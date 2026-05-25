<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateExpenseDto;
use App\Entity\Categorie;
use App\Entity\Depense;
use App\Entity\Groupe;
use App\Entity\Repartir;
use App\Entity\Utilisateur;
use App\Enum\StatutInvitation;
use App\Enum\TypeRepartition;
use App\Repository\AppartenirRepository;
use App\Repository\CategorieRepository;
use App\Repository\DepenseRepository;
use App\Repository\RepartirRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ExpenseService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DepenseRepository $depenseRepository,
        private readonly RepartirRepository $repartirRepository,
        private readonly AppartenirRepository $appartenirRepository,
        private readonly CategorieRepository $categorieRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly SplitCalculatorService $splitCalculator,
    ) {
    }

    public function createExpenseForGroup(Groupe $groupe, Utilisateur $payeur, CreateExpenseDto $dto): Depense
    {
        $categorie = $this->categorieRepository->find($dto->id_categorie);
        if ($categorie === null) {
            throw new UnprocessableEntityHttpException(sprintf('Catégorie %d introuvable.', $dto->id_categorie));
        }

        $acceptedMemberIds = $this->getAcceptedMemberIds($groupe);

        foreach ($dto->beneficiaire_ids as $benefId) {
            if (!in_array($benefId, $acceptedMemberIds, true)) {
                throw new UnprocessableEntityHttpException(
                    sprintf('L\'utilisateur %d n\'est pas membre accepté du groupe.', $benefId)
                );
            }
        }

        $dateDepense = $dto->date_depense !== null
            ? new \DateTimeImmutable($dto->date_depense)
            : new \DateTimeImmutable();

        $depense = (new Depense())
            ->setDescription($dto->description)
            ->setMontant(number_format($dto->montant, 2, '.', ''))
            ->setDateDepense($dateDepense)
            ->setCategorie($categorie)
            ->setPayeur($payeur)
            ->setGroupe($groupe)
            ->setTypeRepartition(TypeRepartition::Equitable);

        $this->em->persist($depense);

        // Positionner le payeur en dernier pour lui attribuer le surplus d'arrondi (§6.3.2)
        $benefIds = $dto->beneficiaire_ids;
        if (in_array($payeur->getId(), $benefIds, true)) {
            $benefIds = array_filter($benefIds, fn (int $id) => $id !== $payeur->getId());
            $benefIds = array_values($benefIds);
            $benefIds[] = $payeur->getId();
        }

        $parts = $this->splitCalculator->calculateEqual(number_format($dto->montant, 2, '.', ''), $benefIds);

        foreach ($parts as $userId => $montantPart) {
            $beneficiaire = $this->utilisateurRepository->find($userId);
            if ($beneficiaire === null) {
                continue;
            }

            $repartir = (new Repartir())
                ->setBeneficiaire($beneficiaire)
                ->setDepense($depense)
                ->setMontantPart($montantPart);

            $this->em->persist($repartir);
        }

        $this->em->flush();

        return $depense;
    }

    /** @return Depense[] */
    public function listExpensesForGroup(Groupe $groupe): array
    {
        return $this->depenseRepository->createQueryBuilder('d')
            ->where('d.groupe = :groupe')
            ->setParameter('groupe', $groupe)
            ->orderBy('d.dateDepense', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return array{depense: Depense, repartitions: Repartir[]} */
    public function getExpenseWithRepartition(Depense $depense): array
    {
        $repartitions = $this->repartirRepository->findBy(['depense' => $depense]);

        return [
            'depense' => $depense,
            'repartitions' => $repartitions,
        ];
    }

    /** @return int[] */
    private function getAcceptedMemberIds(Groupe $groupe): array
    {
        $appartenances = $this->appartenirRepository->findBy([
            'groupe' => $groupe,
            'statutInvitation' => StatutInvitation::Acceptee,
        ]);

        return array_map(
            fn ($a) => $a->getUtilisateur()->getId(),
            $appartenances
        );
    }
}
