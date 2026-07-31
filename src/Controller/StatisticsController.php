<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\PeriodeStatistique;
use App\Service\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class StatisticsController extends AbstractController
{
    public function __construct(private readonly StatisticsService $statisticsService)
    {
    }

    #[Route('/api/stats', name: 'api_stats', methods: ['GET'])]
    public function __invoke(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $periodeParam = (string) $request->query->get('period', PeriodeStatistique::Mois->value);
        $periode = PeriodeStatistique::tryFrom($periodeParam);
        if (null === $periode) {
            return $this->json([
                'error' => sprintf('Période invalide : "%s". Valeurs autorisées : semaine, mois, annee.', $periodeParam),
            ], 400);
        }

        $groupIdParam = $request->query->get('group_id');
        $groupId = null;
        if (null !== $groupIdParam && '' !== $groupIdParam) {
            if (!ctype_digit((string) $groupIdParam)) {
                return $this->json(['error' => 'group_id doit être un entier positif.'], 400);
            }
            $groupId = (int) $groupIdParam;
        }

        return $this->json($this->statisticsService->compute($user, $periode, $groupId));
    }
}
