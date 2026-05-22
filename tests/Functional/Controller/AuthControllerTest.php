<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->createQuery('DELETE FROM ' . Utilisateur::class . ' u')->execute();
    }

    public function testRegisterValidReturns201(): void
    {
        $this->jsonRequest('POST', '/api/register', [
            'nom' => 'Sarr',
            'prenom' => 'Mouhamed',
            'email' => 'new@test.com',
            'motDePasse' => 'SecurePass1',
            'cguAcceptees' => true,
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('new@test.com', $body['email']);
        self::assertArrayHasKey('id', $body);
    }

    public function testRegisterDuplicateEmailReturns409(): void
    {
        $this->register('dup@test.com', 'SecurePass1');

        $this->jsonRequest('POST', '/api/register', [
            'nom' => 'Other',
            'prenom' => 'User',
            'email' => 'dup@test.com',
            'motDePasse' => 'SecurePass2',
            'cguAcceptees' => true,
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testRegisterWeakPasswordReturns422(): void
    {
        $this->jsonRequest('POST', '/api/register', [
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'weak@test.com',
            'motDePasse' => 'weak',
            'cguAcceptees' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testRegisterWithoutCguConsentReturns422(): void
    {
        $this->jsonRequest('POST', '/api/register', [
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'nocgu@test.com',
            'motDePasse' => 'SecurePass1',
            'cguAcceptees' => false,
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testLoginValidReturnsJwt(): void
    {
        $this->register('login@test.com', 'SecurePass1');

        $this->jsonRequest('POST', '/api/login', [
            'email' => 'login@test.com',
            'motDePasse' => 'SecurePass1',
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $body);
        self::assertNotEmpty($body['token']);
    }

    public function testLoginWrongPasswordReturns401(): void
    {
        $this->register('bad@test.com', 'SecurePass1');

        $this->jsonRequest('POST', '/api/login', [
            'email' => 'bad@test.com',
            'motDePasse' => 'WrongPass1',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testMeReturnsAuthenticatedUser(): void
    {
        $this->register('me@test.com', 'SecurePass1');

        $token = $this->loginAndGetToken('me@test.com', 'SecurePass1');

        $this->client->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('me@test.com', $body['email']);
        self::assertContains('ROLE_USER', $body['roles']);
    }

    public function testMeWithoutTokenReturns401(): void
    {
        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginThrottlingReturns429AfterFiveAttempts(): void
    {
        $this->register('throttle@test.com', 'SecurePass1');

        for ($i = 1; $i <= 5; $i++) {
            $this->jsonRequest('POST', '/api/login', [
                'email' => 'throttle@test.com',
                'motDePasse' => 'WrongPass1',
            ]);
            self::assertResponseStatusCodeSame(401, "Tentative $i devrait renvoyer 401");
        }

        $this->jsonRequest('POST', '/api/login', [
            'email' => 'throttle@test.com',
            'motDePasse' => 'WrongPass1',
        ]);
        self::assertResponseStatusCodeSame(429);
    }

    private function jsonRequest(string $method, string $uri, array $payload): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private function register(string $email, string $password): void
    {
        $this->jsonRequest('POST', '/api/register', [
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => $email,
            'motDePasse' => $password,
            'cguAcceptees' => true,
        ]);
        self::assertResponseStatusCodeSame(201);
    }

    private function loginAndGetToken(string $email, string $password): string
    {
        $this->jsonRequest('POST', '/api/login', [
            'email' => $email,
            'motDePasse' => $password,
        ]);
        self::assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true)['token'];
    }
}
