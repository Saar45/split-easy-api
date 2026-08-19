<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateExpenseDto;
use App\Dto\UpdateExpenseDto;
use App\Entity\Depense;
use App\Entity\Groupe;
use App\Entity\Repartir;
use App\Entity\Utilisateur;
use App\Enum\StatutInvitation;
use App\Enum\StatutRemboursement;
use App\Enum\TypeNotification;
use App\Enum\TypeRepartition;
use App\Repository\AppartenirRepository;
use App\Repository\CategorieRepository;
use App\Repository\DepenseRepository;
use App\Repository\RemboursementRepository;
use App\Repository\RepartirRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ExpenseService
{
    private const EDIT_WINDOW_HOURS = 24;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DepenseRepository $depenseRepository,
        private readonly RepartirRepository $repartirRepository,
        private readonly AppartenirRepository $appartenirRepository,
        private readonly CategorieRepository $categorieRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly RemboursementRepository $remboursementRepository,
        private readonly SplitCalculatorService $splitCalculator,
        private readonly NotificationService $notifications,
    ) {
    }

    public function createExpenseForGroup(Groupe $groupe, Utilisateur $payeur, CreateExpenseDto $dto): Depense
    {
        if (null === $dto->id_categorie || null === $dto->montant || null === $dto->beneficiaire_ids) {
            throw new UnprocessableEntityHttpException('Champs obligatoires manquants.');
        }

        $categorie = $this->categorieRepository->find($dto->id_categorie);
        if (null === $categorie) {
            throw new UnprocessableEntityHttpException(sprintf('Catégorie %d introuvable.', $dto->id_categorie));
        }

        $acceptedMemberIds = $this->getAcceptedMemberIds($groupe);

        foreach ($dto->beneficiaire_ids as $benefId) {
            if (!in_array($benefId, $acceptedMemberIds, true)) {
                throw new UnprocessableEntityHttpException(sprintf('L\'utilisateur %d n\'est pas membre accepté du groupe.', $benefId));
            }
        }

        $type = $dto->getTypeRepartition();
        $dateDepense = null !== $dto->date_depense
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
            ->setTypeRepartition($type);

        $this->em->persist($depense);

        // Calcule les parts selon le mode et conserve les pourcentages éventuels.
        [$parts, $pourcentages] = $this->computeParts($type, $montantString, $payeur, $dto->beneficiaire_ids, $dto->parts);

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

            if (isset($pourcentages[$userId])) {
                $repartir->setPourcentage($pourcentages[$userId]);
            }

            $this->em->persist($repartir);
        }

        $this->em->flush();

        $this->notifyGroupMembers($groupe, $payeur, $depense->getId(), $dto->description, $montantString);

        return $depense;
    }

    public function updateExpense(Depense $depense, UpdateExpenseDto $dto): Depense
    {
        $this->assertWithinEditWindow($depense, 'modifiée');
        $this->assertNoValidatedRemboursement($depense->getGroupe(), 'modifiée');

        if (null === $dto->id_categorie || null === $dto->montant || null === $dto->beneficiaire_ids) {
            throw new UnprocessableEntityHttpException('Champs obligatoires manquants.');
        }

        $categorie = $this->categorieRepository->find($dto->id_categorie);
        if (null === $categorie) {
            throw new UnprocessableEntityHttpException(sprintf('Catégorie %d introuvable.', $dto->id_categorie));
        }

        $groupe = $depense->getGroupe();
        $payeur = $depense->getPayeur();
        $acceptedMemberIds = $this->getAcceptedMemberIds($groupe);

        foreach ($dto->beneficiaire_ids as $benefId) {
            if (!in_array($benefId, $acceptedMemberIds, true)) {
                throw new UnprocessableEntityHttpException(sprintf('L\'utilisateur %d n\'est pas membre accepté du groupe.', $benefId));
            }
        }

        $type = $dto->getTypeRepartition();
        $dateDepense = null !== $dto->date_depense
            ? new \DateTimeImmutable($dto->date_depense)
            : $depense->getDateDepense();

        $montantString = number_format($dto->montant, 2, '.', '');

        // Recalcule les parts avant de toucher à l'entité, pour ne pas laisser
        // la dépense dans un état incohérent si le calcul échoue (§F4).
        [$parts, $pourcentages] = $this->computeParts($type, $montantString, $payeur, $dto->beneficiaire_ids, $dto->parts);

        $beneficiaires = $this->utilisateurRepository->findBy(['id' => array_keys($parts)]);
        $byId = [];
        foreach ($beneficiaires as $b) {
            $byId[$b->getId()] = $b;
        }

        // Réutilise les lignes existantes (clé composite beneficiaire+depense) plutot que
        // de les recréer, pour éviter une collision d'identité dans l'UnitOfWork entre
        // l'ancienne instance retirée et la nouvelle instance persistée au même flush.
        $existantParBeneficiaire = [];
        foreach ($this->repartirRepository->findBy(['depense' => $depense]) as $ancienneRepartition) {
            $existantParBeneficiaire[$ancienneRepartition->getBeneficiaire()->getId()] = $ancienneRepartition;
        }

        foreach ($existantParBeneficiaire as $beneficiaireId => $ancienneRepartition) {
            if (!isset($parts[$beneficiaireId])) {
                $this->em->remove($ancienneRepartition);
            }
        }

        foreach ($parts as $userId => $montantPart) {
            if (!isset($byId[$userId])) {
                throw new UnprocessableEntityHttpException(sprintf('Bénéficiaire %d introuvable.', $userId));
            }

            $repartir = $existantParBeneficiaire[$userId]
                ?? (new Repartir())->setBeneficiaire($byId[$userId])->setDepense($depense);

            $repartir->setMontantPart($montantPart);
            $repartir->setPourcentage($pourcentages[$userId] ?? null);

            if (!isset($existantParBeneficiaire[$userId])) {
                $this->em->persist($repartir);
            }
        }

        $depense
            ->setDescription($dto->description)
            ->setMontant($montantString)
            ->setDateDepense($dateDepense)
            ->setCategorie($categorie)
            ->setTypeRepartition($type)
            ->setDateModification(new \DateTimeImmutable());

        $this->em->flush();

        return $depense;
    }

    public function deleteExpense(Depense $depense): void
    {
        $this->assertWithinEditWindow($depense, 'supprimée');
        $this->assertNoValidatedRemboursement($depense->getGroupe(), 'supprimée');

        foreach ($this->repartirRepository->findBy(['depense' => $depense]) as $repartition) {
            $this->em->remove($repartition);
        }

        $this->em->remove($depense);
        $this->em->flush();
    }

    private function assertWithinEditWindow(Depense $depense, string $action): void
    {
        $limite = \DateTimeImmutable::createFromInterface($depense->getDateCreation())
            ->modify(sprintf('+%d hours', self::EDIT_WINDOW_HOURS));
        if (new \DateTimeImmutable() > $limite) {
            throw new ConflictHttpException(sprintf('La dépense ne peut plus être %s passé un délai de %d heures après sa création.', $action, self::EDIT_WINDOW_HOURS));
        }
    }

    private function assertNoValidatedRemboursement(Groupe $groupe, string $action): void
    {
        // Un remboursement valide fige les soldes qui ont servi à son calcul
        // (DebtOptimizerService::computeForGroup) : modifier ou supprimer une
        // dépense après coup romprait cette intégrité.
        $existant = $this->remboursementRepository->findOneBy([
            'groupe' => $groupe,
            'statut' => StatutRemboursement::Valide,
        ]);

        if (null !== $existant) {
            throw new ConflictHttpException(sprintf('Des remboursements ont déjà été validés pour ce groupe, la dépense ne peut plus être %s.', $action));
        }
    }

    private function notifyGroupMembers(Groupe $groupe, Utilisateur $payeur, ?int $depenseId, string $description, string $montant): void
    {
        if (null === $depenseId) {
            return;
        }

        $acceptedIds = $this->getAcceptedMemberIds($groupe);
        $otherIds = array_values(array_filter($acceptedIds, fn (int $id) => $id !== $payeur->getId()));
        if ([] === $otherIds) {
            return;
        }

        $others = $this->utilisateurRepository->findBy(['id' => $otherIds]);
        foreach ($others as $member) {
            $this->notifications->create(
                $member,
                TypeNotification::DepenseAjoutee,
                'Nouvelle dépense',
                sprintf('%s %s a ajouté « %s » (%s €) dans le groupe « %s ».', $payeur->getPrenom(), $payeur->getNom(), $description, $montant, $groupe->getNom()),
                'depense',
                $depenseId,
            );
        }
    }

    /**
     * @param int[]|null                    $beneficiaireIds
     * @param array<int|string, mixed>|null $parts
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function computeParts(TypeRepartition $type, string $montant, Utilisateur $payeur, ?array $beneficiaireIds, ?array $parts): array
    {
        $beneficiaireIds ??= [];

        if (TypeRepartition::Equitable === $type) {
            // Payeur en dernier pour lui attribuer le surplus d'arrondi (§6.3.2).
            $benefIds = array_values(array_filter($beneficiaireIds, fn (int $id) => $id !== $payeur->getId()));
            if (in_array($payeur->getId(), $beneficiaireIds, true)) {
                $benefIds[] = $payeur->getId();
            }

            return [$this->splitCalculator->calculateEqual($montant, $benefIds), []];
        }

        if (null === $parts || 0 === count($parts)) {
            throw new UnprocessableEntityHttpException('Le champ parts est obligatoire pour ce mode de répartition.');
        }

        $normalized = $this->normalizeParts($parts, $beneficiaireIds);

        if (TypeRepartition::Personnalisee === $type) {
            return [$this->splitCalculator->calculateCustom($montant, $normalized), []];
        }

        // Pourcentage : payeur en dernière clé pour recevoir le surplus d'arrondi (§6.3.2).
        $ordered = $this->reorderPayerLast($normalized, $payeur->getId());

        return [$this->splitCalculator->calculatePercentages($montant, $ordered), $ordered];
    }

    /**
     * @param array<int, string> $normalized
     *
     * @return array<int, string>
     */
    private function reorderPayerLast(array $normalized, int $payeurId): array
    {
        if (!array_key_exists($payeurId, $normalized)) {
            return $normalized;
        }

        $payeurPart = $normalized[$payeurId];
        unset($normalized[$payeurId]);
        $normalized[$payeurId] = $payeurPart;

        return $normalized;
    }

    /**
     * Valide et normalise le map { user_id: valeur } en s'assurant qu'il couvre exactement la liste des bénéficiaires.
     *
     * @param array<int|string, mixed> $rawParts
     * @param int[]                    $beneficiaireIds
     *
     * @return array<int, string>
     */
    private function normalizeParts(array $rawParts, array $beneficiaireIds): array
    {
        $normalized = [];
        // Accepte uniquement un entier ou une chaîne décimale avec 1 ou 2 décimales,
        // pour éviter la notation scientifique ("1e2") et les conversions float floues.
        $decimalPattern = '/^\d+(\.\d{1,2})?$/';
        foreach ($rawParts as $userId => $value) {
            $intUserId = (int) $userId;
            if ($intUserId <= 0) {
                throw new UnprocessableEntityHttpException('Les identifiants de parts doivent être des entiers positifs.');
            }
            if (!is_string($value) && !is_int($value)) {
                throw new UnprocessableEntityHttpException(sprintf('La valeur de la part du bénéficiaire %d doit être un nombre décimal.', $intUserId));
            }
            $stringValue = is_int($value) ? (string) $value : trim($value);
            if (!preg_match($decimalPattern, $stringValue)) {
                throw new UnprocessableEntityHttpException(sprintf('La valeur "%s" du bénéficiaire %d doit être un décimal positif avec 2 décimales max.', $stringValue, $intUserId));
            }
            // bcadd avec scale=2 normalise la chaîne sans float (ex: "5" -> "5.00").
            $normalized[$intUserId] = bcadd($stringValue, '0', 2);
        }

        sort($beneficiaireIds);
        $partKeys = array_keys($normalized);
        sort($partKeys);

        if ($partKeys !== $beneficiaireIds) {
            throw new UnprocessableEntityHttpException('Les parts doivent couvrir exactement la liste des bénéficiaires.');
        }

        return $normalized;
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
