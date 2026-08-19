<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Appartenir;
use App\Entity\Categorie;
use App\Entity\Depense;
use App\Entity\Groupe;
use App\Entity\Repartir;
use App\Entity\Utilisateur;
use App\Enum\RoleAppartenir;
use App\Enum\StatutInvitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ExpenseControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $categorieId;

    protected function setUp(): void
    {
        $cacheDir = dirname(__DIR__, 3).'/var/share/test/pools/app';
        if (is_dir($cacheDir)) {
            self::removeDir($cacheDir);
        }

        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM '.Repartir::class.' r')->execute();
        $this->em->createQuery('DELETE FROM '.Depense::class.' d')->execute();
        $this->em->createQuery('DELETE FROM '.Appartenir::class.' a')->execute();
        $this->em->createQuery('DELETE FROM '.Groupe::class.' g')->execute();
        $this->em->createQuery('DELETE FROM '.Utilisateur::class.' u')->execute();

        $categorie = $this->em->getRepository(Categorie::class)->findOneBy([]);
        if (null === $categorie) {
            $categorie = (new Categorie())
                ->setLibelle('Test Cat '.uniqid())
                ->setIcone('test')
                ->setCouleur('#000000')
                ->setOrdreAffichage(99);
            $this->em->persist($categorie);
            $this->em->flush();
        }

        $this->categorieId = $categorie->getId();
    }

    private static function removeDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $dir.'/'.$item;
            is_dir($path) ? self::removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testCreateExpenseReturns201AndPersistDepenseAndRepartitions(): void
    {
        $token = $this->createUserAndGetToken('payeur_'.uniqid().'@test.com');
        $groupId = $this->createGroup($token);
        $userId = $this->getCurrentUserId($token);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/expenses', [
            'description' => 'Courses supermarche',
            'montant' => 30.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userId],
        ], $token);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('Courses supermarche', $body['description']);
        self::assertSame('30.00', $body['montant']);
        self::assertArrayHasKey('beneficiaires', $body);
        self::assertCount(1, $body['beneficiaires']);
        self::assertSame('30.00', $body['beneficiaires'][0]['montant_part']);

        $depense = $this->em->getRepository(Depense::class)->find($body['id']);
        self::assertNotNull($depense);

        $repartitions = $this->em->getRepository(Repartir::class)->findBy(['depense' => $depense]);
        self::assertCount(1, $repartitions);
    }

    public function testCreateExpenseWithMultipleBeneficiairesAndCorrectSplit(): void
    {
        $emailA = 'pa_'.uniqid().'@test.com';
        $emailB = 'pb_'.uniqid().'@test.com';
        $emailC = 'pc_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);
        $tokenC = $this->createUserAndGetToken($emailC);

        $groupId = $this->createGroup($tokenA);
        $userAId = $this->getCurrentUserId($tokenA);
        $userBId = $this->getCurrentUserId($tokenB);
        $userCId = $this->getCurrentUserId($tokenC);

        $this->addMemberToGroup($groupId, $userBId);
        $this->addMemberToGroup($groupId, $userCId);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/expenses', [
            'description' => 'Repas restaurant',
            'montant' => 30.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userBId, $userCId, $userAId],
        ], $tokenA);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(3, $body['beneficiaires']);

        $parts = array_column($body['beneficiaires'], 'montant_part');
        sort($parts);
        self::assertSame(['10.00', '10.00', '10.00'], $parts);
    }

    public function testCreateExpenseWithInvalidMontantReturns422(): void
    {
        $token = $this->createUserAndGetToken('inv_montant_'.uniqid().'@test.com');
        $groupId = $this->createGroup($token);
        $userId = $this->getCurrentUserId($token);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/expenses', [
            'description' => 'Bad montant',
            'montant' => -5.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userId],
        ], $token);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateExpenseWithZeroMontantReturns422(): void
    {
        $token = $this->createUserAndGetToken('zero_'.uniqid().'@test.com');
        $groupId = $this->createGroup($token);
        $userId = $this->getCurrentUserId($token);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/expenses', [
            'description' => 'Zero montant',
            'montant' => 0,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userId],
        ], $token);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateExpenseWithNonMemberBeneficiaireReturns422(): void
    {
        $emailPayeur = 'payeur2_'.uniqid().'@test.com';
        $emailOutsider = 'outsider_'.uniqid().'@test.com';

        $tokenPayeur = $this->createUserAndGetToken($emailPayeur);
        $tokenOutsider = $this->createUserAndGetToken($emailOutsider);

        $groupId = $this->createGroup($tokenPayeur);
        $outsiderId = $this->getCurrentUserId($tokenOutsider);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/expenses', [
            'description' => 'Depense invalide',
            'montant' => 10.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$outsiderId],
        ], $tokenPayeur);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateExpenseAsNonMemberReturns403(): void
    {
        $emailOwner = 'owner_'.uniqid().'@test.com';
        $emailOutsider = 'out_'.uniqid().'@test.com';

        $tokenOwner = $this->createUserAndGetToken($emailOwner);
        $tokenOutsider = $this->createUserAndGetToken($emailOutsider);

        $groupId = $this->createGroup($tokenOwner);
        $ownerId = $this->getCurrentUserId($tokenOwner);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/expenses', [
            'description' => 'Forbidden depense',
            'montant' => 10.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$ownerId],
        ], $tokenOutsider);

        self::assertResponseStatusCodeSame(403);
    }

    public function testListExpensesReturnsOnlyGroupExpenses(): void
    {
        $emailA = 'list_a_'.uniqid().'@test.com';
        $emailB = 'list_b_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);

        $groupAId = $this->createGroup($tokenA);
        $groupBId = $this->createGroup($tokenB);

        $userAId = $this->getCurrentUserId($tokenA);
        $userBId = $this->getCurrentUserId($tokenB);

        $this->jsonRequest('POST', '/api/groups/'.$groupAId.'/expenses', [
            'description' => 'Depense groupe A',
            'montant' => 20.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userAId],
        ], $tokenA);

        $this->jsonRequest('POST', '/api/groups/'.$groupBId.'/expenses', [
            'description' => 'Depense groupe B',
            'montant' => 15.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userBId],
        ], $tokenB);

        $this->client->request('GET', '/api/groups/'.$groupAId.'/expenses', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenA,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(1, $body);
        self::assertSame('Depense groupe A', $body[0]['description']);
    }

    public function testShowExpenseIncludesBeneficiaireBreakdown(): void
    {
        $emailA = 'show_a_'.uniqid().'@test.com';
        $emailB = 'show_b_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);

        $groupId = $this->createGroup($tokenA);
        $userAId = $this->getCurrentUserId($tokenA);
        $userBId = $this->getCurrentUserId($tokenB);

        $this->addMemberToGroup($groupId, $userBId);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/expenses', [
            'description' => 'Show test',
            'montant' => 10.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userBId, $userAId],
        ], $tokenA);

        $createBody = json_decode($this->client->getResponse()->getContent(), true);
        $expenseId = $createBody['id'];

        $this->client->request('GET', '/api/expenses/'.$expenseId, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenA,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame($expenseId, $body['id']);
        self::assertArrayHasKey('beneficiaires', $body);
        self::assertCount(2, $body['beneficiaires']);

        foreach ($body['beneficiaires'] as $b) {
            self::assertArrayHasKey('montant_part', $b);
            self::assertArrayHasKey('id', $b);
            self::assertArrayHasKey('prenom', $b);
            self::assertArrayHasKey('nom', $b);
        }
    }

    public function testShowExpenseReturns403ForNonMember(): void
    {
        $emailOwner = 'so_owner_'.uniqid().'@test.com';
        $emailOther = 'so_other_'.uniqid().'@test.com';

        $tokenOwner = $this->createUserAndGetToken($emailOwner);
        $tokenOther = $this->createUserAndGetToken($emailOther);

        $groupId = $this->createGroup($tokenOwner);
        $ownerId = $this->getCurrentUserId($tokenOwner);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/expenses', [
            'description' => 'Private expense',
            'montant' => 5.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$ownerId],
        ], $tokenOwner);

        $expenseId = json_decode($this->client->getResponse()->getContent(), true)['id'];

        $this->client->request('GET', '/api/expenses/'.$expenseId, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenOther,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCreateExpenseCustomMode(): void
    {
        $emailA = 'cm_a_'.uniqid().'@test.com';
        $emailB = 'cm_b_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);

        $groupId = $this->createGroup($tokenA);
        $userAId = $this->getCurrentUserId($tokenA);
        $userBId = $this->getCurrentUserId($tokenB);

        $this->addMemberToGroup($groupId, $userBId);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/expenses', [
            'description' => 'Repas custom',
            'montant' => 30.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userAId, $userBId],
            'mode' => 'personnalisee',
            'parts' => [
                (string) $userAId => '20.00',
                (string) $userBId => '10.00',
            ],
        ], $tokenA);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('personnalisee', $body['type_repartition']);
        $byId = [];
        foreach ($body['beneficiaires'] as $b) {
            $byId[$b['id']] = $b['montant_part'];
        }
        self::assertSame('20.00', $byId[$userAId]);
        self::assertSame('10.00', $byId[$userBId]);
    }

    public function testCreateExpenseCustomModeWithMismatchReturns422(): void
    {
        $token = $this->createUserAndGetToken('cm_mis_'.uniqid().'@test.com');
        $groupId = $this->createGroup($token);
        $userId = $this->getCurrentUserId($token);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/expenses', [
            'description' => 'Bad custom',
            'montant' => 30.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userId],
            'mode' => 'personnalisee',
            'parts' => [(string) $userId => '25.00'],
        ], $token);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateExpensePercentageMode(): void
    {
        $emailA = 'pct_a_'.uniqid().'@test.com';
        $emailB = 'pct_b_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);

        $groupId = $this->createGroup($tokenA);
        $userAId = $this->getCurrentUserId($tokenA);
        $userBId = $this->getCurrentUserId($tokenB);

        $this->addMemberToGroup($groupId, $userBId);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/expenses', [
            'description' => 'Repas pct',
            'montant' => 100.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userAId, $userBId],
            'mode' => 'pourcentage',
            'parts' => [
                (string) $userAId => '60.00',
                (string) $userBId => '40.00',
            ],
        ], $tokenA);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('pourcentage', $body['type_repartition']);

        $byId = [];
        foreach ($body['beneficiaires'] as $b) {
            $byId[$b['id']] = $b;
        }
        self::assertSame('60.00', $byId[$userAId]['montant_part']);
        self::assertSame('40.00', $byId[$userBId]['montant_part']);
        self::assertSame('60.00', $byId[$userAId]['pourcentage']);
        self::assertSame('40.00', $byId[$userBId]['pourcentage']);
    }

    public function testCreateExpensePercentageSumNot100Returns422(): void
    {
        $emailA = 'pct_bad_'.uniqid().'@test.com';
        $emailB = 'pct_bad2_'.uniqid().'@test.com';

        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);

        $groupId = $this->createGroup($tokenA);
        $userAId = $this->getCurrentUserId($tokenA);
        $userBId = $this->getCurrentUserId($tokenB);

        $this->addMemberToGroup($groupId, $userBId);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/expenses', [
            'description' => 'Bad pct',
            'montant' => 100.00,
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userAId, $userBId],
            'mode' => 'pourcentage',
            'parts' => [
                (string) $userAId => '60.00',
                (string) $userBId => '30.00',
            ],
        ], $tokenA);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateExpenseUsesDateDepenseWhenProvided(): void
    {
        $token = $this->createUserAndGetToken('date_'.uniqid().'@test.com');
        $groupId = $this->createGroup($token);
        $userId = $this->getCurrentUserId($token);

        $this->jsonRequest('POST', '/api/groups/'.$groupId.'/expenses', [
            'description' => 'Depense datee',
            'montant' => 12.00,
            'date_depense' => '2026-03-15',
            'id_categorie' => $this->categorieId,
            'beneficiaire_ids' => [$userId],
        ], $token);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('2026-03-15', $body['date_depense']);
    }

    public function testScanTicketWithoutAuthReturns401(): void
    {
        $this->client->request('POST', '/api/expenses/scan-ticket');

        self::assertResponseStatusCodeSame(401);
    }

    public function testScanTicketWithWrongFileTypeReturns422(): void
    {
        $token = $this->createUserAndGetToken('scan_wrongtype_'.uniqid().'@test.com');

        $path = tempnam(sys_get_temp_dir(), 'ticket_txt_');
        self::assertIsString($path);
        file_put_contents($path, 'ceci nest pas une image');
        $upload = new UploadedFile($path, 'note.txt', 'text/plain', null, true);

        $this->client->request('POST', '/api/expenses/scan-ticket', files: ['ticket' => $upload], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(422);
        unlink($path);
    }

    public function testScanTicketWithValidFileAndMockedOcrReturns200(): void
    {
        $token = $this->createUserAndGetToken('scan_ok_'.uniqid().'@test.com');

        // Le client de test reboote le kernel entre 2 requêtes (comportement Symfony >= 5.3),
        // ce qui recrée le MockHttpClient : on désactive ce reboot pour garder le mock ci-dessous.
        $this->client->disableReboot();

        $ocrPayload = [
            'IsErroredOnProcessing' => false,
            'ParsedResults' => [
                ['ParsedText' => "CARREFOUR CITY\nTOTAL TTC   23.45\n15/07/2026\n"],
            ],
        ];
        // MockHttpClient remplace HttpClientInterface en environnement de test (config/services.yaml).
        self::getContainer()->get(MockHttpClient::class)->setResponseFactory([
            new MockResponse(json_encode($ocrPayload, \JSON_THROW_ON_ERROR)),
        ]);

        // 1x1 PNG valide : finfo doit reconnaitre le vrai contenu, pas l'extension.
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', true);
        $path = tempnam(sys_get_temp_dir(), 'ticket_png_');
        self::assertIsString($path);
        file_put_contents($path, $pngBytes);
        $upload = new UploadedFile($path, 'ticket.png', 'image/png', null, true);

        $this->client->request('POST', '/api/expenses/scan-ticket', files: ['ticket' => $upload], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('23.45', $body['montant']);
        self::assertSame('2026-07-15', $body['date']);
        self::assertSame('CARREFOUR CITY', $body['commercant']);
        self::assertArrayHasKey('texteBrut', $body);
        unlink($path);
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

        return $this->loginAndGetToken($email, 'SecurePass1');
    }

    private function loginAndGetToken(string $email, string $password): string
    {
        $this->jsonRequest('POST', '/api/login', [
            'email' => $email,
            'motDePasse' => $password,
        ], null);
        self::assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true)['token'];
    }

    private function createGroup(string $token): int
    {
        $this->jsonRequest('POST', '/api/groups', ['nom' => 'Groupe '.uniqid()], $token);
        self::assertResponseStatusCodeSame(201);

        return json_decode($this->client->getResponse()->getContent(), true)['id'];
    }

    private function getCurrentUserId(string $token): int
    {
        $this->client->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true)['id'];
    }

    private function addMemberToGroup(int $groupId, int $userId): void
    {
        $groupe = $this->em->getRepository(Groupe::class)->find($groupId);
        $user = $this->em->getRepository(Utilisateur::class)->find($userId);

        $appartenir = (new Appartenir())
            ->setUtilisateur($user)
            ->setGroupe($groupe)
            ->setRole(RoleAppartenir::Membre)
            ->setStatutInvitation(StatutInvitation::Acceptee)
            ->setDateAdhesion(new \DateTimeImmutable())
            ->setTokenInvitation(bin2hex(random_bytes(32)));

        $this->em->persist($appartenir);
        $this->em->flush();
    }

    private function jsonRequest(string $method, string $uri, array $payload, ?string $token): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
