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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StatisticsControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $categorieCoursesId;
    private int $categorieRestoId;

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

        $courses = $this->em->getRepository(Categorie::class)->findOneBy(['libelle' => 'Courses']);
        if (null === $courses) {
            $courses = (new Categorie())->setLibelle('Courses')->setIcone('cart')->setCouleur('#4CAF50')->setOrdreAffichage(1);
            $this->em->persist($courses);
        }
        $resto = $this->em->getRepository(Categorie::class)->findOneBy(['libelle' => 'Restaurant']);
        if (null === $resto) {
            $resto = (new Categorie())->setLibelle('Restaurant')->setIcone('food')->setCouleur('#FF9800')->setOrdreAffichage(2);
            $this->em->persist($resto);
        }
        $this->em->flush();

        $this->categorieCoursesId = $courses->getId();
        $this->categorieRestoId = $resto->getId();
    }

    private function rrmdir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $dir.'/'.$item;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testUnauthenticatedReturns401(): void
    {
        $this->client->request('GET', '/api/stats?period=mois');
        self::assertResponseStatusCodeSame(401);
    }

    public function testZeroStateReturnsValidShape(): void
    {
        $token = $this->createUserAndGetToken('stats_zero_'.uniqid().'@test.com');

        $this->client->request('GET', '/api/stats?period=mois', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('mois', $body['periode']);
        self::assertSame('0.00', $body['total_depense']);
        self::assertSame('0.00', $body['moyenne_par_jour']);
        self::assertNull($body['categorie_principale']);
        self::assertSame([], $body['par_categorie']);
        self::assertIsArray($body['evolution']);
        self::assertNotEmpty($body['evolution']);
    }

    public function testMoisAggregatesAcrossCategories(): void
    {
        $email = 'stats_mois_'.uniqid().'@test.com';
        $token = $this->createUserAndGetToken($email);
        $userId = $this->getCurrentUserId($token);

        $groupId = $this->createGroup($token);

        $groupe = $this->em->getRepository(Groupe::class)->find($groupId);
        $user = $this->em->getRepository(Utilisateur::class)->find($userId);
        $courses = $this->em->getRepository(Categorie::class)->find($this->categorieCoursesId);
        $resto = $this->em->getRepository(Categorie::class)->find($this->categorieRestoId);

        $monthStart = (new \DateTimeImmutable('today'))->modify('first day of this month');

        // 3 Courses + 2 Restaurant ce mois-ci, payés par l'utilisateur.
        $this->createDepense($groupe, $user, $courses, '20.00', $monthStart);
        $this->createDepense($groupe, $user, $courses, '15.00', $monthStart->modify('+1 day'));
        $this->createDepense($groupe, $user, $courses, '10.00', $monthStart->modify('+2 days'));
        $this->createDepense($groupe, $user, $resto, '30.00', $monthStart->modify('+3 days'));
        $this->createDepense($groupe, $user, $resto, '20.00', $monthStart->modify('+4 days'));
        $this->em->flush();

        $this->client->request('GET', '/api/stats?period=mois', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('95.00', $body['total_depense']);
        self::assertCount(2, $body['par_categorie']);

        // Catégorie principale doit être Restaurant (50.00 > 45.00).
        self::assertNotNull($body['categorie_principale']);
        self::assertSame('Restaurant', $body['categorie_principale']['nom']);
        self::assertSame('50.00', $body['categorie_principale']['montant']);

        // Somme des pourcentages = 100.00.
        $sum = '0.00';
        foreach ($body['par_categorie'] as $row) {
            $sum = bcadd($sum, $row['pourcentage'], 2);
        }
        self::assertSame('100.00', $sum);
    }

    public function testSemaineEvolutionReturnsSevenPoints(): void
    {
        $token = $this->createUserAndGetToken('stats_sem_'.uniqid().'@test.com');

        $this->client->request('GET', '/api/stats?period=semaine', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('semaine', $body['periode']);
        self::assertCount(7, $body['evolution']);
    }

    public function testAnneeEvolutionReturnsTwelveMonthlyPoints(): void
    {
        $token = $this->createUserAndGetToken('stats_an_'.uniqid().'@test.com');

        $this->client->request('GET', '/api/stats?period=annee', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('annee', $body['periode']);
        self::assertCount(12, $body['evolution']);
        foreach ($body['evolution'] as $p) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-01$/', $p['date']);
        }
    }

    public function testInvalidPeriodReturns400(): void
    {
        $token = $this->createUserAndGetToken('stats_bad_'.uniqid().'@test.com');

        $this->client->request('GET', '/api/stats?period=decennie', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testGroupIdScopesStatsToSingleGroup(): void
    {
        $email = 'stats_grp_'.uniqid().'@test.com';
        $token = $this->createUserAndGetToken($email);
        $userId = $this->getCurrentUserId($token);

        $group1Id = $this->createGroup($token);
        $group2Id = $this->createGroup($token);

        $groupe1 = $this->em->getRepository(Groupe::class)->find($group1Id);
        $groupe2 = $this->em->getRepository(Groupe::class)->find($group2Id);
        $user = $this->em->getRepository(Utilisateur::class)->find($userId);
        $courses = $this->em->getRepository(Categorie::class)->find($this->categorieCoursesId);

        $monthStart = (new \DateTimeImmutable('today'))->modify('first day of this month');
        $this->createDepense($groupe1, $user, $courses, '40.00', $monthStart);
        $this->createDepense($groupe2, $user, $courses, '100.00', $monthStart);
        $this->em->flush();

        // Stats limitées au groupe 1.
        $this->client->request('GET', '/api/stats?period=mois&group_id='.$group1Id, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('40.00', $body['total_depense']);
    }

    public function testGroupIdNonMemberReturns403(): void
    {
        $emailA = 'stats_a_'.uniqid().'@test.com';
        $emailB = 'stats_b_'.uniqid().'@test.com';
        $tokenA = $this->createUserAndGetToken($emailA);
        $tokenB = $this->createUserAndGetToken($emailB);

        $groupBId = $this->createGroup($tokenB);

        $this->client->request('GET', '/api/stats?period=mois&group_id='.$groupBId, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenA,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCategoriesEndpointReturnsList(): void
    {
        $token = $this->createUserAndGetToken('cat_list_'.uniqid().'@test.com');

        $this->client->request('GET', '/api/categories', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertIsArray($body);
        self::assertGreaterThanOrEqual(2, count($body));
        self::assertArrayHasKey('id', $body[0]);
        self::assertArrayHasKey('libelle', $body[0]);
        self::assertArrayHasKey('couleur', $body[0]);
    }

    private function createDepense(
        Groupe $groupe,
        Utilisateur $payeur,
        Categorie $categorie,
        string $montant,
        \DateTimeImmutable $date,
    ): void {
        $d = (new Depense())
            ->setDescription('Test '.uniqid())
            ->setMontant($montant)
            ->setDateDepense($date)
            ->setCategorie($categorie)
            ->setPayeur($payeur)
            ->setGroupe($groupe);
        $this->em->persist($d);

        $part = (new Repartir())
            ->setDepense($d)
            ->setBeneficiaire($payeur)
            ->setMontantPart($montant);
        $this->em->persist($part);
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
        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true)['id'];
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
