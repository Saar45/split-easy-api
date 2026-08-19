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
    public const EDIT = 'EXPENSE_EDIT';
    public const DELETE = 'EXPENSE_DELETE';

    public function __construct(private readonly GroupService $groupService)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true) && $subject instanceof Depense;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Utilisateur || !$subject instanceof Depense) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->groupService->isMember($subject->getGroupe(), $user),
            // Payeur (auteur de la dépense) ou créateur du groupe, comme pour GroupVoter::EDIT/DELETE.
            self::EDIT, self::DELETE => $subject->getPayeur()->getId() === $user->getId()
                || $this->groupService->isCreator($subject->getGroupe(), $user),
            default => false,
        };
    }
}
