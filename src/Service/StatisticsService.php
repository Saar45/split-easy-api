<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Groupe;
use App\Entity\Utilisateur;
use App\Enum\PeriodeStatistique;
use App\Enum\StatutInvitation;
use App\Repository\AppartenirRepository;
use App\Repository\DepenseRepository;
use App\Repository\GroupeRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Agrège les dépenses de l'utilisateur courant pour l'onglet Statistiques (F8).
 *
 * Périmètre : on agrège uniquement les dépenses où l'utilisateur est payeur,
 * dans les groupes dont il est membre accepté (ou un groupe précis si fourni).
 *
 * Trois granularités d'évolution selon la période :
 *  - semaine : un point par jour sur les 7 derniers jours (inclus aujourd'hui).
 *  - mois    : un point par jour sur le mois calendaire courant.
 *  - annee   : un point par mois sur les 12 derniers mois.
 */
final class StatisticsService
{
    public function __construct(
        private readonly DepenseRepository $depenseRepository,
        private readonly AppartenirRepository $appartenirRepository,
        private readonly GroupeRepository $groupeRepository,
    ) {
    }

    /**
     * @return array{
     *   periode: string,
     *   date_debut: string,
     *   date_fin: string,
     *   total_depense: string,
     *   moyenne_par_jour: string,
     *   categorie_principale: ?array{id: int, nom: string, couleur: ?string, montant: string},
     *   par_categorie: list<array{id: int, nom: string, couleur: ?string, montant: string, pourcentage: string}>,
     *   evolution: list<array{date: string, montant: string}>,
     * }
     */
    public function compute(Utilisateur $user, PeriodeStatistique $periode, ?int $groupId = null): array
    {
        [$start, $end] = $this->resolveRange($periode);

        $groupes = $this->resolveGroupes($user, $groupId);

        $categorieRows = $this->depenseRepository->sumByCategoryForPayer($user, $groupes, $start, $end);
        $rawAmounts = $this->depenseRepository->findRawAmountsForPayer($user, $groupes, $start, $end);

        $totalDepense = $this->sumDecimal(array_map(static fn (array $r) => $r['montant'], $categorieRows));

        $parCategorie = $this->buildParCategorie($categorieRows, $totalDepense);
        $categoriePrincipale = [] === $parCategorie ? null : [
            'id' => $parCategorie[0]['id'],
            'nom' => $parCategorie[0]['nom'],
            'couleur' => $parCategorie[0]['couleur'],
            'montant' => $parCategorie[0]['montant'],
        ];

        $evolution = $this->buildEvolution($periode, $start, $end, $rawAmounts);

        $nbJours = $this->countDays($start, $end);
        $moyenneParJour = $nbJours > 0
            ? bcdiv($totalDepense, (string) $nbJours, 2)
            : '0.00';

        return [
            'periode' => $periode->value,
            'date_debut' => $start->format('Y-m-d'),
            'date_fin' => $end->format('Y-m-d'),
            'total_depense' => $totalDepense,
            'moyenne_par_jour' => $moyenneParJour,
            'categorie_principale' => $categoriePrincipale,
            'par_categorie' => $parCategorie,
            'evolution' => $evolution,
        ];
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function resolveRange(PeriodeStatistique $periode): array
    {
        $today = new \DateTimeImmutable('today');

        return match ($periode) {
            PeriodeStatistique::Semaine => [
                $today->modify('-6 days'),
                $today,
            ],
            PeriodeStatistique::Mois => [
                $today->modify('first day of this month'),
                $today->modify('last day of this month'),
            ],
            PeriodeStatistique::Annee => [
                $today->modify('first day of -11 months'),
                $today->modify('last day of this month'),
            ],
        };
    }

    /**
     * @return Groupe[]
     */
    private function resolveGroupes(Utilisateur $user, ?int $groupId): array
    {
        if (null === $groupId) {
            return $this->appartenirRepository->findAcceptedGroupsForUser($user);
        }

        $groupe = $this->groupeRepository->find($groupId);
        if (null === $groupe) {
            throw new NotFoundHttpException(sprintf('Groupe %d introuvable.', $groupId));
        }
        $appartenir = $this->appartenirRepository->findOneBy([
            'groupe' => $groupe,
            'utilisateur' => $user,
            'statutInvitation' => StatutInvitation::Acceptee,
        ]);
        if (null === $appartenir) {
            throw new AccessDeniedHttpException('Non membre du groupe.');
        }

        return [$groupe];
    }

    /**
     * @param list<array{categorie_id: int, libelle: string, couleur: ?string, montant: string}> $rows
     *
     * @return list<array{id: int, nom: string, couleur: ?string, montant: string, pourcentage: string}>
     */
    private function buildParCategorie(array $rows, string $total): array
    {
        if ([] === $rows || 0 === bccomp($total, '0.00', 2)) {
            return [];
        }

        $out = [];
        $pourcentageCumule = '0.00';
        $count = count($rows);
        foreach ($rows as $i => $row) {
            if ($i === $count - 1) {
                // Dernier élément : on attribue le reste pour garantir somme = 100 %.
                $pourcentage = bcsub('100.00', $pourcentageCumule, 2);
            } else {
                $pourcentage = bcmul(bcdiv($row['montant'], $total, 6), '100', 2);
                $pourcentageCumule = bcadd($pourcentageCumule, $pourcentage, 2);
            }
            $out[] = [
                'id' => $row['categorie_id'],
                'nom' => $row['libelle'],
                'couleur' => $row['couleur'],
                'montant' => bcadd($row['montant'], '0', 2),
                'pourcentage' => $pourcentage,
            ];
        }

        return $out;
    }

    /**
     * @param list<array{date: \DateTimeInterface, montant: string}> $rawAmounts
     *
     * @return list<array{date: string, montant: string}>
     */
    private function buildEvolution(
        PeriodeStatistique $periode,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        array $rawAmounts,
    ): array {
        if (PeriodeStatistique::Annee === $periode) {
            return $this->buildMonthlyEvolution($start, $end, $rawAmounts);
        }

        return $this->buildDailyEvolution($start, $end, $rawAmounts);
    }

    /**
     * @param list<array{date: \DateTimeInterface, montant: string}> $rawAmounts
     *
     * @return list<array{date: string, montant: string}>
     */
    private function buildDailyEvolution(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        array $rawAmounts,
    ): array {
        $buckets = [];
        foreach ($rawAmounts as $r) {
            $key = $r['date']->format('Y-m-d');
            $buckets[$key] = bcadd($buckets[$key] ?? '0.00', $r['montant'], 2);
        }

        $out = [];
        for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $out[] = [
                'date' => $key,
                'montant' => $buckets[$key] ?? '0.00',
            ];
        }

        return $out;
    }

    /**
     * @param list<array{date: \DateTimeInterface, montant: string}> $rawAmounts
     *
     * @return list<array{date: string, montant: string}>
     */
    private function buildMonthlyEvolution(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        array $rawAmounts,
    ): array {
        $buckets = [];
        foreach ($rawAmounts as $r) {
            $key = $r['date']->format('Y-m');
            $buckets[$key] = bcadd($buckets[$key] ?? '0.00', $r['montant'], 2);
        }

        $out = [];
        $cursor = $start->modify('first day of this month');
        $endMonth = $end->modify('first day of this month');
        while ($cursor <= $endMonth) {
            $key = $cursor->format('Y-m');
            $out[] = [
                'date' => $cursor->format('Y-m-01'),
                'montant' => $buckets[$key] ?? '0.00',
            ];
            $cursor = $cursor->modify('+1 month');
        }

        return $out;
    }

    private function countDays(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) $start->diff($end)->days + 1;
    }

    /**
     * @param list<string> $amounts
     */
    private function sumDecimal(array $amounts): string
    {
        $total = '0.00';
        foreach ($amounts as $a) {
            $total = bcadd($total, $a, 2);
        }

        return $total;
    }
}
