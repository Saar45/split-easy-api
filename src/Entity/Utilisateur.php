<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur')]
#[ORM\UniqueConstraint(name: 'idx_utilisateur_email', columns: ['email'])]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_utilisateur', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'nom', type: 'string', length: 50)]
    private string $nom;

    #[ORM\Column(name: 'prenom', type: 'string', length: 50)]
    private string $prenom;

    #[ORM\Column(name: 'email', type: 'string', length: 255, unique: true)]
    private string $email;

    #[ORM\Column(name: 'mot_de_passe', type: 'string', length: 255)]
    private string $motDePasse;

    #[ORM\Column(name: 'photo_profil', type: 'string', length: 255, nullable: true)]
    private ?string $photoProfil = null;

    #[ORM\Column(name: 'date_inscription', type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $dateInscription;

    #[ORM\Column(name: 'email_verifie', type: 'boolean', options: ['default' => false])]
    private bool $emailVerifie = false;

    #[ORM\Column(name: 'token_reinitialisation', type: 'string', length: 100, nullable: true)]
    private ?string $tokenReinitialisation = null;

    #[ORM\Column(name: 'date_token_reinitialisation', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateTokenReinitialisation = null;

    public function __construct()
    {
        $this->dateInscription = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }

    public function getPrenom(): string { return $this->prenom; }
    public function setPrenom(string $prenom): self { $this->prenom = $prenom; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getMotDePasse(): string { return $this->motDePasse; }
    public function setMotDePasse(string $hashedPassword): self { $this->motDePasse = $hashedPassword; return $this; }

    public function getPhotoProfil(): ?string { return $this->photoProfil; }
    public function setPhotoProfil(?string $photoProfil): self { $this->photoProfil = $photoProfil; return $this; }

    public function getDateInscription(): \DateTimeInterface { return $this->dateInscription; }

    public function isEmailVerifie(): bool { return $this->emailVerifie; }
    public function setEmailVerifie(bool $emailVerifie): self { $this->emailVerifie = $emailVerifie; return $this; }

    public function getTokenReinitialisation(): ?string { return $this->tokenReinitialisation; }
    public function setTokenReinitialisation(?string $token): self
    {
        $this->tokenReinitialisation = $token;
        $this->dateTokenReinitialisation = $token !== null ? new \DateTimeImmutable() : null;
        return $this;
    }

    public function getDateTokenReinitialisation(): ?\DateTimeInterface { return $this->dateTokenReinitialisation; }

    public function getPassword(): string { return $this->motDePasse; }

    public function getRoles(): array { return ['ROLE_USER']; }

    public function getUserIdentifier(): string { return $this->email; }

    public function eraseCredentials(): void {}
}
