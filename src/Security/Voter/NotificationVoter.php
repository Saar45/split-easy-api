<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Notification;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * F9 - Voter notifications : seul le destinataire peut marquer une notification comme lue.
 */
final class NotificationVoter extends Voter
{
    public const READ = 'NOTIFICATION_READ';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::READ === $attribute && $subject instanceof Notification;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Utilisateur || !$subject instanceof Notification) {
            return false;
        }

        return $subject->getDestinataire()->getId() === $user->getId();
    }
}
