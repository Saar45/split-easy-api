<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TypeRepartition;
use App\Repository\DepenseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DepenseRepository::class)]
#[ORM\Table(name: 'depense')]
#[ORM\Index(name: 'idx_depense_groupe', columns: ['id_groupe'])]
#[ORM\Index(name: 'idx_depense_utilisateur', columns: ['id_utilisateur'])]
#[ORM\Index(name: 'idx_depense_categorie', columns: ['id_categorie'])]
#[ORM\Index(name: 'idx_depense_date', columns: ['date_depense'])]
class Depense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_depense', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'description', type: 'string', length: 100)]
    private string $description;

    #[ORM\Column(name: 'montant', type: 'decimal', precision: 10, scale: 2)]
    private string $montant;

    #[ORM\Column(name: 'date_depense', type: 'date_immutable')]
    private \DateTimeInterface $dateDepense;

    #[ORM\Column(name: 'date_creation', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(name: 'chemin_ticket', type: 'string', length: 255, nullable: true)]
    private ?string $cheminTicket = null;

    #[ORM\Column(
        name: 'type_repartition',
        type: 'string',
        enumType: TypeRepartition::class,
        options: ['default' => 'equitable'],
        columnDefinition: "ENUM('equitable','personnalisee','pourcentage') NOT NULL DEFAULT 'equitable'",
    )]
    private TypeRepartition $typeRepartition = TypeRepartition::Equitable;

    #[ORM\Column(name: 'date_modification', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $dateModification = null;

    #[ORM\ManyToOne(targetEntity: Categorie::class)]
    #[ORM\JoinColumn(name: 'id_categorie', referencedColumnName: 'id_categorie', nullable: false)]
    private Categorie $categorie;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private Utilisateur $payeur;

    #[ORM\ManyToOne(targetEntity: Groupe::class)]
    #[ORM\JoinColumn(name: 'id_groupe', referencedColumnName: 'id_groupe', nullable: false)]
    private Groupe $groupe;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
        $this->dateDepense = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }

    public function getMontant(): string { return $this->montant; }
    public function setMontant(string $montant): self { $this->montant = $montant; return $this; }

    public function getDateDepense(): \DateTimeInterface { return $this->dateDepense; }
    public function setDateDepense(\DateTimeInterface $dateDepense): self { $this->dateDepense = $dateDepense; return $this; }

    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }

    public function getCheminTicket(): ?string { return $this->cheminTicket; }
    public function setCheminTicket(?string $cheminTicket): self { $this->cheminTicket = $cheminTicket; return $this; }

    public function getTypeRepartition(): TypeRepartition { return $this->typeRepartition; }
    public function setTypeRepartition(TypeRepartition $type): self { $this->typeRepartition = $type; return $this; }

    public function getDateModification(): ?\DateTimeInterface { return $this->dateModification; }
    public function setDateModification(?\DateTimeInterface $date): self { $this->dateModification = $date; return $this; }

    public function getCategorie(): Categorie { return $this->categorie; }
    public function setCategorie(Categorie $categorie): self { $this->categorie = $categorie; return $this; }

    public function getPayeur(): Utilisateur { return $this->payeur; }
    public function setPayeur(Utilisateur $payeur): self { $this->payeur = $payeur; return $this; }

    public function getGroupe(): Groupe { return $this->groupe; }
    public function setGroupe(Groupe $groupe): self { $this->groupe = $groupe; return $this; }
}
