<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class SplitCalculatorService
{
    /**
     * Calcule la répartition équitable d'un montant entre plusieurs bénéficiaires.
     *
     * Le surplus d'arrondi (centimes résiduels) est attribué au payeur (§6.3.2).
     * Exemple : 10 € / 3 = 3.33 + 3.33 + 3.34 où le dernier ID dans la liste
     * est le payeur s'il est présent, sinon le surplus va au premier bénéficiaire
     * de la liste. Le service ne connaît pas le payeur directement : l'appelant
     * doit passer le payeur en dernier dans $beneficiaireIds pour respecter la
     * règle §6.3.2. ExpenseService positionne le payeur en dernière position.
     *
     * @param string $totalMontant    Montant total (ex: "30.00")
     * @param int[]  $beneficiaireIds Tableau d'IDs utilisateurs (non vide)
     * @return array<int, string>     Map [id => part_arrondie]
     */
    public function calculateEqual(string $totalMontant, array $beneficiaireIds): array
    {
        $count = count($beneficiaireIds);
        if ($count === 0) {
            return [];
        }

        $total = $totalMontant;
        $parts = [];

        $basePart = bcdiv($total, (string) $count, 2);
        $sumParts = bcmul($basePart, (string) $count, 2);
        $surplus = bcsub($total, $sumParts, 2);

        foreach ($beneficiaireIds as $id) {
            $parts[$id] = $basePart;
        }

        // Attribuer le surplus d'arrondi au dernier ID (le payeur selon §6.3.2)
        $lastId = end($beneficiaireIds);
        $parts[$lastId] = bcadd($parts[$lastId], $surplus, 2);

        return $parts;
    }

    /**
     * Calcule la répartition personnalisée : montants exacts par bénéficiaire.
     *
     * Valide que la somme des montants individuels = montant total, et que
     * tous les montants sont strictement positifs (§F4, §6.3.2).
     *
     * @param string             $totalMontant Montant total (ex: "30.00")
     * @param array<int, string> $amounts      Map [id => montant_exact]
     * @return array<int, string>              Map [id => part]
     */
    public function calculateCustom(string $totalMontant, array $amounts): array
    {
        if (count($amounts) === 0) {
            throw new UnprocessableEntityHttpException('Aucun montant fourni pour la répartition personnalisée.');
        }

        $sum = '0.00';
        $parts = [];
        foreach ($amounts as $id => $montant) {
            if (bccomp($montant, '0.00', 2) <= 0) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Le montant du bénéficiaire %d doit être strictement positif.', $id)
                );
            }
            $parts[$id] = bcadd('0.00', $montant, 2);
            $sum = bcadd($sum, $parts[$id], 2);
        }

        if (bccomp($sum, $totalMontant, 2) !== 0) {
            throw new UnprocessableEntityHttpException(
                sprintf('La somme des montants personnalisés (%s) doit être égale au montant total (%s).', $sum, $totalMontant)
            );
        }

        return $parts;
    }

    /**
     * Calcule la répartition par pourcentage.
     *
     * Valide que la somme des pourcentages = 100, et que tous les
     * pourcentages sont strictement positifs (§F4, §6.3.2). Le surplus
     * d'arrondi est attribué au dernier ID (payeur).
     *
     * @param string             $totalMontant Montant total (ex: "100.00")
     * @param array<int, string> $percentages  Map [id => pourcentage]
     * @return array<int, string>              Map [id => part_arrondie]
     */
    public function calculatePercentages(string $totalMontant, array $percentages): array
    {
        if (count($percentages) === 0) {
            throw new UnprocessableEntityHttpException('Aucun pourcentage fourni pour la répartition par pourcentage.');
        }

        $sumPct = '0.00';
        foreach ($percentages as $id => $pct) {
            if (bccomp($pct, '0.00', 2) <= 0) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Le pourcentage du bénéficiaire %d doit être strictement positif.', $id)
                );
            }
            $sumPct = bcadd($sumPct, $pct, 2);
        }

        if (bccomp($sumPct, '100.00', 2) !== 0) {
            throw new UnprocessableEntityHttpException(
                sprintf('La somme des pourcentages (%s) doit être égale à 100.', $sumPct)
            );
        }

        $parts = [];
        $sumParts = '0.00';
        foreach ($percentages as $id => $pct) {
            $part = bcdiv(bcmul($totalMontant, $pct, 4), '100', 2);
            $parts[$id] = $part;
            $sumParts = bcadd($sumParts, $part, 2);
        }

        // Surplus d'arrondi au dernier ID (le payeur selon §6.3.2)
        $surplus = bcsub($totalMontant, $sumParts, 2);
        if (bccomp($surplus, '0.00', 2) !== 0) {
            $lastId = array_key_last($parts);
            $parts[$lastId] = bcadd($parts[$lastId], $surplus, 2);
        }

        return $parts;
    }
}
