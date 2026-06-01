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

final class RemboursementControllerTest extends WebTestCase
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

    public function testProposeReturns201AndPersists(): void
    {
        [$tokenA, $tokenB, $groupId, $userAId, $userBId] = $this->setupTwoMembers();

        // B (débiteur) propose un remboursement vers A (créancier).
        $this->jsonRequest('POST', '/api/groups/' . $groupId . '/remboursements', [
            'id_crediteur' => $userAId,
            'montant' => 15.00,
        ], $tokenB);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('propose', $body['statut']);
        self::assertSame('15.00', $body['montant']);
        self::assertSame($userBId, $body['debiteur']['id']);
        self::assertSame($userAId, $body['crediteur']['id']);
    }

    public function testProposeForbiddenIfNotGroupMember(): void
    {
        $tokenA = $this->createUserAndGetToken('rA_' . uniqid() . '@test.com');
        $tokenC = $this->createUserAndGetToken('rC_' . uniqid() . '@test.com');
        $groupId = $this->createGroup($tokenA);
        $userAId = $this->getCurrentUserId($tokenA);

        $this->jsonRequest('POST', '/api/groups/' . $groupId . '/remboursements', [
            'id_crediteur' => $userAId,
            'montant' => 10.00,
        ], $tokenC);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAcceptByCreditorTransitionsToValide(): void
    {
        [$tokenA, $tokenB, $groupId, $userAId, $userBId] = $this->setupTwoMembers();
        $rbId = $this->proposeRb($tokenB, $groupId, $userAId, 12.00);

        $this->client->request('POST', '/api/remboursements/' . $rbId . '/accept', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
        ]);
        self::assertResponseIsSuccessful();

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('valide', $body['statut']);
        self::assertNotNull($body['date_validation']);
    }

    public function testAcceptByDebtorReturns403(): void
    {
        [$tokenA, $tokenB, $groupId, $userAId] = $this->setupTwoMembers();
        $rbId = $this->proposeRb($tokenB, $groupId, $userAId, 5.00);

        $this->client->request('POST', '/api/remboursements/' . $rbId . '/accept', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB,
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testRejectByCreditorTransitionsToConteste(): void
    {
        [$tokenA, $tokenB, $groupId, $userAId] = $this->setupTwoMembers();
        $rbId = $this->proposeRb($tokenB, $groupId, $userAId, 9.00);

        $this->client->request('POST', '/api/remboursements/' . $rbId . '/reject', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
        ]);
        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('conteste', $body['statut']);
    }

    public function testCancelByDebtorTransitionsToAnnule(): void
    {
        [$tokenA, $tokenB, $groupId, $userAId] = $this->setupTwoMembers();
        $rbId = $this->proposeRb($tokenB, $groupId, $userAId, 7.00);

        $this->client->request('POST', '/api/remboursements/' . $rbId . '/cancel', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB,
        ]);
        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('annule', $body['statut']);
    }

    public function testCancelByCreditorReturns403(): void
    {
        [$tokenA, $tokenB, $groupId, $userAId] = $this->setupTwoMembers();
        $rbId = $this->proposeRb($tokenB, $groupId, $userAId, 7.00);

        $this->client->request('POST', '/api/remboursements/' . $rbId . '/cancel', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testDoubleAcceptReturns409(): void
    {
        [$tokenA, $tokenB, $groupId, $userAId] = $this->setupTwoMembers();
        $rbId = $this->proposeRb($tokenB, $groupId, $userAId, 4.00);

        $this->client->request('POST', '/api/remboursements/' . $rbId . '/accept', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
        ]);
        self::assertResponseIsSuccessful();

        // Le deuxième accept doit échouer car le statut n'est plus "propose".
        $this->client->request('POST', '/api/remboursements/' . $rbId . '/accept', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
        ]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testListReturnsRemboursementsInvolvingCurrentUser(): void
    {
        [$tokenA, $tokenB, $groupId, $userAId] = $this->setupTwoMembers();
        $this->proposeRb($tokenB, $groupId, $userAId, 3.00);

        $this->client->request('GET', '/api/remboursements', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
        ]);
        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(1, $body);
        self::assertSame('propose', $body[0]['statut']);
    }

    public function testSelfReimbursementReturns422(): void
    {
        $tokenA = $this->createUserAndGetToken('selfRb_' . uniqid() . '@test.com');
        $groupId = $this->createGroup($tokenA);
        $userAId = $this->getCurrentUserId($tokenA);

        $this->jsonRequest('POST', '/api/groups/' . $groupId . '/remboursements', [
            'id_crediteur' => $userAId,
            'montant' => 5.00,
        ], $tokenA);

        self::assertResponseStatusCodeSame(422);
    }

    /** @return array{0:string,1:string,2:int,3:int,4:int} */
    private function setupTwoMembers(): array
    {
        $emailA = 'rb_a_' . uniqid() . '@test.com';
        $emailB = 'rb_b_' . uniqid() . '@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);

        $groupId = $this->createGroup($tokenA);
        $userAId = $this->getCurrentUserId($tokenA);
        $userBId = $this->getCurrentUserId($tokenB);

        $this->addMemberToGroup($groupId, $userBId);

        return [$tokenA, $tokenB, $groupId, $userAId, $userBId];
    }

    private function proposeRb(string $tokenDebtor, int $groupId, int $creditorId, float $montant): int
    {
        $this->jsonRequest('POST', '/api/groups/' . $groupId . '/remboursements', [
            'id_crediteur' => $creditorId,
            'montant' => $montant,
        ], $tokenDebtor);
        self::assertResponseStatusCodeSame(201);

        return json_decode($this->client->getResponse()->getContent(), true)['id'];
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
