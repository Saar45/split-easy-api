<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Appartenir;
use App\Entity\Depense;
use App\Entity\Groupe;
use App\Entity\PreferencesUtilisateur;
use App\Entity\Repartir;
use App\Entity\Remboursement;
use App\Entity\Utilisateur;
use App\Enum\RoleAppartenir;
use App\Enum\StatutInvitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserMeControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Clean in FK-safe order.
        $this->em->createQuery('DELETE FROM ' . PreferencesUtilisateur::class . ' p')->execute();
        $this->em->createQuery('DELETE FROM ' . Remboursement::class . ' r')->execute();
        $this->em->createQuery('DELETE FROM ' . Repartir::class . ' r')->execute();
        $this->em->createQuery('DELETE FROM ' . Depense::class . ' d')->execute();
        $this->em->createQuery('DELETE FROM ' . Appartenir::class . ' a')->execute();
        $this->em->createQuery('DELETE FROM ' . Groupe::class . ' g')->execute();
        $this->em->createQuery('DELETE FROM ' . Utilisateur::class . ' u')->execute();
    }

    // -------------------------------------------------------------------
    // GET /api/users/me/data
    // -------------------------------------------------------------------

    public function testDataExportReturns401WhenUnauthenticated(): void
    {
        $this->client->request('GET', '/api/users/me/data');
        self::assertResponseStatusCodeSame(401);
    }

    public function testDataExportReturns200WithUserAndRelations(): void
    {
        $email = 'export_' . uniqid() . '@test.com';
        $this->register($email, 'SecurePass1');
        $token = $this->loginAndGetToken($email, 'SecurePass1');

        // Create a group so that groupes_crees and groupes_membre_de have data.
        $this->jsonRequest('POST', '/api/groups', ['nom' => 'Export Group'], $token);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/users/me/data', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);

        $response = $this->client->getResponse();
        self::assertStringContainsString('attachment', $response->headers->get('Content-Disposition') ?? '');
        self::assertStringContainsString('mes-donnees-spliteasy.json', $response->headers->get('Content-Disposition') ?? '');

        $body = json_decode($response->getContent(), true);
        self::assertArrayHasKey('utilisateur', $body);
        self::assertArrayHasKey('groupes_membre_de', $body);
        self::assertArrayHasKey('groupes_crees', $body);
        self::assertArrayHasKey('depenses_payees', $body);
        self::assertArrayHasKey('parts_recues', $body);
        self::assertArrayHasKey('remboursements', $body);

        self::assertSame($email, $body['utilisateur']['email']);
        self::assertNotEmpty($body['groupes_crees']);
        self::assertSame('Export Group', $body['groupes_crees'][0]['nom']);
    }

    public function testDataExportDoesNotLeakOtherUserPii(): void
    {
        $emailA = 'owner_export_' . uniqid() . '@test.com';
        $emailB = 'member_export_' . uniqid() . '@test.com';

        $this->register($emailA, 'SecurePass1');
        $this->register($emailB, 'SecurePass1');

        $tokenA = $this->loginAndGetToken($emailA, 'SecurePass1');

        // Add user B to user A's group manually.
        $this->jsonRequest('POST', '/api/groups', ['nom' => 'Shared'], $tokenA);
        $groupId = json_decode($this->client->getResponse()->getContent(), true)['id'];

        $userB  = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $emailB]);
        $groupe = $this->em->getRepository(Groupe::class)->find($groupId);
        $a = (new Appartenir())
            ->setUtilisateur($userB)
            ->setGroupe($groupe)
            ->setRole(RoleAppartenir::Membre)
            ->setStatutInvitation(StatutInvitation::Acceptee)
            ->setDateAdhesion(new \DateTimeImmutable())
            ->setTokenInvitation(bin2hex(random_bytes(32)));
        $this->em->persist($a);
        $this->em->flush();

        $this->client->request('GET', '/api/users/me/data', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
        ]);

        self::assertResponseIsSuccessful();
        $raw = $this->client->getResponse()->getContent();

        // User B's email must not appear anywhere in the export.
        self::assertStringNotContainsString($emailB, $raw);
    }

    // -------------------------------------------------------------------
    // DELETE /api/users/me
    // -------------------------------------------------------------------

    public function testDeleteAccountReturns204WhenNoGroupOwnership(): void
    {
        $email = 'del_solo_' . uniqid() . '@test.com';
        $this->register($email, 'SecurePass1');
        $token = $this->loginAndGetToken($email, 'SecurePass1');

        $this->client->request('DELETE', '/api/users/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(204);
    }

    public function testDeleteAccountReturns409WhenCreatorOfActiveGroup(): void
    {
        $emailCreator = 'creator_del_' . uniqid() . '@test.com';
        $emailMember  = 'member_del_' . uniqid() . '@test.com';

        $this->register($emailCreator, 'SecurePass1');
        $this->register($emailMember, 'SecurePass1');

        $tokenCreator = $this->loginAndGetToken($emailCreator, 'SecurePass1');

        // Create group so creator has ownership.
        $this->jsonRequest('POST', '/api/groups', ['nom' => 'Active Group'], $tokenCreator);
        $groupId = json_decode($this->client->getResponse()->getContent(), true)['id'];

        // Add a second accepted member to trigger the guard.
        $userMember = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $emailMember]);
        $groupe     = $this->em->getRepository(Groupe::class)->find($groupId);
        $app = (new Appartenir())
            ->setUtilisateur($userMember)
            ->setGroupe($groupe)
            ->setRole(RoleAppartenir::Membre)
            ->setStatutInvitation(StatutInvitation::Acceptee)
            ->setDateAdhesion(new \DateTimeImmutable())
            ->setTokenInvitation(bin2hex(random_bytes(32)));
        $this->em->persist($app);
        $this->em->flush();

        $this->client->request('DELETE', '/api/users/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenCreator,
        ]);

        self::assertResponseStatusCodeSame(409);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $body);
        self::assertStringContainsString('créateur', $body['error']);
    }

    public function testDeleteAccountMakesSubsequentLoginReturn401(): void
    {
        $email = 'gone_' . uniqid() . '@test.com';
        $this->register($email, 'SecurePass1');
        $token = $this->loginAndGetToken($email, 'SecurePass1');

        $this->client->request('DELETE', '/api/users/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        self::assertResponseStatusCodeSame(204);

        // Account is gone — login must fail.
        $this->jsonRequest('POST', '/api/login', ['email' => $email, 'motDePasse' => 'SecurePass1'], null);
        self::assertResponseStatusCodeSame(401);
    }

    // -------------------------------------------------------------------
    // GET /api/users/me/preferences
    // -------------------------------------------------------------------

    public function testGetPreferencesReturns401WhenUnauthenticated(): void
    {
        $this->client->request('GET', '/api/users/me/preferences');
        self::assertResponseStatusCodeSame(401);
    }

    public function testGetPreferencesReturnsDefaultsOnFirstCall(): void
    {
        $email = 'prefs_' . uniqid() . '@test.com';
        $this->register($email, 'SecurePass1');
        $token = $this->loginAndGetToken($email, 'SecurePass1');

        $this->client->request('GET', '/api/users/me/preferences', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('notifications_email', $body);
        self::assertArrayHasKey('notifications_push', $body);
        self::assertTrue($body['notifications_email']);
        self::assertTrue($body['notifications_push']);
    }

    // -------------------------------------------------------------------
    // PUT /api/users/me/preferences
    // -------------------------------------------------------------------

    public function testPutPreferencesUpdatesToggle(): void
    {
        $email = 'prefs_put_' . uniqid() . '@test.com';
        $this->register($email, 'SecurePass1');
        $token = $this->loginAndGetToken($email, 'SecurePass1');

        $this->jsonRequest('PUT', '/api/users/me/preferences', [
            'notifications_email' => false,
            'notifications_push'  => true,
        ], $token);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertFalse($body['notifications_email']);
        self::assertTrue($body['notifications_push']);

        // Verify GET returns the persisted value.
        $this->client->request('GET', '/api/users/me/preferences', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $body2 = json_decode($this->client->getResponse()->getContent(), true);
        self::assertFalse($body2['notifications_email']);
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private function jsonRequest(string $method, string $uri, array $payload, ?string $token): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private function register(string $email, string $password): void
    {
        $this->jsonRequest('POST', '/api/register', [
            'nom'          => 'Test',
            'prenom'       => 'User',
            'email'        => $email,
            'motDePasse'   => $password,
            'cguAcceptees' => true,
        ], null);
        self::assertResponseStatusCodeSame(201);
    }

    private function loginAndGetToken(string $email, string $password): string
    {
        $this->jsonRequest('POST', '/api/login', [
            'email'      => $email,
            'motDePasse' => $password,
        ], null);
        self::assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true)['token'];
    }
}
