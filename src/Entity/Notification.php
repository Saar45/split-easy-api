<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TypeNotification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Référence polymorphe : reference_type + reference_id pointent vers Depense, Groupe,
 * Remboursement ou Appartenir selon le contexte. Aucune FK n'est posée sur reference_id —
 * l'intégrité référentielle est garantie par la couche Service (voir NotificationService).
 * Point de défense jury n°3 (§6.6.2 du dossier).
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
#[ORM\Index(name: 'idx_notification_utilisateur', columns: ['id_utilisateur'])]
#[ORM\Index(name: 'idx_notification_lecture', columns: ['id_utilisateur', 'est_lu'])]
#[ORM\Index(name: 'idx_notification_reference', columns: ['reference_type', 'reference_id'])]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_notification', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'type_notification', type: 'string', length: 75, enumType: TypeNotification::class)]
    private TypeNotification $typeNotification;

    #[ORM\Column(name: 'titre', type: 'string', length: 150)]
    private string $titre;

    #[ORM\Column(name: 'message', type: 'text')]
    private string $message;

    #[ORM\Column(name: 'est_lu', type: 'boolean', options: ['default' => false])]
    private bool $estLu = false;

    #[ORM\Column(name: 'date_creation', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(name: 'date_lecture', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $dateLecture = null;

    #[ORM\Column(name: 'reference_type', type: 'string', length: 50, nullable: true)]
    private ?string $referenceType = null;

    #[ORM\Column(name: 'reference_id', type: 'integer', nullable: true)]
    private ?int $referenceId = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private Utilisateur $destinataire;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getTypeNotification(): TypeNotification { return $this->typeNotification; }
    public function setTypeNotification(TypeNotification $type): self { $this->typeNotification = $type; return $this; }

    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $titre): self { $this->titre = $titre; return $this; }

    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): self { $this->message = $message; return $this; }

    public function isEstLu(): bool { return $this->estLu; }
    public function setEstLu(bool $estLu): self
    {
        $this->estLu = $estLu;
        $this->dateLecture = $estLu ? new \DateTimeImmutable() : null;
        return $this;
    }

    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }
    public function getDateLecture(): ?\DateTimeInterface { return $this->dateLecture; }

    public function getReferenceType(): ?string { return $this->referenceType; }
    public function setReferenceType(?string $referenceType): self { $this->referenceType = $referenceType; return $this; }

    public function getReferenceId(): ?int { return $this->referenceId; }
    public function setReferenceId(?int $referenceId): self { $this->referenceId = $referenceId; return $this; }

    public function getDestinataire(): Utilisateur { return $this->destinataire; }
    public function setDestinataire(Utilisateur $destinataire): self { $this->destinataire = $destinataire; return $this; }
}
