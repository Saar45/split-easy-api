<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** @return Notification[] */
    public function listForUser(Utilisateur $user, ?bool $onlyUnread, int $limit): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.destinataire = :u')
            ->setParameter('u', $user)
            ->orderBy('n.dateCreation', 'DESC')
            ->setMaxResults($limit);

        if (true === $onlyUnread) {
            $qb->andWhere('n.estLu = :lu')->setParameter('lu', false);
        }

        return $qb->getQuery()->getResult();
    }

    public function countUnread(Utilisateur $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.destinataire = :u')
            ->andWhere('n.estLu = :lu')
            ->setParameter('u', $user)
            ->setParameter('lu', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllReadFor(Utilisateur $user): int
    {
        $now = new \DateTimeImmutable();

        return (int) $this->createQueryBuilder('n')
            ->update()
            ->set('n.estLu', ':lu')
            ->set('n.dateLecture', ':now')
            ->where('n.destinataire = :u')
            ->andWhere('n.estLu = :unread')
            ->setParameter('lu', true)
            ->setParameter('now', $now)
            ->setParameter('u', $user)
            ->setParameter('unread', false)
            ->getQuery()
            ->execute();
    }
}
