<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Appartenir;
use App\Entity\Depense;
use App\Entity\Groupe;
use App\Entity\Remboursement;
use App\Entity\Repartir;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InvitationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $cacheDir = dirname(__DIR__, 3).'/var/share/test/pools/app';
        if (is_dir($cacheDir)) {
            $this->rrmdir($cacheDir);
        }

        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM '.Remboursement::class.' rb')->execute();
        $this->em->createQuery('DELETE FROM '.Repartir::class.' r')->execute();
        $this->em->createQuery('DELETE FROM '.Depense::class.' d')->execute();
        $this->em->createQuery('DELETE FROM '.Appartenir::class.' a')->execute();
        $this->em->createQuery('DELETE FROM '.Groupe::class.' g')->execute();
        $this->em->createQuery('DELETE FROM '.Utilisateur::class.' u')->execute();
    }

    private function rrmdir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $p = $dir.'/'.$item;
            is_dir($p) ? $this->rrmdir($p) : unlink($p);
        }
        rmdir($dir);
    }

    public function testCreatorInvitesByEmailReturns201WithToken(): void
    {
        $emailA = 'iA_'.uniqid().'@test.com';
        $emailB = 'iB_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $this->createUserAndGetToken($emailB);
        $groupId = $this->createGroup($tokenA);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/invitations', [
            'email' => $emailB,
        ], $tokenA);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('en_attente', $body['statut_invitation']);
        self::assertSame($emailB, $body['utilisateur']['email']);
        self::assertNotEmpty($body['token']);
        self::assertSame(64, strlen($body['token']));
        self::assertNotNull($body['date_expiration']);
    }

    public function testNonCreatorCannotInvite(): void
    {
        $emailA = 'iA_'.uniqid().'@test.com';
        $emailB = 'iB_'.uniqid().'@test.com';
        $emailC = 'iC_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);
        $this->createUserAndGetToken($emailC);

        $groupId = $this->createGroup($tokenA);

        // B is not a member, attempts to invite C.
        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/invitations', [
            'email' => $emailC,
        ], $tokenB);

        self::assertResponseStatusCodeSame(403);
    }

    public function testInviteUnknownEmailReturns422(): void
    {
        $emailA = 'iA_'.uniqid().'@test.com';
        $tokenA = $this->createUserAndGetToken($emailA);
        $groupId = $this->createGroup($tokenA);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/invitations', [
            'email' => 'ghost@test.com',
        ], $tokenA);

        self::assertResponseStatusCodeSame(422);
    }

    public function testDuplicateInvitationReturns409(): void
    {
        $emailA = 'iA_'.uniqid().'@test.com';
        $emailB = 'iB_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $this->createUserAndGetToken($emailB);
        $groupId = $this->createGroup($tokenA);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/invitations', ['email' => $emailB], $tokenA);
        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/invitations', ['email' => $emailB], $tokenA);
        self::assertResponseStatusCodeSame(409);
    }

    public function testInviteeAcceptsInvitation(): void
    {
        $emailA = 'iA_'.uniqid().'@test.com';
        $emailB = 'iB_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);
        $groupId = $this->createGroup($tokenA);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/invitations', ['email' => $emailB], $tokenA);
        self::assertResponseStatusCodeSame(201);
        $token = json_decode($this->client->getResponse()->getContent(), true)['token'];

        $this->client->request('POST', '/api/invitations/'.$token.'/accept', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenB,
        ]);
        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('acceptee', $body['statut_invitation']);
        self::assertNotNull($body['date_acceptation']);
        self::assertNotNull($body['date_adhesion']);
    }

    public function testWrongUserCannotAcceptInvitation(): void
    {
        $emailA = 'iA_'.uniqid().'@test.com';
        $emailB = 'iB_'.uniqid().'@test.com';
        $emailC = 'iC_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $this->createUserAndGetToken($emailB);
        $tokenC = $this->createUserAndGetToken($emailC);
        $groupId = $this->createGroup($tokenA);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/invitations', ['email' => $emailB], $tokenA);
        $token = json_decode($this->client->getResponse()->getContent(), true)['token'];

        $this->client->request('POST', '/api/invitations/'.$token.'/accept', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenC,
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testInviteeRefusesInvitation(): void
    {
        $emailA = 'iA_'.uniqid().'@test.com';
        $emailB = 'iB_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);
        $groupId = $this->createGroup($tokenA);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/invitations', ['email' => $emailB], $tokenA);
        $token = json_decode($this->client->getResponse()->getContent(), true)['token'];

        $this->client->request('POST', '/api/invitations/'.$token.'/refuse', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenB,
        ]);
        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('refusee', $body['statut_invitation']);
    }

    public function testAcceptWithUnknownTokenReturns404(): void
    {
        $tokenA = $this->createUserAndGetToken('iA_'.uniqid().'@test.com');

        $this->client->request('POST', '/api/invitations/'.str_repeat('a', 64).'/accept', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenA,
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testExpiredInvitationReturns410(): void
    {
        $emailA = 'iA_'.uniqid().'@test.com';
        $emailB = 'iB_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);
        $groupId = $this->createGroup($tokenA);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/invitations', ['email' => $emailB], $tokenA);
        $token = json_decode($this->client->getResponse()->getContent(), true)['token'];

        // Forcefully expire by setting date_expiration in the past via the EM.
        $appartenir = $this->em->getRepository(Appartenir::class)->findOneBy(['tokenInvitation' => $token]);
        $appartenir->setDateExpiration(new \DateTimeImmutable('-1 day'));
        $this->em->flush();
        $this->em->clear();

        $this->client->request('POST', '/api/invitations/'.$token.'/accept', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenB,
        ]);
        self::assertResponseStatusCodeSame(410);
    }

    public function testListPendingInvitationsForCurrentUser(): void
    {
        $emailA = 'iA_'.uniqid().'@test.com';
        $emailB = 'iB_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);
        $groupId = $this->createGroup($tokenA);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/invitations', ['email' => $emailB], $tokenA);

        $this->client->request('GET', '/api/invitations/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenB,
        ]);
        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(1, $body);
        self::assertSame('en_attente', $body[0]['statut_invitation']);
        self::assertSame($groupId, $body[0]['groupe']['id']);
    }

    public function testListMembersReturnsAcceptedAndPending(): void
    {
        $emailA = 'iA_'.uniqid().'@test.com';
        $emailB = 'iB_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $this->createUserAndGetToken($emailB);
        $groupId = $this->createGroup($tokenA);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/invitations', ['email' => $emailB], $tokenA);

        $this->client->request('GET', '/api/groups/'.$groupId.'/members', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenA,
        ]);
        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(2, $body);

        $emails = array_column($body, 'email');
        self::assertContains($emailA, $emails);
        self::assertContains($emailB, $emails);
    }

    public function testListMembersForbiddenForNonMember(): void
    {
        $emailA = 'iA_'.uniqid().'@test.com';
        $emailC = 'iC_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenC = $this->createUserAndGetToken($emailC);
        $groupId = $this->createGroup($tokenA);

        $this->client->request('GET', '/api/groups/'.$groupId.'/members', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenC,
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    private function createUserAndGetToken(string $email): string
    {
        $this->jsonRequest('POST', '/api/register', [
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => $email,
            'motDePasse' => 'SecurePass1',
            'cguAcceptees' => true,
        ], null);
        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/api/login', ['email' => $email, 'motDePasse' => 'SecurePass1'], null);
        self::assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true)['token'];
    }

    private function createGroup(string $token): int
    {
        $this->jsonRequest('POST', '/api/groups', ['nom' => 'G '.uniqid()], $token);
        self::assertResponseStatusCodeSame(201);

        return json_decode($this->client->getResponse()->getContent(), true)['id'];
    }

    private function jsonRequest(string $method, string $uri, array $payload, ?string $token): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request($method, $uri, server: $server, content: json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
