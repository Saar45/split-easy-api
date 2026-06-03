<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PreferencesUtilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreferencesUtilisateur>
 */
class PreferencesUtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreferencesUtilisateur::class);
    }
}
