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

final class BalancesControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $categorieId;

    protected function setUp(): void
    {
        $cacheDir = dirname(__DIR__, 3) . '/var/share/test/pools/app';
        if (is_dir($cacheDir)) {
            foreach (scandir($cacheDir) ?: [] as $item) {
                if ($item !== '.' && $item !== '..') {
                    $path = $cacheDir . '/' . $item;
                    is_dir($path) ? $this->rrmdir($path) : unlink($path);
                }
            }
            rmdir($cacheDir);
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
            $p = $dir . '/' . $item;
            is_dir($p) ? $this->rrmdir($p) : unlink($p);
        }
        rmdir($dir);
    }

    public function testBalancesReturnsSoldesAndRemboursements(): void
    {
        $tokenA = $this->createUserAndGetToken('a_' . uniqid() . '@test.com');
        $tokenB = $this->createUserAndGetToken('b_' . uniqid() . '@test.com');

        $groupId = $this->createGroup($tokenA);
        $userAId = $this->getCurrentUserId($tokenA);
        $userBId = $this->getCurrentUserId($tokenB);

        $this->addMemberToGroup($groupId, $userBId);

        // A paie 30 pour {A, B} -> A doit recevoir 15.
        $this->jsonRequest('POST', '/api/groups/' . $groupId . '/expenses', [
            'description' => 'Repas',
            'montant' => 30.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userAId, $userBId],
        ], $tokenA);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/groups/' . $groupId . '/balances', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
        ]);
        self::assertResponseIsSuccessful();

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('soldes', $body);
        self::assertArrayHasKey('remboursements', $body);
        self::assertCount(2, $body['soldes']);

        $balanceById = [];
        foreach ($body['soldes'] as $s) {
            $balanceById[$s['user']['id']] = $s['balance'];
        }
        self::assertSame('15.00', $balanceById[$userAId]);
        self::assertSame('-15.00', $balanceById[$userBId]);

        self::assertCount(1, $body['remboursements']);
        self::assertSame($userBId, $body['remboursements'][0]['from']['id']);
        self::assertSame($userAId, $body['remboursements'][0]['to']['id']);
        self::assertSame('15.00', $body['remboursements'][0]['montant']);
    }

    public function testBalancesForbiddenForNonMember(): void
    {
        $tokenOwner = $this->createUserAndGetToken('o_' . uniqid() . '@test.com');
        $tokenOut = $this->createUserAndGetToken('out_' . uniqid() . '@test.com');
        $groupId = $this->createGroup($tokenOwner);

        $this->client->request('GET', '/api/groups/' . $groupId . '/balances', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenOut,
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
