<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PreferencesUtilisateur;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;

final class PreferencesService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Returns the user's preferences, creating a default row on first access (lazy init).
     */
    public function getOrCreate(Utilisateur $user): PreferencesUtilisateur
    {
        $prefs = $user->getPreferences();

        if ($prefs === null) {
            $prefs = (new PreferencesUtilisateur())
                ->setUtilisateur($user);
            $user->setPreferences($prefs);
            $this->em->persist($prefs);
            $this->em->flush();
        }

        return $prefs;
    }

    /**
     * @param array{notifications_email?: bool, notifications_push?: bool} $data
     */
    public function update(Utilisateur $user, array $data): PreferencesUtilisateur
    {
        $prefs = $this->getOrCreate($user);

        if (isset($data['notifications_email'])) {
            $prefs->setNotificationsEmail((bool) $data['notifications_email']);
        }
        if (isset($data['notifications_push'])) {
            $prefs->setNotificationsPush((bool) $data['notifications_push']);
        }

        $prefs->setDateModification(new \DateTimeImmutable());
        $this->em->flush();

        return $prefs;
    }
}
