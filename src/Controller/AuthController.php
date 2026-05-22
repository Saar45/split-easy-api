<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegisterDto;
use App\Exception\EmailAlreadyTakenException;
use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class AuthController extends AbstractController
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterDto $dto): JsonResponse
    {
        try {
            $user = $this->authService->registerUser($dto);
        } catch (EmailAlreadyTakenException) {
            // Message générique — éviter d'aider à l'énumération de comptes.
            return $this->json(['error' => 'Inscription impossible avec ces informations.'], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
        ], Response::HTTP_CREATED);
    }

    // Route placeholder — le firewall json_login intercepte avant exécution.
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        throw new \LogicException('JSON login firewall intercepts this route.');
    }
}
