<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Exception\ActiveGroupOwnershipException;
use App\Service\AccountDeletionService;
use App\Service\PreferencesService;
use App\Service\UserDataExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * RGPD endpoints: article 15 (data export) and article 17 (erasure).
 * Also exposes notification preferences (F9 opt-in).
 */
#[Route('/api/users/me', name: 'api_users_me_')]
final class UserMeController extends AbstractController
{
    public function __construct(
        private readonly UserDataExportService $exportService,
        private readonly AccountDeletionService $deletionService,
        private readonly PreferencesService $preferencesService,
    ) {
    }

    /**
     * GET /api/users/me/data — RGPD article 15: right of access.
     * Returns full personal data as a downloadable JSON attachment.
     */
    #[Route('/data', name: 'data', methods: ['GET'])]
    public function exportData(#[CurrentUser] Utilisateur $user): Response
    {
        $payload = $this->exportService->export($user);

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return new Response(
            $json,
            Response::HTTP_OK,
            [
                'Content-Type'        => 'application/json',
                'Content-Disposition' => 'attachment; filename="mes-donnees-spliteasy.json"',
            ],
        );
    }

    /**
     * DELETE /api/users/me — RGPD article 17: right to erasure.
     * Hard delete with CASCADE. Returns 409 if the user owns active groups with other members.
     * Optional header X-Confirm-Delete: true is accepted but not required.
     */
    #[Route('', name: 'delete', methods: ['DELETE'])]
    public function deleteAccount(#[CurrentUser] Utilisateur $user): Response
    {
        try {
            $this->deletionService->delete($user);
        } catch (ActiveGroupOwnershipException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * GET /api/users/me/preferences — returns notification preferences.
     * Auto-creates the row with defaults on first call.
     */
    #[Route('/preferences', name: 'preferences_get', methods: ['GET'])]
    public function getPreferences(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        $prefs = $this->preferencesService->getOrCreate($user);

        return $this->json([
            'notifications_email' => $prefs->isNotificationsEmail(),
            'notifications_push'  => $prefs->isNotificationsPush(),
        ]);
    }

    /**
     * PUT /api/users/me/preferences — updates one or both notification toggles.
     * Body: { "notifications_email": bool, "notifications_push": bool }.
     */
    #[Route('/preferences', name: 'preferences_put', methods: ['PUT'])]
    public function putPreferences(#[CurrentUser] Utilisateur $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $prefs = $this->preferencesService->update($user, $data);

        return $this->json([
            'notifications_email' => $prefs->isNotificationsEmail(),
            'notifications_push'  => $prefs->isNotificationsPush(),
        ]);
    }
}
