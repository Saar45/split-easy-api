<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateExpenseDto;
use App\Entity\Depense;
use App\Entity\Groupe;
use App\Entity\Repartir;
use App\Entity\Utilisateur;
use App\Security\Voter\ExpenseVoter;
use App\Security\Voter\GroupVoter;
use App\Service\ExpenseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

// TODO: PUT /api/expenses/{id} (update, deferred to later PR)
// TODO: DELETE /api/expenses/{id} (delete, deferred to later PR)

final class ExpenseController extends AbstractController
{
    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/groups/{groupId}/expenses', name: 'api_expenses_create', methods: ['POST'], requirements: ['groupId' => '\d+'])]
    public function create(
        int $groupId,
        #[MapRequestPayload] CreateExpenseDto $dto,
        #[CurrentUser] Utilisateur $user,
    ): JsonResponse {
        $groupe = $this->em->getRepository(Groupe::class)->find($groupId);
        if ($groupe === null) {
            return $this->json(['error' => sprintf('Groupe %d introuvable.', $groupId)], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $groupe);

        $depense = $this->expenseService->createExpenseForGroup($groupe, $user, $dto);

        $data = $this->expenseService->getExpenseWithRepartition($depense);

        return $this->json($this->serializeWithRepartition($data['depense'], $data['repartitions']), Response::HTTP_CREATED);
    }

    #[Route('/api/groups/{groupId}/expenses', name: 'api_expenses_list', methods: ['GET'], requirements: ['groupId' => '\d+'])]
    public function list(int $groupId): JsonResponse
    {
        $groupe = $this->em->getRepository(Groupe::class)->find($groupId);
        if ($groupe === null) {
            return $this->json(['error' => sprintf('Groupe %d introuvable.', $groupId)], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $groupe);

        $depenses = $this->expenseService->listExpensesForGroup($groupe);

        return $this->json(array_map(fn (Depense $d) => $this->serializeDepense($d), $depenses));
    }

    #[Route('/api/expenses/{id}', name: 'api_expenses_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Depense $depense): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::VIEW, $depense);

        $data = $this->expenseService->getExpenseWithRepartition($depense);

        return $this->json($this->serializeWithRepartition($data['depense'], $data['repartitions']));
    }

    /** @return array<string, mixed> */
    private function serializeDepense(Depense $depense): array
    {
        return [
            'id' => $depense->getId(),
            'description' => $depense->getDescription(),
            'montant' => $depense->getMontant(),
            'date_depense' => $depense->getDateDepense()->format('Y-m-d'),
            'date_creation' => $depense->getDateCreation()->format(\DateTimeInterface::ATOM),
            'type_repartition' => $depense->getTypeRepartition()->value,
            'categorie' => [
                'id' => $depense->getCategorie()->getId(),
                'libelle' => $depense->getCategorie()->getLibelle(),
            ],
            'payeur' => [
                'id' => $depense->getPayeur()->getId(),
                'prenom' => $depense->getPayeur()->getPrenom(),
                'nom' => $depense->getPayeur()->getNom(),
            ],
            'groupe_id' => $depense->getGroupe()->getId(),
        ];
    }

    /**
     * @param Repartir[] $repartitions
     * @return array<string, mixed>
     */
    private function serializeWithRepartition(Depense $depense, array $repartitions): array
    {
        $base = $this->serializeDepense($depense);
        $base['beneficiaires'] = array_map(fn (Repartir $r) => [
            'id' => $r->getBeneficiaire()->getId(),
            'prenom' => $r->getBeneficiaire()->getPrenom(),
            'nom' => $r->getBeneficiaire()->getNom(),
            'montant_part' => $r->getMontantPart(),
            'pourcentage' => $r->getPourcentage(),
        ], $repartitions);

        return $base;
    }
}
