<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api', name: 'api_')]
final class UserController extends AbstractController
{
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(#[CurrentUser] ?Utilisateur $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'roles' => $user->getRoles(),
        ]);
    }
}
