<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateRemboursementDto;
use App\Entity\Groupe;
use App\Entity\Remboursement;
use App\Entity\Utilisateur;
use App\Security\Voter\GroupVoter;
use App\Security\Voter\RemboursementVoter;
use App\Service\ReimbursementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class RemboursementController extends AbstractController
{
    public function __construct(
        private readonly ReimbursementService $reimbursementService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/groups/{groupId}/remboursements', name: 'api_remboursements_create', methods: ['POST'], requirements: ['groupId' => '\d+'])]
    public function propose(
        int $groupId,
        #[MapRequestPayload] CreateRemboursementDto $dto,
        #[CurrentUser] Utilisateur $user,
    ): JsonResponse {
        $groupe = $this->em->getRepository(Groupe::class)->find($groupId);
        if (null === $groupe) {
            return $this->json(['error' => sprintf('Groupe %d introuvable.', $groupId)], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $groupe);

        $rb = $this->reimbursementService->propose($groupe, $user, $dto);

        return $this->json($this->serialize($rb), Response::HTTP_CREATED);
    }

    #[Route('/api/remboursements/{id}/accept', name: 'api_remboursements_accept', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function accept(Remboursement $rb): JsonResponse
    {
        $this->denyAccessUnlessGranted(RemboursementVoter::ACCEPT, $rb);
        $updated = $this->reimbursementService->accept($rb);

        return $this->json($this->serialize($updated));
    }

    #[Route('/api/remboursements/{id}/reject', name: 'api_remboursements_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reject(Remboursement $rb): JsonResponse
    {
        $this->denyAccessUnlessGranted(RemboursementVoter::REJECT, $rb);
        $updated = $this->reimbursementService->reject($rb);

        return $this->json($this->serialize($updated));
    }

    #[Route('/api/remboursements/{id}/cancel', name: 'api_remboursements_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(Remboursement $rb): JsonResponse
    {
        $this->denyAccessUnlessGranted(RemboursementVoter::CANCEL, $rb);
        $updated = $this->reimbursementService->cancel($rb);

        return $this->json($this->serialize($updated));
    }

    #[Route('/api/remboursements', name: 'api_remboursements_list', methods: ['GET'])]
    public function list(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        $list = $this->reimbursementService->listForUser($user);

        return $this->json(array_map(fn (Remboursement $r) => $this->serialize($r), $list));
    }

    #[Route('/api/remboursements/{id}', name: 'api_remboursements_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Remboursement $rb): JsonResponse
    {
        $this->denyAccessUnlessGranted(RemboursementVoter::VIEW, $rb);

        return $this->json($this->serialize($rb));
    }

    /** @return array<string, mixed> */
    private function serialize(Remboursement $rb): array
    {
        return [
            'id' => $rb->getId(),
            'groupe_id' => $rb->getGroupe()->getId(),
            'montant' => $rb->getMontant(),
            'statut' => $rb->getStatut()->value,
            'date_creation' => $rb->getDateCreation()->format(\DateTimeInterface::ATOM),
            'date_proposition' => $rb->getDateProposition()?->format(\DateTimeInterface::ATOM),
            'date_validation' => $rb->getDateValidation()?->format(\DateTimeInterface::ATOM),
            'debiteur' => [
                'id' => $rb->getDebiteur()->getId(),
                'prenom' => $rb->getDebiteur()->getPrenom(),
                'nom' => $rb->getDebiteur()->getNom(),
            ],
            'crediteur' => [
                'id' => $rb->getCrediteur()->getId(),
                'prenom' => $rb->getCrediteur()->getPrenom(),
                'nom' => $rb->getCrediteur()->getNom(),
            ],
        ];
    }
}
