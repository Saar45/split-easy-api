<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Groupe;
use App\Entity\Utilisateur;
use App\Service\GroupService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class GroupVoter extends Voter
{
    public const VIEW = 'GROUP_VIEW';
    public const DELETE = 'GROUP_DELETE';

    public function __construct(private readonly GroupService $groupService)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::DELETE], true) && $subject instanceof Groupe;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Utilisateur || !$subject instanceof Groupe) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->groupService->isMember($subject, $user),
            self::DELETE => $this->groupService->isCreator($subject, $user),
        };
    }
}
