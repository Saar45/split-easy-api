<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Appartenir;
use App\Entity\Groupe;
use App\Entity\Utilisateur;
use App\Enum\StatutInvitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appartenir>
 */
class AppartenirRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appartenir::class);
    }

    /**
     * Renvoie tous les groupes dont l'utilisateur est membre accepté.
     *
     * @return Groupe[]
     */
    public function findAcceptedGroupsForUser(Utilisateur $user): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a', 'g')
            ->innerJoin('a.groupe', 'g')
            ->where('a.utilisateur = :user')
            ->andWhere('a.statutInvitation = :accepted')
            ->setParameter('user', $user)
            ->setParameter('accepted', StatutInvitation::Acceptee)
            ->getQuery()
            ->getResult();

        return array_map(fn (Appartenir $a) => $a->getGroupe(), $rows);
    }

    /**
     * Compte les invitations en attente non expirées pour un utilisateur.
     * dateExpiration IS NULL = invitation sans expiration (cas créateur), donc exclue.
     */
    public function countPendingInvitationsForUser(Utilisateur $user): int
    {
        $now = new \DateTimeImmutable();

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.utilisateur)')
            ->where('a.utilisateur = :user')
            ->andWhere('a.statutInvitation = :pending')
            ->andWhere('a.dateExpiration IS NOT NULL')
            ->andWhere('a.dateExpiration > :now')
            ->setParameter('user', $user)
            ->setParameter('pending', StatutInvitation::EnAttente)
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
