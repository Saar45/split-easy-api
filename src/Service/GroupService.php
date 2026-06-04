<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateGroupDto;
use App\Dto\UpdateGroupDto;
use App\Entity\Appartenir;
use App\Entity\Groupe;
use App\Entity\Utilisateur;
use App\Enum\RoleAppartenir;
use App\Enum\StatutInvitation;
use App\Repository\AppartenirRepository;
use App\Repository\GroupeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class GroupService
{
    public function __construct(
        private readonly GroupeRepository $groupRepository,
        private readonly AppartenirRepository $appartenirRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function createGroupForUser(CreateGroupDto $dto, Utilisateur $createur): Groupe
    {
        $groupe = (new Groupe())
            ->setNom($dto->nom)
            ->setDescription($dto->description)
            ->setCouleur($dto->couleur);

        $appartenir = (new Appartenir())
            ->setUtilisateur($createur)
            ->setGroupe($groupe)
            ->setRole(RoleAppartenir::Createur)
            ->setStatutInvitation(StatutInvitation::Acceptee)
            ->setDateAdhesion(new \DateTimeImmutable())
            ->setTokenInvitation(bin2hex(random_bytes(32)));

        $this->em->persist($groupe);
        $this->em->persist($appartenir);
        $this->em->flush();

        return $groupe;
    }

    /** @return Groupe[] */
    public function listGroupsForUser(Utilisateur $user): array
    {
        return $this->groupRepository->createQueryBuilder('g')
            ->innerJoin(Appartenir::class, 'a', 'WITH', 'a.groupe = g')
            ->where('a.utilisateur = :user')
            ->andWhere('a.statutInvitation = :accepted')
            ->setParameter('user', $user)
            ->setParameter('accepted', StatutInvitation::Acceptee)
            ->orderBy('g.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function isMember(Groupe $groupe, Utilisateur $user): bool
    {
        $appartenir = $this->appartenirRepository->findOneBy([
            'groupe' => $groupe,
            'utilisateur' => $user,
            'statutInvitation' => StatutInvitation::Acceptee,
        ]);

        return $appartenir !== null;
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

    public function updateGroup(Groupe $groupe, UpdateGroupDto $dto): Groupe
    {
        if ($dto->nom !== null) {
            $groupe->setNom($dto->nom);
        }
        if ($dto->description !== null) {
            $groupe->setDescription($dto->description);
        }
        if ($dto->couleur !== null) {
            $groupe->setCouleur($dto->couleur);
        }

        $this->em->flush();

        return $groupe;
    }

    public function deleteGroup(Groupe $groupe): void
    {
        $appartenances = $this->appartenirRepository->findBy(['groupe' => $groupe]);
        foreach ($appartenances as $appartenir) {
            $this->em->remove($appartenir);
        }

        $this->em->remove($groupe);
        $this->em->flush();
    }
}
