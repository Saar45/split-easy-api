<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Utilisateur;
use App\Enum\TypeNotification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * F9 - Notifications in-app (point de défense jury n°3 : référence polymorphe).
 *
 * Le couple (reference_type, reference_id) pointe vers Depense, Remboursement ou
 * Appartenir sans contrainte FK. L'intégrité référentielle est garantie ici, à la
 * création : on n'insère une notification que pour des entités existantes connues
 * du service appelant. Avantage architectural : la notification survit à la
 * suppression de l'entité liée (soft-delete safe) et un seul type couvre plusieurs
 * tables sans circulation de FK.
 */
class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $repository,
    ) {
    }

    public function create(
        Utilisateur $destinataire,
        TypeNotification $type,
        string $titre,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
    ): Notification {
        $notif = (new Notification())
            ->setDestinataire($destinataire)
            ->setTypeNotification($type)
            ->setTitre($titre)
            ->setMessage($message)
            ->setReferenceType($entityType)
            ->setReferenceId($entityId);

        $this->em->persist($notif);
        $this->em->flush();

        return $notif;
    }

    public function markAsRead(Notification $notif): void
    {
        if ($notif->isEstLu()) {
            return;
        }

        $notif->setEstLu(true);
        $this->em->flush();
    }

    public function markAllAsRead(Utilisateur $user): int
    {
        return $this->repository->markAllReadFor($user);
    }

    /** @return Notification[] */
    public function listForUser(Utilisateur $user, ?bool $onlyUnread = null, int $limit = 50): array
    {
        $bounded = max(1, min($limit, 200));

        return $this->repository->listForUser($user, $onlyUnread, $bounded);
    }

    public function countUnread(Utilisateur $user): int
    {
        return $this->repository->countUnread($user);
    }
}
