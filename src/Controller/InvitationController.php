<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateInvitationDto;
use App\Entity\Appartenir;
use App\Entity\Groupe;
use App\Entity\Utilisateur;
use App\Security\Voter\GroupVoter;
use App\Security\Voter\InvitationVoter;
use App\Service\InvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class InvitationController extends AbstractController
{
    public function __construct(
        private readonly InvitationService $invitationService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/groups/{groupId}/invitations', name: 'api_invitations_create', methods: ['POST'], requirements: ['groupId' => '\d+'])]
    public function create(
        int $groupId,
        #[MapRequestPayload] CreateInvitationDto $dto,
        #[CurrentUser] Utilisateur $user,
    ): JsonResponse {
        $groupe = $this->em->getRepository(Groupe::class)->find($groupId);
        if (null === $groupe) {
            throw new NotFoundHttpException(sprintf('Groupe %d introuvable.', $groupId));
        }

        $this->denyAccessUnlessGranted(InvitationVoter::CREATE, $groupe);

        $appartenir = $this->invitationService->createInvitation($groupe, (string) $dto->email, $user);

        return $this->json($this->serialize($appartenir), Response::HTTP_CREATED);
    }

    #[Route('/api/invitations/{token}/accept', name: 'api_invitations_accept', methods: ['POST'], requirements: ['token' => '[a-f0-9]+'])]
    public function accept(string $token, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $appartenir = $this->loadByToken($token);
        $this->denyAccessUnlessGranted(InvitationVoter::ACCEPT, $appartenir);

        $updated = $this->invitationService->acceptInvitation($token, $user);

        return $this->json($this->serialize($updated));
    }

    #[Route('/api/invitations/{token}/refuse', name: 'api_invitations_refuse', methods: ['POST'], requirements: ['token' => '[a-f0-9]+'])]
    public function refuse(string $token, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $appartenir = $this->loadByToken($token);
        $this->denyAccessUnlessGranted(InvitationVoter::REFUSE, $appartenir);

        $updated = $this->invitationService->refuseInvitation($token, $user);

        return $this->json($this->serialize($updated));
    }

    #[Route('/api/invitations/me', name: 'api_invitations_me', methods: ['GET'])]
    public function listMine(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        $list = $this->invitationService->listPendingForUser($user);

        return $this->json(array_map(fn (Appartenir $a) => $this->serialize($a), $list));
    }

    #[Route('/api/groups/{groupId}/members', name: 'api_groups_members', methods: ['GET'], requirements: ['groupId' => '\d+'])]
    public function members(int $groupId): JsonResponse
    {
        $groupe = $this->em->getRepository(Groupe::class)->find($groupId);
        if (null === $groupe) {
            throw new NotFoundHttpException(sprintf('Groupe %d introuvable.', $groupId));
        }

        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $groupe);

        $list = $this->invitationService->listForGroup($groupe);

        return $this->json(array_map(fn (Appartenir $a) => [
            'id' => $a->getUtilisateur()->getId(),
            'prenom' => $a->getUtilisateur()->getPrenom(),
            'nom' => $a->getUtilisateur()->getNom(),
            'email' => $a->getUtilisateur()->getEmail(),
            'role' => $a->getRole()->value,
            'statut_invitation' => $a->getStatutInvitation()->value,
            'date_adhesion' => $a->getDateAdhesion()?->format(\DateTimeInterface::ATOM),
        ], $list));
    }

    private function loadByToken(string $token): Appartenir
    {
        $appartenir = $this->em->getRepository(Appartenir::class)->findOneBy(['tokenInvitation' => $token]);
        if (null === $appartenir) {
            throw new NotFoundHttpException('Invitation introuvable.');
        }

        return $appartenir;
    }

    /** @return array<string, mixed> */
    private function serialize(Appartenir $a): array
    {
        return [
            'token' => $a->getTokenInvitation(),
            'statut_invitation' => $a->getStatutInvitation()->value,
            'role' => $a->getRole()->value,
            'date_invitation' => $a->getDateInvitation()->format(\DateTimeInterface::ATOM),
            'date_expiration' => $a->getDateExpiration()?->format(\DateTimeInterface::ATOM),
            'date_acceptation' => $a->getDateAcceptation()?->format(\DateTimeInterface::ATOM),
            'date_adhesion' => $a->getDateAdhesion()?->format(\DateTimeInterface::ATOM),
            'groupe' => [
                'id' => $a->getGroupe()->getId(),
                'nom' => $a->getGroupe()->getNom(),
                'couleur' => $a->getGroupe()->getCouleur(),
            ],
            'utilisateur' => [
                'id' => $a->getUtilisateur()->getId(),
                'prenom' => $a->getUtilisateur()->getPrenom(),
                'nom' => $a->getUtilisateur()->getNom(),
                'email' => $a->getUtilisateur()->getEmail(),
            ],
        ];
    }
}
