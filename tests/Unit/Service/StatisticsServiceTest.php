<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Groupe;
use App\Entity\Utilisateur;
use App\Enum\PeriodeStatistique;
use App\Repository\AppartenirRepository;
use App\Repository\DepenseRepository;
use App\Repository\GroupeRepository;
use App\Service\StatisticsService;
use PHPUnit\Framework\TestCase;

/**
 * Couvre la logique pure du service : bornes de période, distribution des
 * pourcentages (somme = 100 %), granularité d'évolution. Les Repository sont
 * stubés pour isoler le calcul.
 */
final class StatisticsServiceTest extends TestCase
{
    public function testMoisPeriodBoundariesCoverCurrentCalendarMonth(): void
    {
        $service = $this->buildServiceWithEmptyData();

        $result = $service->compute(new Utilisateur(), PeriodeStatistique::Mois);

        $today = new \DateTimeImmutable('today');
        $expectedStart = $today->modify('first day of this month')->format('Y-m-d');
        $expectedEnd = $today->modify('last day of this month')->format('Y-m-d');

        self::assertSame($expectedStart, $result['date_debut']);
        self::assertSame($expectedEnd, $result['date_fin']);
        self::assertSame('mois', $result['periode']);
    }

    public function testSemaineEvolutionReturnsSevenDailyPoints(): void
    {
        $service = $this->buildServiceWithEmptyData();

        $result = $service->compute(new Utilisateur(), PeriodeStatistique::Semaine);

        self::assertCount(7, $result['evolution']);
        // Tous les points doivent être à 0.00 sans dépense.
        foreach ($result['evolution'] as $p) {
            self::assertSame('0.00', $p['montant']);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $p['date']);
        }
    }

    public function testAnneeEvolutionReturnsTwelveMonthlyPoints(): void
    {
        $service = $this->buildServiceWithEmptyData();

        $result = $service->compute(new Utilisateur(), PeriodeStatistique::Annee);

        self::assertCount(12, $result['evolution']);
        // Chaque point d'évolution annuelle doit être le 1er du mois.
        foreach ($result['evolution'] as $p) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-01$/', $p['date']);
        }
    }

    public function testParCategoriePourcentagesSumToOneHundred(): void
    {
        $categorieRows = [
            ['categorie_id' => 1, 'libelle' => 'Courses',    'couleur' => '#4CAF50', 'montant' => '33.33'],
            ['categorie_id' => 2, 'libelle' => 'Restaurant', 'couleur' => '#FF9800', 'montant' => '33.33'],
            ['categorie_id' => 3, 'libelle' => 'Transport',  'couleur' => '#2196F3', 'montant' => '33.34'],
        ];

        $service = $this->buildService($categorieRows, []);
        $result = $service->compute(new Utilisateur(), PeriodeStatistique::Mois);

        $somme = '0.00';
        foreach ($result['par_categorie'] as $row) {
            $somme = bcadd($somme, $row['pourcentage'], 2);
        }
        self::assertSame('100.00', $somme);
        self::assertSame('100.00', $result['total_depense']);
    }

    public function testCategoriePrincipaleIsHighestRow(): void
    {
        $categorieRows = [
            ['categorie_id' => 1, 'libelle' => 'Courses',    'couleur' => '#4CAF50', 'montant' => '60.00'],
            ['categorie_id' => 2, 'libelle' => 'Restaurant', 'couleur' => '#FF9800', 'montant' => '40.00'],
        ];

        $service = $this->buildService($categorieRows, []);
        $result = $service->compute(new Utilisateur(), PeriodeStatistique::Mois);

        self::assertNotNull($result['categorie_principale']);
        self::assertSame(1, $result['categorie_principale']['id']);
        self::assertSame('Courses', $result['categorie_principale']['nom']);
        self::assertSame('60.00', $result['categorie_principale']['montant']);
    }

    public function testZeroStateReturnsEmptyCategoryListAndZeroTotal(): void
    {
        $service = $this->buildServiceWithEmptyData();

        $result = $service->compute(new Utilisateur(), PeriodeStatistique::Mois);

        self::assertSame('0.00', $result['total_depense']);
        self::assertSame('0.00', $result['moyenne_par_jour']);
        self::assertNull($result['categorie_principale']);
        self::assertSame([], $result['par_categorie']);
    }

    public function testEvolutionBucketsDailyAmounts(): void
    {
        $today = new \DateTimeImmutable('today');
        $rawAmounts = [
            ['date' => $today->modify('-2 days'), 'montant' => '10.00'],
            ['date' => $today->modify('-2 days'), 'montant' => '5.50'],
            ['date' => $today,                    'montant' => '7.20'],
        ];

        $categorieRows = [
            ['categorie_id' => 1, 'libelle' => 'Courses', 'couleur' => '#4CAF50', 'montant' => '22.70'],
        ];

        $service = $this->buildService($categorieRows, $rawAmounts);
        $result = $service->compute(new Utilisateur(), PeriodeStatistique::Semaine);

        self::assertCount(7, $result['evolution']);

        $byDate = [];
        foreach ($result['evolution'] as $p) {
            $byDate[$p['date']] = $p['montant'];
        }
        self::assertSame('15.50', $byDate[$today->modify('-2 days')->format('Y-m-d')]);
        self::assertSame('7.20', $byDate[$today->format('Y-m-d')]);
    }

    /**
     * @param list<array{categorie_id: int, libelle: string, couleur: ?string, montant: string}> $categorieRows
     * @param list<array{date: \DateTimeInterface, montant: string}>                             $rawAmounts
     */
    private function buildService(array $categorieRows, array $rawAmounts): StatisticsService
    {
        $depenseRepo = $this->createMock(DepenseRepository::class);
        $depenseRepo->method('sumByCategoryForPayer')->willReturn($categorieRows);
        $depenseRepo->method('findRawAmountsForPayer')->willReturn($rawAmounts);

        $appartenirRepo = $this->createMock(AppartenirRepository::class);
        $appartenirRepo->method('findAcceptedGroupsForUser')->willReturn([new Groupe()]);

        $groupeRepo = $this->createMock(GroupeRepository::class);

        return new StatisticsService($depenseRepo, $appartenirRepo, $groupeRepo);
    }

    private function buildServiceWithEmptyData(): StatisticsService
    {
        return $this->buildService([], []);
    }
}
