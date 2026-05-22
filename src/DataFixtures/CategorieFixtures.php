<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Categorie;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Catégories par défaut (dossier §6.5.4 — script SQL d'initialisation).
 * Insérées en production via cette fixture pour respecter l'extensibilité de la table CATEGORIE.
 */
class CategorieFixtures extends Fixture
{
    private const CATEGORIES = [
        ['Courses',    'shopping-cart',   '#4CAF50', 1],
        ['Restaurant', 'utensils',        '#FF9800', 2],
        ['Transport',  'car',             '#2196F3', 3],
        ['Loyer',      'home',            '#9C27B0', 4],
        ['Factures',   'file-text',       '#F44336', 5],
        ['Loisirs',    'gamepad',         '#00BCD4', 6],
        ['Sante',      'heart',           '#E91E63', 7],
        ['Autre',      'more-horizontal', '#607D8B', 8],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::CATEGORIES as [$libelle, $icone, $couleur, $ordre]) {
            $categorie = (new Categorie())
                ->setLibelle($libelle)
                ->setIcone($icone)
                ->setCouleur($couleur)
                ->setOrdreAffichage($ordre);

            $manager->persist($categorie);
        }

        $manager->flush();
    }
}
