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
        if ($dto->id_categorie === null || $dto->montant === null || $dto->beneficiaire_ids === null) {
            throw new UnprocessableEntityHttpException('Champs obligatoires manquants.');
        }

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

        $montantString = number_format($dto->montant, 2, '.', '');

        $depense = (new Depense())
            ->setDescription($dto->description)
            ->setMontant($montantString)
            ->setDateDepense($dateDepense)
            ->setCategorie($categorie)
            ->setPayeur($payeur)
            ->setGroupe($groupe)
            ->setTypeRepartition(TypeRepartition::Equitable);

        $this->em->persist($depense);

        // Payeur en dernier pour lui attribuer le surplus d'arrondi (§6.3.2).
        $benefIds = array_values(array_filter($dto->beneficiaire_ids, fn (int $id) => $id !== $payeur->getId()));
        if (in_array($payeur->getId(), $dto->beneficiaire_ids, true)) {
            $benefIds[] = $payeur->getId();
        }

        $parts = $this->splitCalculator->calculateEqual($montantString, $benefIds);

        // Batch load des bénéficiaires pour éviter N+1.
        $beneficiaires = $this->utilisateurRepository->findBy(['id' => array_keys($parts)]);
        $byId = [];
        foreach ($beneficiaires as $b) {
            $byId[$b->getId()] = $b;
        }

        foreach ($parts as $userId => $montantPart) {
            if (!isset($byId[$userId])) {
                throw new UnprocessableEntityHttpException(sprintf('Bénéficiaire %d introuvable.', $userId));
            }

            $repartir = (new Repartir())
                ->setBeneficiaire($byId[$userId])
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
