<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Appartenir;
use App\Entity\Depense;
use App\Entity\Groupe;
use App\Entity\Remboursement;
use App\Entity\Repartir;
use App\Entity\Utilisateur;
use App\Enum\RoleAppartenir;
use App\Enum\StatutInvitation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds the full RGPD article-15 export for a given user.
 * Only the requesting user's data is included; no other member's PII leaks.
 */
final class UserDataExportService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return array<string, mixed> */
    public function export(Utilisateur $user): array
    {
        return [
            'utilisateur'      => $this->exportUtilisateur($user),
            'groupes_membre_de' => $this->exportAppartenances($user),
            'groupes_crees'    => $this->exportGroupesCrees($user),
            'depenses_payees'  => $this->exportDepenses($user),
            'parts_recues'     => $this->exportParts($user),
            'remboursements'   => $this->exportRemboursements($user),
        ];
    }

    /** @return array<string, mixed> */
    private function exportUtilisateur(Utilisateur $user): array
    {
        return [
            'id'               => $user->getId(),
            'email'            => $user->getEmail(),
            'nom'              => $user->getNom(),
            'prenom'           => $user->getPrenom(),
            'date_inscription' => $user->getDateInscription()->format(\DateTimeInterface::ATOM),
            'email_verifie'    => $user->isEmailVerifie(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function exportAppartenances(Utilisateur $user): array
    {
        /** @var Appartenir[] $rows */
        $rows = $this->em->getRepository(Appartenir::class)->findBy(['utilisateur' => $user]);

        return array_map(static function (Appartenir $a): array {
            return [
                'id_groupe'         => $a->getGroupe()->getId(),
                'role'              => $a->getRole()->value,
                'statut_invitation' => $a->getStatutInvitation()->value,
                'date_adhesion'     => $a->getDateAdhesion()?->format(\DateTimeInterface::ATOM),
                'date_acceptation'  => $a->getDateAcceptation()?->format(\DateTimeInterface::ATOM),
                'date_expiration'   => $a->getDateExpiration()?->format(\DateTimeInterface::ATOM),
            ];
        }, $rows);
    }

    /** @return list<array<string, mixed>> */
    private function exportGroupesCrees(Utilisateur $user): array
    {
        /** @var Appartenir[] $rows */
        $rows = $this->em->getRepository(Appartenir::class)->findBy([
            'utilisateur'     => $user,
            'role'            => RoleAppartenir::Createur,
            'statutInvitation' => StatutInvitation::Acceptee,
        ]);

        return array_map(static function (Appartenir $a): array {
            $g = $a->getGroupe();
            return [
                'id'            => $g->getId(),
                'nom'           => $g->getNom(),
                'description'   => $g->getDescription(),
                'statut'        => $g->getStatut()->value,
                'date_creation' => $g->getDateCreation()->format(\DateTimeInterface::ATOM),
            ];
        }, $rows);
    }

    /** @return list<array<string, mixed>> */
    private function exportDepenses(Utilisateur $user): array
    {
        /** @var Depense[] $depenses */
        $depenses = $this->em->getRepository(Depense::class)->findBy(['payeur' => $user]);

        return array_map(static function (Depense $d): array {
            return [
                'id'              => $d->getId(),
                'description'     => $d->getDescription(),
                'montant'         => $d->getMontant(),
                'date_depense'    => $d->getDateDepense()->format('Y-m-d'),
                'date_creation'   => $d->getDateCreation()->format(\DateTimeInterface::ATOM),
                'type_repartition' => $d->getTypeRepartition()->value,
                'id_groupe'       => $d->getGroupe()->getId(),
                'id_categorie'    => $d->getCategorie()->getId(),
            ];
        }, $depenses);
    }

    /** @return list<array<string, mixed>> */
    private function exportParts(Utilisateur $user): array
    {
        /** @var Repartir[] $parts */
        $parts = $this->em->getRepository(Repartir::class)->findBy(['beneficiaire' => $user]);

        return array_map(static function (Repartir $r): array {
            return [
                'id_depense'   => $r->getDepense()->getId(),
                'montant_part' => $r->getMontantPart(),
                'pourcentage'  => $r->getPourcentage(),
            ];
        }, $parts);
    }

    /** @return list<array<string, mixed>> */
    private function exportRemboursements(Utilisateur $user): array
    {
        $qb = $this->em->createQueryBuilder();
        /** @var Remboursement[] $rows */
        $rows = $qb->select('r')
            ->from(Remboursement::class, 'r')
            ->where('r.debiteur = :user OR r.crediteur = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        return array_map(static function (Remboursement $r) use ($user): array {
            return [
                'id'               => $r->getId(),
                'montant'          => $r->getMontant(),
                'statut'           => $r->getStatut()->value,
                'role'             => $r->getDebiteur()->getId() === $user->getId() ? 'debiteur' : 'crediteur',
                'id_contrepartie'  => $r->getDebiteur()->getId() === $user->getId()
                    ? $r->getCrediteur()->getId()
                    : $r->getDebiteur()->getId(),
                'id_groupe'        => $r->getGroupe()->getId(),
                'date_creation'    => $r->getDateCreation()->format(\DateTimeInterface::ATOM),
                'date_proposition' => $r->getDateProposition()?->format(\DateTimeInterface::ATOM),
                'date_validation'  => $r->getDateValidation()?->format(\DateTimeInterface::ATOM),
            ];
        }, $rows);
    }
}
