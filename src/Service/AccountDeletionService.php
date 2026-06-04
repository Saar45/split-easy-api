<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Appartenir;
use App\Entity\RefreshToken;
use App\Entity\Utilisateur;
use App\Enum\RoleAppartenir;
use App\Enum\StatutInvitation;
use App\Exception\ActiveGroupOwnershipException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handles RGPD article-17 hard-delete of a user account.
 *
 * Guard: a creator of a group with other accepted members must transfer
 * ownership or delete those groups first. This avoids wiping shared data.
 */
final class AccountDeletionService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @throws ActiveGroupOwnershipException when the user owns active groups with other members.
     */
    public function delete(Utilisateur $user): void
    {
        $this->assertNoActiveGroupOwnership($user);

        $this->invalidateRefreshTokens($user);

        // ON DELETE CASCADE on all FK -> utilisateur handles appartenir, repartir,
        // remboursement (debiteur/crediteur), depense (payeur), preference_notification rows.
        $this->em->remove($user);
        $this->em->flush();
    }

    private function assertNoActiveGroupOwnership(Utilisateur $user): void
    {
        // Find all groups where this user is createur with at least one other accepted member.
        $creatorRows = $this->em->getRepository(Appartenir::class)->findBy([
            'utilisateur'      => $user,
            'role'             => RoleAppartenir::Createur,
            'statutInvitation' => StatutInvitation::Acceptee,
        ]);

        foreach ($creatorRows as $appartenir) {
            $otherMembers = $this->em->createQueryBuilder()
                ->select('COUNT(a.utilisateur)')
                ->from(Appartenir::class, 'a')
                ->where('a.groupe = :groupe')
                ->andWhere('a.utilisateur != :user')
                ->andWhere('a.statutInvitation = :accepted')
                ->setParameter('groupe', $appartenir->getGroupe())
                ->setParameter('user', $user)
                ->setParameter('accepted', StatutInvitation::Acceptee)
                ->getQuery()
                ->getSingleScalarResult();

            if ((int) $otherMembers > 0) {
                throw new ActiveGroupOwnershipException();
            }
        }
    }

    private function invalidateRefreshTokens(Utilisateur $user): void
    {
        // Lexik stores refresh tokens by username (email); delete them all before account removal.
        $tokens = $this->em->getRepository(RefreshToken::class)
            ->findBy(['username' => $user->getEmail()]);

        foreach ($tokens as $token) {
            $this->em->remove($token);
        }
    }
}
