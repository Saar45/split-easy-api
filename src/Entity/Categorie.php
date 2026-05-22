<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CategorieRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategorieRepository::class)]
#[ORM\Table(name: 'categorie')]
#[ORM\UniqueConstraint(name: 'idx_categorie_libelle', columns: ['libelle'])]
class Categorie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_categorie', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'libelle', type: 'string', length: 50, unique: true)]
    private string $libelle;

    #[ORM\Column(name: 'icone', type: 'string', length: 50, nullable: true)]
    private ?string $icone = null;

    #[ORM\Column(name: 'couleur', type: 'string', length: 7, nullable: true)]
    private ?string $couleur = null;

    #[ORM\Column(name: 'ordre_affichage', type: 'integer', nullable: true)]
    private ?int $ordreAffichage = null;

    public function getId(): ?int { return $this->id; }

    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $libelle): self { $this->libelle = $libelle; return $this; }

    public function getIcone(): ?string { return $this->icone; }
    public function setIcone(?string $icone): self { $this->icone = $icone; return $this; }

    public function getCouleur(): ?string { return $this->couleur; }
    public function setCouleur(?string $couleur): self { $this->couleur = $couleur; return $this; }

    public function getOrdreAffichage(): ?int { return $this->ordreAffichage; }
    public function setOrdreAffichage(?int $ordreAffichage): self { $this->ordreAffichage = $ordreAffichage; return $this; }
}
