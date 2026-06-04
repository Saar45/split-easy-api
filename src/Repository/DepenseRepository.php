<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Depense;
use App\Entity\Groupe;
use App\Entity\Utilisateur;
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
     * @param Groupe[] $groupes
     *
     * @return Depense[]
     */
    public function findRecentForGroups(array $groupes, int $limit = 3): array
    {
        if (0 === count($groupes)) {
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

    /**
     * Somme par catégorie des dépenses où l'utilisateur est payeur, restreintes
     * aux groupes donnés et à la fenêtre [start, end] inclusive.
     *
     * @param Groupe[] $groupes
     *
     * @return list<array{categorie_id: int, libelle: string, couleur: ?string, montant: string}>
     */
    public function sumByCategoryForPayer(
        Utilisateur $payeur,
        array $groupes,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): array {
        if (0 === count($groupes)) {
            return [];
        }

        $rows = $this->createQueryBuilder('d')
            ->select('c.id AS categorie_id', 'c.libelle AS libelle', 'c.couleur AS couleur', 'SUM(d.montant) AS montant')
            ->innerJoin('d.categorie', 'c')
            ->where('d.payeur = :payeur')
            ->andWhere('d.groupe IN (:groupes)')
            ->andWhere('d.dateDepense >= :start')
            ->andWhere('d.dateDepense <= :end')
            ->setParameter('payeur', $payeur)
            ->setParameter('groupes', $groupes)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('c.id', 'c.libelle', 'c.couleur')
            ->orderBy('montant', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $r): array {
            return [
                'categorie_id' => (int) $r['categorie_id'],
                'libelle' => (string) $r['libelle'],
                'couleur' => null !== $r['couleur'] ? (string) $r['couleur'] : null,
                'montant' => null !== $r['montant'] ? (string) $r['montant'] : '0.00',
            ];
        }, $rows);
    }

    /**
     * Renvoie (date, montant) pour chaque dépense où l'utilisateur est payeur dans
     * les groupes donnés et la fenêtre [start, end]. L'agrégation par jour/mois est
     * faite dans le Service, en PHP, pour rester portable entre SGBD.
     *
     * @param Groupe[] $groupes
     *
     * @return list<array{date: \DateTimeInterface, montant: string}>
     */
    public function findRawAmountsForPayer(
        Utilisateur $payeur,
        array $groupes,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): array {
        if (0 === count($groupes)) {
            return [];
        }

        $rows = $this->createQueryBuilder('d')
            ->select('d.dateDepense AS date', 'd.montant AS montant')
            ->where('d.payeur = :payeur')
            ->andWhere('d.groupe IN (:groupes)')
            ->andWhere('d.dateDepense >= :start')
            ->andWhere('d.dateDepense <= :end')
            ->setParameter('payeur', $payeur)
            ->setParameter('groupes', $groupes)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $r): array {
            /** @var \DateTimeInterface $date */
            $date = $r['date'];

            return [
                'date' => $date,
                'montant' => (string) $r['montant'],
            ];
        }, $rows);
    }
}
