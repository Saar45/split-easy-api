<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Appartenir;
use App\Entity\Categorie;
use App\Entity\Depense;
use App\Entity\Groupe;
use App\Entity\Remboursement;
use App\Entity\Repartir;
use App\Entity\Utilisateur;
use App\Enum\RoleAppartenir;
use App\Enum\StatutInvitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $categorieId;

    protected function setUp(): void
    {
        $cacheDir = dirname(__DIR__, 3) . '/var/share/test/pools/app';
        if (is_dir($cacheDir)) {
            $this->rrmdir($cacheDir);
        }

        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM ' . Remboursement::class . ' rb')->execute();
        $this->em->createQuery('DELETE FROM ' . Repartir::class . ' r')->execute();
        $this->em->createQuery('DELETE FROM ' . Depense::class . ' d')->execute();
        $this->em->createQuery('DELETE FROM ' . Appartenir::class . ' a')->execute();
        $this->em->createQuery('DELETE FROM ' . Groupe::class . ' g')->execute();
        $this->em->createQuery('DELETE FROM ' . Utilisateur::class . ' u')->execute();

        $categorie = $this->em->getRepository(Categorie::class)->findOneBy([]);
        if ($categorie === null) {
            $categorie = (new Categorie())
                ->setLibelle('Cat ' . uniqid())
                ->setIcone('test')
                ->setCouleur('#000000')
                ->setOrdreAffichage(99);
            $this->em->persist($categorie);
            $this->em->flush();
        }
        $this->categorieId = $categorie->getId();
    }

    private function rrmdir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testUnauthenticatedReturns401(): void
    {
        $this->client->request('GET', '/api/dashboard');

        self::assertResponseStatusCodeSame(401);
    }

    public function testNewUserWithNoGroupsReturnsZeros(): void
    {
        $token = $this->createUserAndGetToken('dash_empty_' . uniqid() . '@test.com');

        $this->client->request('GET', '/api/dashboard', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('0.00', $body['solde_net']);
        self::assertSame('0.00', $body['total_du']);
        self::assertSame('0.00', $body['total_a_recevoir']);
        self::assertSame([], $body['dernieres_depenses']);
        self::assertSame(0, $body['invitations_en_attente']);
    }

    public function testSoldeNetAggregatesAcrossTwoGroups(): void
    {
        // User A pays in group 1 (positive balance) and owes in group 2 (negative balance).
        $emailA = 'dash_a_' . uniqid() . '@test.com';
        $emailB = 'dash_b_' . uniqid() . '@test.com';
        $emailC = 'dash_c_' . uniqid() . '@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);
        $tokenC = $this->createUserAndGetToken($emailC);

        $userAId = $this->getCurrentUserId($tokenA);
        $userBId = $this->getCurrentUserId($tokenB);
        $userCId = $this->getCurrentUserId($tokenC);

        // Groupe 1 : A paie 40 pour {A, B} => A balance = +20.
        $group1Id = $this->createGroup($tokenA);
        $this->addMemberDirectly($group1Id, $userBId);

        $this->jsonRequest('POST', '/api/groups/' . $group1Id . '/expenses', [
            'description' => 'Repas groupe 1',
            'montant' => 40.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userAId, $userBId],
        ], $tokenA);
        self::assertResponseStatusCodeSame(201);

        // Groupe 2 : C paie 30 pour {A, C} => A balance = -15.
        $group2Id = $this->createGroup($tokenC);
        $this->addMemberDirectly($group2Id, $userAId);

        $this->jsonRequest('POST', '/api/groups/' . $group2Id . '/expenses', [
            'description' => 'Courses groupe 2',
            'montant' => 30.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userAId, $userCId],
        ], $tokenC);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/dashboard', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        // total_a_recevoir = 20.00, total_du = 15.00, solde_net = 5.00.
        self::assertSame('20.00', $body['total_a_recevoir']);
        self::assertSame('15.00', $body['total_du']);
        self::assertSame('5.00', $body['solde_net']);
    }

    public function testDernieresDepensesLimitedToThreeSortedDesc(): void
    {
        $emailA = 'dash_dep_' . uniqid() . '@test.com';
        $emailB = 'dash_dep2_' . uniqid() . '@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);

        $userAId = $this->getCurrentUserId($tokenA);
        $userBId = $this->getCurrentUserId($tokenB);

        $groupId = $this->createGroup($tokenA);
        $this->addMemberDirectly($groupId, $userBId);

        // Insert 4 expenses with explicit dates via the entity manager.
        $groupe = $this->em->getRepository(Groupe::class)->find($groupId);
        $userA = $this->em->getRepository(Utilisateur::class)->find($userAId);
        $categorie = $this->em->getRepository(Categorie::class)->find($this->categorieId);

        $dates = ['2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01'];
        $descriptions = ['Jan', 'Feb', 'Mar', 'Apr'];

        foreach ($dates as $i => $dateStr) {
            $depense = (new Depense())
                ->setDescription($descriptions[$i])
                ->setMontant('10.00')
                ->setDateDepense(new \DateTimeImmutable($dateStr))
                ->setCategorie($categorie)
                ->setPayeur($userA)
                ->setGroupe($groupe);
            $this->em->persist($depense);

            $part = (new Repartir())
                ->setDepense($depense)
                ->setBeneficiaire($userA)
                ->setMontantPart('10.00');
            $this->em->persist($part);
        }
        $this->em->flush();

        $this->client->request('GET', '/api/dashboard', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        $depenses = $body['dernieres_depenses'];
        self::assertCount(3, $depenses);

        // Most recent first.
        self::assertSame('Apr', $depenses[0]['description']);
        self::assertSame('Mar', $depenses[1]['description']);
        self::assertSame('Feb', $depenses[2]['description']);
    }

    public function testInvitationsEnAttenteCountsOnlyActiveInvitations(): void
    {
        $emailA = 'dash_inv_a_' . uniqid() . '@test.com';
        $emailB = 'dash_inv_b_' . uniqid() . '@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);

        $userBId = $this->getCurrentUserId($tokenB);

        // Group 1 : valid pending invitation for B (expiration in the future).
        $group1Id = $this->createGroup($tokenA);
        $this->jsonRequest('POST', '/api/groups/' . $group1Id . '/invitations', [
            'email' => $emailB,
        ], $tokenA);
        self::assertResponseStatusCodeSame(201);

        // Group 2 : expired invitation for B.
        $group2Id = $this->createGroup($tokenA);
        $this->jsonRequest('POST', '/api/groups/' . $group2Id . '/invitations', [
            'email' => $emailB,
        ], $tokenA);
        self::assertResponseStatusCodeSame(201);

        // Expire group2 invitation.
        $userB = $this->em->getRepository(Utilisateur::class)->find($userBId);
        $groupe2 = $this->em->getRepository(Groupe::class)->find($group2Id);
        $appartenir = $this->em->getRepository(Appartenir::class)->findOneBy([
            'utilisateur' => $userB,
            'groupe' => $groupe2,
        ]);
        $appartenir->setDateExpiration(new \DateTimeImmutable('-1 day'));
        $this->em->flush();
        $this->em->clear();

        $this->client->request('GET', '/api/dashboard', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        // Only 1 active invitation, the expired one is filtered out.
        self::assertSame(1, $body['invitations_en_attente']);
    }

    public function testDashboardResponseShape(): void
    {
        $token = $this->createUserAndGetToken('dash_shape_' . uniqid() . '@test.com');

        $this->client->request('GET', '/api/dashboard', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertArrayHasKey('solde_net', $body);
        self::assertArrayHasKey('total_du', $body);
        self::assertArrayHasKey('total_a_recevoir', $body);
        self::assertArrayHasKey('dernieres_depenses', $body);
        self::assertArrayHasKey('invitations_en_attente', $body);
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

    private function getCurrentUserId(string $token): int
    {
        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true)['id'];
    }

    private function createGroup(string $token): int
    {
        $this->jsonRequest('POST', '/api/groups', ['nom' => 'G ' . uniqid()], $token);
        self::assertResponseStatusCodeSame(201);

        return json_decode($this->client->getResponse()->getContent(), true)['id'];
    }

    private function addMemberDirectly(int $groupId, int $userId): void
    {
        $groupe = $this->em->getRepository(Groupe::class)->find($groupId);
        $user = $this->em->getRepository(Utilisateur::class)->find($userId);

        $a = (new Appartenir())
            ->setUtilisateur($user)
            ->setGroupe($groupe)
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
