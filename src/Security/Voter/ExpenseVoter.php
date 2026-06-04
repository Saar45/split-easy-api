<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Depense;
use App\Entity\Utilisateur;
use App\Service\GroupService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ExpenseVoter extends Voter
{
    public const VIEW = 'EXPENSE_VIEW';

    public function __construct(private readonly GroupService $groupService)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof Depense;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Utilisateur || !$subject instanceof Depense) {
            return false;
        }

        return $this->groupService->isMember($subject->getGroupe(), $user);
    }
}
