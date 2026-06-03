<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Depense;
use App\Entity\Groupe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Depense>
 */
class DepenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Depense::class);
    }

    /**
     * Renvoie les N dépenses les plus récentes parmi les groupes donnés.
     * Eager-fetch groupe et payeur pour éviter les N+1 queries à la sérialisation.
     *
     * @param  Groupe[] $groupes
     * @return Depense[]
     */
    public function findRecentForGroups(array $groupes, int $limit = 3): array
    {
        if (count($groupes) === 0) {
            return [];
        }

        return $this->createQueryBuilder('d')
            ->addSelect('g', 'p')
            ->innerJoin('d.groupe', 'g')
            ->innerJoin('d.payeur', 'p')
            ->where('d.groupe IN (:groupes)')
            ->setParameter('groupes', $groupes)
            ->orderBy('d.dateDepense', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
