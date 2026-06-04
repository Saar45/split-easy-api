<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateRemboursementDto;
use App\Entity\Groupe;
use App\Entity\Remboursement;
use App\Entity\Utilisateur;
use App\Enum\StatutInvitation;
use App\Enum\StatutRemboursement;
use App\Enum\TypeNotification;
use App\Repository\AppartenirRepository;
use App\Repository\RemboursementRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * F6 - Service de remboursement bipartite (point de défense jury n°2).
 *
 * Machine à états :
 *   PROPOSE   (état initial à la création par le débiteur)
 *     -> VALIDE  (acceptation par le créancier)
 *     -> CONTESTE (rejet par le créancier)
 *     -> ANNULE  (annulation par le débiteur)
 *
 * Les transitions invalides sont rejetées en 409 Conflict ; les actions non
 * autorisées par rôle (débiteur vs créancier) sont rejetées en amont par le
 * RemboursementVoter.
 */
final class ReimbursementService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RemboursementRepository $remboursementRepository,
        private readonly AppartenirRepository $appartenirRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly NotificationService $notifications,
    ) {
    }

    public function propose(Groupe $groupe, Utilisateur $debiteur, CreateRemboursementDto $dto): Remboursement
    {
        if (null === $dto->id_crediteur || null === $dto->montant) {
            throw new UnprocessableEntityHttpException('Champs obligatoires manquants.');
        }
        if ($dto->id_crediteur === $debiteur->getId()) {
            throw new UnprocessableEntityHttpException('Un débiteur ne peut pas se rembourser lui-même.');
        }

        $crediteur = $this->utilisateurRepository->find($dto->id_crediteur);
        if (null === $crediteur) {
            throw new UnprocessableEntityHttpException(sprintf('Créancier %d introuvable.', $dto->id_crediteur));
        }

        $acceptedIds = $this->getAcceptedMemberIds($groupe);
        if (!in_array($debiteur->getId(), $acceptedIds, true)) {
            throw new UnprocessableEntityHttpException('Le débiteur n\'est pas membre accepté du groupe.');
        }
        if (!in_array($crediteur->getId(), $acceptedIds, true)) {
            throw new UnprocessableEntityHttpException('Le créancier n\'est pas membre accepté du groupe.');
        }

        $montant = number_format($dto->montant, 2, '.', '');

        $rb = (new Remboursement())
            ->setGroupe($groupe)
            ->setDebiteur($debiteur)
            ->setCrediteur($crediteur)
            ->setMontant($montant)
            ->setStatut(StatutRemboursement::Propose)
            ->setDateProposition(new \DateTimeImmutable());

        $this->em->persist($rb);
        $this->em->flush();

        $this->notifications->create(
            $crediteur,
            TypeNotification::RemboursementPropose,
            'Remboursement proposé',
            sprintf('%s %s vous propose un remboursement de %s €.', $debiteur->getPrenom(), $debiteur->getNom(), $montant),
            'remboursement',
            $rb->getId(),
        );

        return $rb;
    }

    public function accept(Remboursement $rb): Remboursement
    {
        $this->ensureCurrentStatus($rb, StatutRemboursement::Propose, 'accepter');

        $rb->setStatut(StatutRemboursement::Valide);
        $rb->setDateValidation(new \DateTimeImmutable());
        $this->em->flush();

        $this->notifications->create(
            $rb->getDebiteur(),
            TypeNotification::RemboursementAccepte,
            'Remboursement accepté',
            sprintf('%s %s a accepté votre remboursement de %s €.', $rb->getCrediteur()->getPrenom(), $rb->getCrediteur()->getNom(), $rb->getMontant()),
            'remboursement',
            $rb->getId(),
        );

        return $rb;
    }

    public function reject(Remboursement $rb): Remboursement
    {
        $this->ensureCurrentStatus($rb, StatutRemboursement::Propose, 'rejeter');

        // date_validation ne reflète qu'une validation positive : on ne la
        // renseigne pas sur un rejet pour éviter l'ambiguïté côté API.
        $rb->setStatut(StatutRemboursement::Conteste);
        $this->em->flush();

        $this->notifications->create(
            $rb->getDebiteur(),
            TypeNotification::RemboursementRejete,
            'Remboursement contesté',
            sprintf('%s %s a contesté votre remboursement de %s €.', $rb->getCrediteur()->getPrenom(), $rb->getCrediteur()->getNom(), $rb->getMontant()),
            'remboursement',
            $rb->getId(),
        );

        return $rb;
    }

    public function cancel(Remboursement $rb): Remboursement
    {
        $this->ensureCurrentStatus($rb, StatutRemboursement::Propose, 'annuler');

        $rb->setStatut(StatutRemboursement::Annule);
        $this->em->flush();

        $this->notifications->create(
            $rb->getCrediteur(),
            TypeNotification::RemboursementAnnule,
            'Remboursement annulé',
            sprintf('%s %s a annulé sa proposition de remboursement de %s €.', $rb->getDebiteur()->getPrenom(), $rb->getDebiteur()->getNom(), $rb->getMontant()),
            'remboursement',
            $rb->getId(),
        );

        return $rb;
    }

    /** @return Remboursement[] */
    public function listForUser(Utilisateur $user): array
    {
        return $this->remboursementRepository->createQueryBuilder('r')
            ->where('r.debiteur = :u OR r.crediteur = :u')
            ->setParameter('u', $user)
            ->orderBy('r.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function ensureCurrentStatus(Remboursement $rb, StatutRemboursement $expected, string $action): void
    {
        if ($rb->getStatut() !== $expected) {
            throw new ConflictHttpException(sprintf('Impossible d\'%s un remboursement dans l\'état %s (attendu : %s).', $action, $rb->getStatut()->value, $expected->value));
        }
    }

    /** @return int[] */
    private function getAcceptedMemberIds(Groupe $groupe): array
    {
        $appartenances = $this->appartenirRepository->findBy([
            'groupe' => $groupe,
            'statutInvitation' => StatutInvitation::Acceptee,
        ]);

        return array_map(fn ($a) => $a->getUtilisateur()->getId(), $appartenances);
    }
}
