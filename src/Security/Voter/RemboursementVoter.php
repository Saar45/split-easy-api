<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Remboursement;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * F6 - Voter bipartite des remboursements.
 *
 * Rôles autorisés selon l'action :
 *   - VIEW   : débiteur ou créancier
 *   - ACCEPT : créancier uniquement
 *   - REJECT : créancier uniquement
 *   - CANCEL : débiteur uniquement
 */
final class RemboursementVoter extends Voter
{
    public const VIEW = 'REIMBURSEMENT_VIEW';
    public const ACCEPT = 'REIMBURSEMENT_ACCEPT';
    public const REJECT = 'REIMBURSEMENT_REJECT';
    public const CANCEL = 'REIMBURSEMENT_CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::ACCEPT, self::REJECT, self::CANCEL], true)
            && $subject instanceof Remboursement;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Utilisateur || !$subject instanceof Remboursement) {
            return false;
        }

        $isDebtor = $subject->getDebiteur()->getId() === $user->getId();
        $isCreditor = $subject->getCrediteur()->getId() === $user->getId();

        return match ($attribute) {
            self::VIEW => $isDebtor || $isCreditor,
            self::ACCEPT, self::REJECT => $isCreditor,
            self::CANCEL => $isDebtor,
            default => false,
        };
    }
}
