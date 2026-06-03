<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Appartenir;
use App\Entity\Depense;
use App\Entity\Groupe;
use App\Entity\Notification;
use App\Entity\Remboursement;
use App\Entity\Repartir;
use App\Entity\Utilisateur;
use App\Enum\RoleAppartenir;
use App\Enum\StatutInvitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NotificationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $cacheDir = dirname(__DIR__, 3) . '/var/share/test/pools/app';
        if (is_dir($cacheDir)) {
            $this->rrmdir($cacheDir);
        }

        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM ' . Notification::class . ' n')->execute();
        $this->em->createQuery('DELETE FROM ' . Remboursement::class . ' rb')->execute();
        $this->em->createQuery('DELETE FROM ' . Repartir::class . ' r')->execute();
        $this->em->createQuery('DELETE FROM ' . Depense::class . ' d')->execute();
        $this->em->createQuery('DELETE FROM ' . Appartenir::class . ' a')->execute();
        $this->em->createQuery('DELETE FROM ' . Groupe::class . ' g')->execute();
        $this->em->createQuery('DELETE FROM ' . Utilisateur::class . ' u')->execute();
    }

    private function rrmdir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $p = $dir . '/' . $item;
            is_dir($p) ? $this->rrmdir($p) : unlink($p);
        }
        rmdir($dir);
    }

    public function testInvitationCreatesNotificationForInvitee(): void
    {
        $emailA = 'nA_' . uniqid() . '@test.com';
        $emailB = 'nB_' . uniqid() . '@test.com';
        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);
        $groupId = $this->createGroup($tokenA);

        $this->jsonRequest('POST', '/api/groups/' . $groupId . '/invitations', ['email' => $emailB], $tokenA);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/notifications', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB]);
        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(1, $body);
        self::assertSame('invitation_recue', $body[0]['type']);
        self::assertFalse($body[0]['lue']);
        self::assertSame('appartenir', $body[0]['reference_type']);
    }

    public function testUnreadCountEndpoint(): void
    {
        $emailA = 'nA_' . uniqid() . '@test.com';
        $emailB = 'nB_' . uniqid() . '@test.com';
        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);
        $groupId = $this->createGroup($tokenA);

        $this->jsonRequest('POST', '/api/groups/' . $groupId . '/invitations', ['email' => $emailB], $tokenA);

        $this->client->request('GET', '/api/notifications/unread-count', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB]);
        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(1, $body['count']);
    }

    public function testListUnreadFilter(): void
    {
        $emailA = 'nA_' . uniqid() . '@test.com';
        $emailB = 'nB_' . uniqid() . '@test.com';
        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);
        $groupId = $this->createGroup($tokenA);

        $this->jsonRequest('POST', '/api/groups/' . $groupId . '/invitations', ['email' => $emailB], $tokenA);

        $notif = $this->em->getRepository(Notification::class)->findOneBy([]);
        $notifId = $notif->getId();

        $this->client->request('POST', '/api/notifications/' . $notifId . '/read', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB]);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/notifications?unread=true', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB]);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(0, $body);

        $this->client->request('GET', '/api/notifications', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB]);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(1, $body);
        self::assertTrue($body[0]['lue']);
    }

    public function testMarkReadByWrongUserReturns403(): void
    {
        $emailA = 'nA_' . uniqid() . '@test.com';
        $emailB = 'nB_' . uniqid() . '@test.com';
        $emailC = 'nC_' . uniqid() . '@test.com';
        $tokenA = $this->createUserAndGetToken($emailA);
        $this->createUserAndGetToken($emailB);
        $tokenC = $this->createUserAndGetToken($emailC);
        $groupId = $this->createGroup($tokenA);
        $this->jsonRequest('POST', '/api/groups/' . $groupId . '/invitations', ['email' => $emailB], $tokenA);

        $notif = $this->em->getRepository(Notification::class)->findOneBy([]);

        $this->client->request('POST', '/api/notifications/' . $notif->getId() . '/read', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenC]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testMarkAllAsRead(): void
    {
        $emailA = 'nA_' . uniqid() . '@test.com';
        $emailB = 'nB_' . uniqid() . '@test.com';
        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);
        $groupId = $this->createGroup($tokenA);

        $this->jsonRequest('POST', '/api/groups/' . $groupId . '/invitations', ['email' => $emailB], $tokenA);

        $this->client->request('POST', '/api/notifications/read-all', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB]);
        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(1, $body['updated']);

        $this->client->request('GET', '/api/notifications/unread-count', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB]);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(0, $body['count']);
    }

    public function testExpenseCreationNotifiesOtherAcceptedMembers(): void
    {
        $emailA = 'nA_' . uniqid() . '@test.com';
        $emailB = 'nB_' . uniqid() . '@test.com';
        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);
        $userAId = $this->getCurrentUserId($tokenA);
        $userBId = $this->getCurrentUserId($tokenB);
        $groupId = $this->createGroup($tokenA);
        $this->addMemberToGroup($groupId, $userBId);

        // créer une catégorie via fixtures absentes : récupérer existante.
        $catId = $this->getOrCreateCategoryId();

        $this->jsonRequest('POST', '/api/groups/' . $groupId . '/expenses', [
            'description' => 'Pizza',
            'montant' => 30.00,
            'id_categorie' => $catId,
            'beneficiaire_ids' => [$userAId, $userBId],
            'type_repartition' => 'equitable',
        ], $tokenA);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/notifications', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB]);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertGreaterThanOrEqual(1, count($body));
        $depenseNotifs = array_filter($body, fn ($n) => $n['type'] === 'depense_ajoutee');
        self::assertCount(1, $depenseNotifs);
    }

    public function testReimbursementLifecycleNotifications(): void
    {
        $emailA = 'nA_' . uniqid() . '@test.com';
        $emailB = 'nB_' . uniqid() . '@test.com';
        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);
        $userAId = $this->getCurrentUserId($tokenA);
        $userBId = $this->getCurrentUserId($tokenB);
        $groupId = $this->createGroup($tokenA);
        $this->addMemberToGroup($groupId, $userBId);

        // B propose à A.
        $this->jsonRequest('POST', '/api/groups/' . $groupId . '/remboursements', [
            'id_crediteur' => $userAId,
            'montant' => 12.00,
        ], $tokenB);
        self::assertResponseStatusCodeSame(201);
        $rbId = json_decode($this->client->getResponse()->getContent(), true)['id'];

        // A doit recevoir RemboursementPropose.
        $this->client->request('GET', '/api/notifications', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA]);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(1, $body);
        self::assertSame('remboursement_propose', $body[0]['type']);

        // A accepte -> B reçoit RemboursementAccepte.
        $this->client->request('POST', '/api/remboursements/' . $rbId . '/accept', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA]);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/notifications', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB]);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        $types = array_column($body, 'type');
        self::assertContains('remboursement_accepte', $types);
    }

    private function getOrCreateCategoryId(): int
    {
        $cat = $this->em->getRepository(\App\Entity\Categorie::class)->findOneBy([]);
        if ($cat !== null) {
            return $cat->getId();
        }
        $cat = new \App\Entity\Categorie();
        $cat->setNom('Test')->setIcone('cart')->setCouleur('#000');
        $this->em->persist($cat);
        $this->em->flush();

        return $cat->getId();
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
        $this->jsonRequest('POST', '/api/groups', ['nom' => 'G ' . uniqid()], $token);
        self::assertResponseStatusCodeSame(201);

        return json_decode($this->client->getResponse()->getContent(), true)['id'];
    }

    private function getCurrentUserId(string $token): int
    {
        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true)['id'];
    }

    private function addMemberToGroup(int $groupId, int $userId): void
    {
        $g = $this->em->getRepository(Groupe::class)->find($groupId);
        $u = $this->em->getRepository(Utilisateur::class)->find($userId);

        $a = (new Appartenir())
            ->setUtilisateur($u)
            ->setGroupe($g)
            ->setRole(RoleAppartenir::Membre)
            ->setStatutInvitation(StatutInvitation::Acceptee)
            ->setDateAdhesion(new \DateTimeImmutable())
            ->setTokenInvitation(bin2hex(random_bytes(32)));

        $this->em->persist($a);
        $this->em->flush();
    }

    private function jsonRequest(string $method, string $uri, array $payload, ?string $token): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $this->client->request($method, $uri, server: $server, content: json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
