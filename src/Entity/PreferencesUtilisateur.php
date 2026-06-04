<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PreferencesUtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PreferencesUtilisateurRepository::class)]
#[ORM\Table(name: 'preferences_utilisateur')]
class PreferencesUtilisateur
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Utilisateur::class, inversedBy: 'preferences')]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private Utilisateur $utilisateur;

    #[ORM\Column(name: 'notifications_email', type: 'boolean', options: ['default' => true])]
    private bool $notificationsEmail = true;

    #[ORM\Column(name: 'notifications_push', type: 'boolean', options: ['default' => true])]
    private bool $notificationsPush = true;

    #[ORM\Column(name: 'date_modification', type: 'datetime_immutable')]
    private \DateTimeInterface $dateModification;

    public function __construct()
    {
        $this->dateModification = new \DateTimeImmutable();
    }

    public function getUtilisateur(): Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(Utilisateur $utilisateur): self { $this->utilisateur = $utilisateur; return $this; }

    public function isNotificationsEmail(): bool { return $this->notificationsEmail; }
    public function setNotificationsEmail(bool $value): self { $this->notificationsEmail = $value; return $this; }

    public function isNotificationsPush(): bool { return $this->notificationsPush; }
    public function setNotificationsPush(bool $value): self { $this->notificationsPush = $value; return $this; }

    public function getDateModification(): \DateTimeInterface { return $this->dateModification; }
    public function setDateModification(\DateTimeInterface $date): self { $this->dateModification = $date; return $this; }
}
