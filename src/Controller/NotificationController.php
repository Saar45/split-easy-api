<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Utilisateur;
use App\Security\Voter\NotificationVoter;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class NotificationController extends AbstractController
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    #[Route('/api/notifications', name: 'api_notifications_list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $onlyUnread = $this->parseUnread($request->query->get('unread'));
        $limit = (int) $request->query->get('limit', 50);

        $list = $this->notifications->listForUser($user, $onlyUnread, $limit);

        return $this->json(array_map(fn (Notification $n) => $this->serialize($n), $list));
    }

    #[Route('/api/notifications/unread-count', name: 'api_notifications_unread_count', methods: ['GET'])]
    public function unreadCount(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        return $this->json(['count' => $this->notifications->countUnread($user)]);
    }

    #[Route('/api/notifications/{id}/read', name: 'api_notifications_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function read(Notification $notification): JsonResponse
    {
        $this->denyAccessUnlessGranted(NotificationVoter::READ, $notification);
        $this->notifications->markAsRead($notification);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/notifications/read-all', name: 'api_notifications_read_all', methods: ['POST'])]
    public function readAll(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        $updated = $this->notifications->markAllAsRead($user);

        return $this->json(['updated' => $updated]);
    }

    private function parseUnread(?string $raw): ?bool
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return in_array(strtolower($raw), ['1', 'true', 'yes'], true);
    }

    /** @return array<string, mixed> */
    private function serialize(Notification $n): array
    {
        return [
            'id' => $n->getId(),
            'type' => $n->getTypeNotification()->value,
            'titre' => $n->getTitre(),
            'message' => $n->getMessage(),
            'lue' => $n->isEstLu(),
            'date_creation' => $n->getDateCreation()->format(\DateTimeInterface::ATOM),
            'date_lecture' => $n->getDateLecture()?->format(\DateTimeInterface::ATOM),
            'reference_type' => $n->getReferenceType(),
            'reference_id' => $n->getReferenceId(),
        ];
    }
}
