<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Categorie;
use App\Repository\CategorieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class CategoryController extends AbstractController
{
    public function __construct(private readonly CategorieRepository $categorieRepository)
    {
    }

    #[Route('/api/categories', name: 'api_categories_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $categories = $this->categorieRepository->findBy([], ['ordreAffichage' => 'ASC']);

        $payload = array_map(static fn (Categorie $c) => [
            'id' => $c->getId(),
            'libelle' => $c->getLibelle(),
            'icone' => $c->getIcone(),
            'couleur' => $c->getCouleur(),
            'ordre_affichage' => $c->getOrdreAffichage(),
        ], $categories);

        return $this->json($payload);
    }
}
