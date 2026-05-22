<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RepartirRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RepartirRepository::class)]
#[ORM\Table(name: 'repartir')]
#[ORM\Index(name: 'idx_repartir_depense', columns: ['id_depense'])]
class Repartir
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private Utilisateur $beneficiaire;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Depense::class)]
    #[ORM\JoinColumn(name: 'id_depense', referencedColumnName: 'id_depense', nullable: false, onDelete: 'CASCADE')]
    private Depense $depense;

    #[ORM\Column(name: 'montant_part', type: 'decimal', precision: 10, scale: 2)]
    private string $montantPart;

    #[ORM\Column(name: 'pourcentage', type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $pourcentage = null;

    public function getBeneficiaire(): Utilisateur { return $this->beneficiaire; }
    public function setBeneficiaire(Utilisateur $beneficiaire): self { $this->beneficiaire = $beneficiaire; return $this; }

    public function getDepense(): Depense { return $this->depense; }
    public function setDepense(Depense $depense): self { $this->depense = $depense; return $this; }

    public function getMontantPart(): string { return $this->montantPart; }
    public function setMontantPart(string $montantPart): self { $this->montantPart = $montantPart; return $this; }

    public function getPourcentage(): ?string { return $this->pourcentage; }
    public function setPourcentage(?string $pourcentage): self { $this->pourcentage = $pourcentage; return $this; }
}
