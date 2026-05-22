<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FrequenceRappel;
use App\Enum\TypeNotification;
use App\Repository\PreferenceNotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PreferenceNotificationRepository::class)]
#[ORM\Table(name: 'preference_notification')]
#[ORM\Index(name: 'idx_preference_utilisateur', columns: ['id_utilisateur'])]
#[ORM\UniqueConstraint(name: 'idx_preference_unique', columns: ['id_utilisateur', 'type_notification'])]
class PreferenceNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_preference', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'type_notification', type: 'string', length: 75, enumType: TypeNotification::class)]
    private TypeNotification $typeNotification;

    #[ORM\Column(name: 'est_active', type: 'boolean', options: ['default' => true])]
    private bool $estActive = true;

    #[ORM\Column(name: 'canal_email', type: 'boolean', options: ['default' => true])]
    private bool $canalEmail = true;

    #[ORM\Column(
        name: 'frequence_rappel',
        type: 'string',
        enumType: FrequenceRappel::class,
        options: ['default' => 'hebdomadaire'],
        columnDefinition: "ENUM('jamais','hebdomadaire','mensuel') NOT NULL DEFAULT 'hebdomadaire'",
    )]
    private FrequenceRappel $frequenceRappel = FrequenceRappel::Hebdomadaire;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private Utilisateur $utilisateur;

    public function getId(): ?int { return $this->id; }

    public function getTypeNotification(): TypeNotification { return $this->typeNotification; }
    public function setTypeNotification(TypeNotification $type): self { $this->typeNotification = $type; return $this; }

    public function isEstActive(): bool { return $this->estActive; }
    public function setEstActive(bool $estActive): self { $this->estActive = $estActive; return $this; }

    public function isCanalEmail(): bool { return $this->canalEmail; }
    public function setCanalEmail(bool $canalEmail): self { $this->canalEmail = $canalEmail; return $this; }

    public function getFrequenceRappel(): FrequenceRappel { return $this->frequenceRappel; }
    public function setFrequenceRappel(FrequenceRappel $freq): self { $this->frequenceRappel = $freq; return $this; }

    public function getUtilisateur(): Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(Utilisateur $utilisateur): self { $this->utilisateur = $utilisateur; return $this; }
}
