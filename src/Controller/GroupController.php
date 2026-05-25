<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateGroupDto;
use App\Entity\Groupe;
use App\Entity\Utilisateur;
use App\Security\Voter\GroupVoter;
use App\Service\GroupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/groups', name: 'api_groups_')]
final class GroupController extends AbstractController
{
    public function __construct(private readonly GroupService $groupService)
    {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateGroupDto $dto,
        #[CurrentUser] Utilisateur $user,
    ): JsonResponse {
        $groupe = $this->groupService->createGroupForUser($dto, $user);

        return $this->json($this->serialize($groupe), Response::HTTP_CREATED);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        $groupes = $this->groupService->listGroupsForUser($user);

        return $this->json(array_map(fn (Groupe $g) => $this->serialize($g), $groupes));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Groupe $groupe): JsonResponse
    {
        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $groupe);

        return $this->json($this->serialize($groupe));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Groupe $groupe): JsonResponse
    {
        $this->denyAccessUnlessGranted(GroupVoter::DELETE, $groupe);
        $this->groupService->deleteGroup($groupe);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed> */
    private function serialize(Groupe $groupe): array
    {
        return [
            'id' => $groupe->getId(),
            'nom' => $groupe->getNom(),
            'description' => $groupe->getDescription(),
            'couleur' => $groupe->getCouleur(),
            'statut' => $groupe->getStatut()->value,
            'date_creation' => $groupe->getDateCreation()->format(\DateTimeInterface::ATOM),
        ];
    }
}
