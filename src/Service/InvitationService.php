<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Appartenir;
use App\Entity\Groupe;
use App\Entity\Utilisateur;
use App\Enum\RoleAppartenir;
use App\Enum\StatutInvitation;
use App\Repository\AppartenirRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * F7 - Service des invitations par lien unique.
 *
 * Modèle d'invitation : le créateur d'un groupe invite par email un utilisateur
 * déjà inscrit. Un token aléatoire (32 octets, encodage hex 64 caractères) est
 * généré, persisté sur la table appartenir avec statut en_attente et une date
 * d'expiration à +7 jours (dossier §6.3 - règles métier). L'invité accepte ou
 * refuse l'invitation via le token, ce qui bascule statut_invitation vers
 * acceptee ou refusee et renseigne date_acceptation pour l'audit.
 */
final class InvitationService
{
    private const TOKEN_BYTES = 32;
    private const EXPIRATION_DAYS = 7;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AppartenirRepository $appartenirRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
    ) {
    }

    public function createInvitation(Groupe $groupe, string $email, Utilisateur $inviter): Appartenir
    {
        if (!$this->isCreator($groupe, $inviter)) {
            throw new AccessDeniedHttpException('Seul le créateur du groupe peut inviter.');
        }

        $invite = $this->utilisateurRepository->findOneBy(['email' => $email]);
        if ($invite === null) {
            throw new UnprocessableEntityHttpException('L\'utilisateur doit avoir un compte.');
        }

        if ($invite->getId() === $inviter->getId()) {
            throw new ConflictHttpException('Vous êtes déjà membre de ce groupe.');
        }

        $existing = $this->appartenirRepository->findOneBy([
            'groupe' => $groupe,
            'utilisateur' => $invite,
        ]);
        if ($existing !== null) {
            $statut = $existing->getStatutInvitation();
            if ($statut === StatutInvitation::Acceptee) {
                throw new ConflictHttpException('Cet utilisateur est déjà membre du groupe.');
            }
            if ($statut === StatutInvitation::EnAttente && !$this->isExpired($existing)) {
                throw new ConflictHttpException('Une invitation est déjà en attente pour cet utilisateur.');
            }
            // refusee/expiree ou pending expirée : on remplace l'ancienne ligne.
            $this->em->remove($existing);
            $this->em->flush();
        }

        $appartenir = (new Appartenir())
            ->setUtilisateur($invite)
            ->setGroupe($groupe)
            ->setRole(RoleAppartenir::Membre)
            ->setStatutInvitation(StatutInvitation::EnAttente)
            ->setTokenInvitation($this->generateToken())
            ->setDateExpiration(new \DateTimeImmutable('+' . self::EXPIRATION_DAYS . ' days'));

        $this->em->persist($appartenir);
        $this->em->flush();

        return $appartenir;
    }

    public function acceptInvitation(string $token, Utilisateur $currentUser): Appartenir
    {
        $appartenir = $this->findByTokenOrFail($token);
        $this->ensurePending($appartenir);
        $this->ensureNotExpired($appartenir);
        $this->ensureInvitedUser($appartenir, $currentUser);

        $now = new \DateTimeImmutable();
        $appartenir
            ->setStatutInvitation(StatutInvitation::Acceptee)
            ->setDateAcceptation($now)
            ->setDateAdhesion($now);
        $this->em->flush();

        return $appartenir;
    }

    public function refuseInvitation(string $token, Utilisateur $currentUser): Appartenir
    {
        $appartenir = $this->findByTokenOrFail($token);
        $this->ensurePending($appartenir);
        $this->ensureNotExpired($appartenir);
        $this->ensureInvitedUser($appartenir, $currentUser);

        $appartenir->setStatutInvitation(StatutInvitation::Refusee);
        $this->em->flush();

        return $appartenir;
    }

    /** @return Appartenir[] */
    public function listPendingForUser(Utilisateur $user): array
    {
        $rows = $this->appartenirRepository->createQueryBuilder('a')
            ->where('a.utilisateur = :u')
            ->andWhere('a.statutInvitation = :pending')
            ->setParameter('u', $user)
            ->setParameter('pending', StatutInvitation::EnAttente)
            ->getQuery()
            ->getResult();

        return array_values(array_filter($rows, fn (Appartenir $a) => !$this->isExpired($a)));
    }

    /** @return Appartenir[] */
    public function listForGroup(Groupe $groupe): array
    {
        return $this->appartenirRepository->createQueryBuilder('a')
            ->where('a.groupe = :g')
            ->setParameter('g', $groupe)
            ->getQuery()
            ->getResult();
    }

    public function isCreator(Groupe $groupe, Utilisateur $user): bool
    {
        $appartenir = $this->appartenirRepository->findOneBy([
            'groupe' => $groupe,
            'utilisateur' => $user,
            'statutInvitation' => StatutInvitation::Acceptee,
        ]);

        return $appartenir !== null && $appartenir->getRole() === RoleAppartenir::Createur;
    }

    private function findByTokenOrFail(string $token): Appartenir
    {
        $appartenir = $this->appartenirRepository->findOneBy(['tokenInvitation' => $token]);
        if ($appartenir === null) {
            throw new NotFoundHttpException('Invitation introuvable.');
        }

        return $appartenir;
    }

    private function ensurePending(Appartenir $appartenir): void
    {
        if ($appartenir->getStatutInvitation() !== StatutInvitation::EnAttente) {
            throw new ConflictHttpException(sprintf(
                'Cette invitation est déjà %s.',
                $appartenir->getStatutInvitation()->value,
            ));
        }
    }

    private function ensureNotExpired(Appartenir $appartenir): void
    {
        if ($this->isExpired($appartenir)) {
            $appartenir->setStatutInvitation(StatutInvitation::Expiree);
            $this->em->flush();
            throw new GoneHttpException('Cette invitation a expiré.');
        }
    }

    private function ensureInvitedUser(Appartenir $appartenir, Utilisateur $currentUser): void
    {
        if ($appartenir->getUtilisateur()->getId() !== $currentUser->getId()) {
            throw new AccessDeniedHttpException('Cette invitation ne vous est pas destinée.');
        }
    }

    private function isExpired(Appartenir $appartenir): bool
    {
        $exp = $appartenir->getDateExpiration();
        if ($exp === null) {
            return false;
        }

        return $exp < new \DateTimeImmutable();
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_BYTES));
    }
}
