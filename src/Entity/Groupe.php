<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StatutGroupe;
use App\Repository\GroupeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupeRepository::class)]
#[ORM\Table(name: 'groupe')]
class Groupe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_groupe', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'nom', type: 'string', length: 100)]
    private string $nom;

    #[ORM\Column(name: 'couleur', type: 'string', length: 7, nullable: true)]
    private ?string $couleur = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(
        name: 'statut',
        type: 'string',
        enumType: StatutGroupe::class,
        options: ['default' => 'actif'],
        columnDefinition: "ENUM('actif','archive') NOT NULL DEFAULT 'actif'",
    )]
    private StatutGroupe $statut = StatutGroupe::Actif;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }

    public function getCouleur(): ?string { return $this->couleur; }
    public function setCouleur(?string $couleur): self { $this->couleur = $couleur; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }

    public function getStatut(): StatutGroupe { return $this->statut; }
    public function setStatut(StatutGroupe $statut): self { $this->statut = $statut; return $this; }
}
