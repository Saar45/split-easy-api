<?php

declare(strict_types=1);

namespace App\Service;

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
}
