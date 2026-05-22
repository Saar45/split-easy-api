<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StatutRemboursement;
use App\Repository\RemboursementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RemboursementRepository::class)]
#[ORM\Table(name: 'remboursement')]
#[ORM\Index(name: 'idx_remboursement_groupe', columns: ['id_groupe'])]
#[ORM\Index(name: 'idx_remboursement_debiteur', columns: ['id_debiteur'])]
#[ORM\Index(name: 'idx_remboursement_crediteur', columns: ['id_crediteur'])]
class Remboursement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_remboursement', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'montant', type: 'decimal', precision: 10, scale: 2)]
    private string $montant;

    #[ORM\Column(
        name: 'statut',
        type: 'string',
        enumType: StatutRemboursement::class,
        options: ['default' => 'en_attente'],
        columnDefinition: "ENUM('en_attente','propose','valide','conteste') NOT NULL DEFAULT 'en_attente'",
    )]
    private StatutRemboursement $statut = StatutRemboursement::EnAttente;

    #[ORM\Column(name: 'date_creation', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(name: 'date_proposition', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $dateProposition = null;

    #[ORM\Column(name: 'date_validation', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $dateValidation = null;

    #[ORM\ManyToOne(targetEntity: Groupe::class)]
    #[ORM\JoinColumn(name: 'id_groupe', referencedColumnName: 'id_groupe', nullable: false)]
    private Groupe $groupe;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_debiteur', referencedColumnName: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private Utilisateur $debiteur;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_crediteur', referencedColumnName: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private Utilisateur $crediteur;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getMontant(): string { return $this->montant; }
    public function setMontant(string $montant): self { $this->montant = $montant; return $this; }

    public function getStatut(): StatutRemboursement { return $this->statut; }
    public function setStatut(StatutRemboursement $statut): self { $this->statut = $statut; return $this; }

    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }

    public function getDateProposition(): ?\DateTimeInterface { return $this->dateProposition; }
    public function setDateProposition(?\DateTimeInterface $date): self { $this->dateProposition = $date; return $this; }

    public function getDateValidation(): ?\DateTimeInterface { return $this->dateValidation; }
    public function setDateValidation(?\DateTimeInterface $date): self { $this->dateValidation = $date; return $this; }

    public function getGroupe(): Groupe { return $this->groupe; }
    public function setGroupe(Groupe $groupe): self { $this->groupe = $groupe; return $this; }

    public function getDebiteur(): Utilisateur { return $this->debiteur; }
    public function setDebiteur(Utilisateur $debiteur): self { $this->debiteur = $debiteur; return $this; }

    public function getCrediteur(): Utilisateur { return $this->crediteur; }
    public function setCrediteur(Utilisateur $crediteur): self { $this->crediteur = $crediteur; return $this; }
}
