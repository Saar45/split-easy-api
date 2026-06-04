<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Appartenir;
use App\Entity\Groupe;
use App\Entity\Utilisateur;
use App\Service\InvitationService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * F7 - Voter des invitations.
 *
 * - INVITATION_CREATE : sujet = Groupe ; autorise seulement le créateur du groupe à inviter.
 * - INVITATION_ACCEPT / INVITATION_REFUSE : sujet = Appartenir ; autorise uniquement
 *   l'utilisateur destinataire de l'invitation.
 */
final class InvitationVoter extends Voter
{
    public const CREATE = 'INVITATION_CREATE';
    public const ACCEPT = 'INVITATION_ACCEPT';
    public const REFUSE = 'INVITATION_REFUSE';

    public function __construct(private readonly InvitationService $invitationService)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($attribute === self::CREATE) {
            return $subject instanceof Groupe;
        }
        if (in_array($attribute, [self::ACCEPT, self::REFUSE], true)) {
            return $subject instanceof Appartenir;
        }

        return false;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Utilisateur) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $subject instanceof Groupe && $this->invitationService->isCreator($subject, $user),
            self::ACCEPT, self::REFUSE => $subject instanceof Appartenir
                && $subject->getUtilisateur()->getId() === $user->getId(),
            default => false,
        };
    }
}
