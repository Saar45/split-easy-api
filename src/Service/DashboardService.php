<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Depense;
use App\Entity\Utilisateur;
use App\Repository\AppartenirRepository;
use App\Repository\DepenseRepository;

/**
 * Agrège les données nécessaires à l'onglet Accueil (dashboard).
 *
 * Trois agrégations indépendantes :
 *   1. Solde net global (somme des balances de l'utilisateur dans chaque groupe).
 *   2. Dernières dépenses (3 max, triées date DESC puis id DESC).
 *   3. Invitations en attente non expirées.
 */
final class DashboardService
{
    public function __construct(
        private readonly DebtOptimizerService $debtOptimizer,
        private readonly AppartenirRepository $appartenirRepository,
        private readonly DepenseRepository $depenseRepository,
    ) {
    }

    /**
     * @return array{
     *   solde_net: string,
     *   total_du: string,
     *   total_a_recevoir: string,
     *   dernieres_depenses: list<array<string, mixed>>,
     *   invitations_en_attente: int,
     * }
     */
    public function buildForUser(Utilisateur $user): array
    {
        $groupes = $this->appartenirRepository->findAcceptedGroupsForUser($user);

        [$totalARecevoir, $totalDu] = $this->computeBalances($user, $groupes);

        $soldeNet = bcsub($totalARecevoir, $totalDu, 2);

        $depenses = $this->depenseRepository->findRecentForGroups($groupes, 3);

        $invitations = $this->appartenirRepository->countPendingInvitationsForUser($user);

        return [
            'solde_net' => $soldeNet,
            'total_du' => $totalDu,
            'total_a_recevoir' => $totalARecevoir,
            'dernieres_depenses' => array_map(fn (Depense $d) => $this->serializeDepense($d), $depenses),
            'invitations_en_attente' => $invitations,
        ];
    }

    /**
     * Parcourt chaque groupe et extrait la balance de l'utilisateur courant.
     *
     * @param \App\Entity\Groupe[] $groupes
     *
     * @return array{string, string} [totalARecevoir, totalDu]
     */
    private function computeBalances(Utilisateur $user, array $groupes): array
    {
        $totalARecevoir = '0.00';
        $totalDu = '0.00';

        foreach ($groupes as $groupe) {
            $result = $this->debtOptimizer->computeForGroup($groupe);

            foreach ($result['soldes'] as $entry) {
                if ($entry['user']['id'] !== $user->getId()) {
                    continue;
                }
                $balance = $entry['balance'];
                if (bccomp($balance, '0.00', 2) > 0) {
                    $totalARecevoir = bcadd($totalARecevoir, $balance, 2);
                } elseif (bccomp($balance, '0.00', 2) < 0) {
                    // On stocke la valeur absolue.
                    $totalDu = bcadd($totalDu, bcmul($balance, '-1', 2), 2);
                }
                break;
            }
        }

        return [$totalARecevoir, $totalDu];
    }

    /** @return array<string, mixed> */
    private function serializeDepense(Depense $d): array
    {
        return [
            'id' => $d->getId(),
            'description' => $d->getDescription(),
            'montant' => $d->getMontant(),
            'date_depense' => $d->getDateDepense()->format('Y-m-d'),
            'groupe' => [
                'id' => $d->getGroupe()->getId(),
                'nom' => $d->getGroupe()->getNom(),
            ],
            'payeur' => [
                'id' => $d->getPayeur()->getId(),
                'prenom' => $d->getPayeur()->getPrenom(),
            ],
        ];
    }
}
