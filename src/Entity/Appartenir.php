<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RoleAppartenir;
use App\Enum\StatutInvitation;
use App\Repository\AppartenirRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppartenirRepository::class)]
#[ORM\Table(name: 'appartenir')]
#[ORM\Index(name: 'idx_appartenir_groupe', columns: ['id_groupe'])]
#[ORM\UniqueConstraint(name: 'idx_appartenir_token', columns: ['token_invitation'])]
class Appartenir
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private Utilisateur $utilisateur;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Groupe::class)]
    #[ORM\JoinColumn(name: 'id_groupe', referencedColumnName: 'id_groupe', nullable: false)]
    private Groupe $groupe;

    #[ORM\Column(name: 'date_adhesion', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $dateAdhesion = null;

    #[ORM\Column(
        name: 'statut_invitation',
        type: 'string',
        enumType: StatutInvitation::class,
        options: ['default' => 'en_attente'],
        columnDefinition: "ENUM('en_attente','acceptee','refusee','expiree') NOT NULL DEFAULT 'en_attente'",
    )]
    private StatutInvitation $statutInvitation = StatutInvitation::EnAttente;

    #[ORM\Column(name: 'token_invitation', type: 'string', length: 250, unique: true)]
    private string $tokenInvitation;

    #[ORM\Column(name: 'date_invitation', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $dateInvitation;

    #[ORM\Column(
        name: 'role',
        type: 'string',
        enumType: RoleAppartenir::class,
        options: ['default' => 'membre'],
        columnDefinition: "ENUM('createur','membre') NOT NULL DEFAULT 'membre'",
    )]
    private RoleAppartenir $role = RoleAppartenir::Membre;

    #[ORM\Column(name: 'date_expiration', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $dateExpiration = null;

    #[ORM\Column(name: 'date_acceptation', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $dateAcceptation = null;

    public function __construct()
    {
        $this->dateInvitation = new \DateTimeImmutable();
    }

    public function getUtilisateur(): Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(Utilisateur $utilisateur): self { $this->utilisateur = $utilisateur; return $this; }

    public function getGroupe(): Groupe { return $this->groupe; }
    public function setGroupe(Groupe $groupe): self { $this->groupe = $groupe; return $this; }

    public function getDateAdhesion(): ?\DateTimeInterface { return $this->dateAdhesion; }
    public function setDateAdhesion(?\DateTimeInterface $date): self { $this->dateAdhesion = $date; return $this; }

    public function getStatutInvitation(): StatutInvitation { return $this->statutInvitation; }
    public function setStatutInvitation(StatutInvitation $statut): self { $this->statutInvitation = $statut; return $this; }

    public function getTokenInvitation(): string { return $this->tokenInvitation; }
    public function setTokenInvitation(string $token): self { $this->tokenInvitation = $token; return $this; }

    public function getDateInvitation(): \DateTimeInterface { return $this->dateInvitation; }

    public function getRole(): RoleAppartenir { return $this->role; }
    public function setRole(RoleAppartenir $role): self { $this->role = $role; return $this; }

    public function getDateExpiration(): ?\DateTimeInterface { return $this->dateExpiration; }
    public function setDateExpiration(?\DateTimeInterface $date): self { $this->dateExpiration = $date; return $this; }

    public function getDateAcceptation(): ?\DateTimeInterface { return $this->dateAcceptation; }
    public function setDateAcceptation(?\DateTimeInterface $date): self { $this->dateAcceptation = $date; return $this; }
}
