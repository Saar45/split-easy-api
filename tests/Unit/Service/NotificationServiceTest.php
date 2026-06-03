<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Notification;
use App\Entity\Utilisateur;
use App\Enum\TypeNotification;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class NotificationServiceTest extends TestCase
{
    public function testCreatePersistsAndFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Notification::class));
        $em->expects(self::once())->method('flush');

        $repo = $this->createMock(NotificationRepository::class);
        $service = new NotificationService($em, $repo);

        $user = $this->makeUser();
        $notif = $service->create(
            $user,
            TypeNotification::DepenseAjoutee,
            'Titre',
            'Message complet',
            'depense',
            42,
        );

        self::assertSame(TypeNotification::DepenseAjoutee, $notif->getTypeNotification());
        self::assertSame('Titre', $notif->getTitre());
        self::assertSame('Message complet', $notif->getMessage());
        self::assertSame('depense', $notif->getReferenceType());
        self::assertSame(42, $notif->getReferenceId());
        self::assertFalse($notif->isEstLu());
        self::assertSame($user, $notif->getDestinataire());
    }

    public function testMarkAsReadFlipsFlagAndSetsDate(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $repo = $this->createMock(NotificationRepository::class);
        $service = new NotificationService($em, $repo);

        $notif = (new Notification())
            ->setDestinataire($this->makeUser())
            ->setTypeNotification(TypeNotification::DepenseAjoutee)
            ->setTitre('t')
            ->setMessage('m');

        $service->markAsRead($notif);

        self::assertTrue($notif->isEstLu());
        self::assertNotNull($notif->getDateLecture());
    }

    public function testMarkAsReadNoopWhenAlreadyRead(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        $repo = $this->createMock(NotificationRepository::class);
        $service = new NotificationService($em, $repo);

        $notif = (new Notification())
            ->setDestinataire($this->makeUser())
            ->setTypeNotification(TypeNotification::DepenseAjoutee)
            ->setTitre('t')
            ->setMessage('m')
            ->setEstLu(true);

        $service->markAsRead($notif);

        self::assertTrue($notif->isEstLu());
    }

    public function testMarkAllAsReadDelegatesToRepository(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(NotificationRepository::class);
        $repo->expects(self::once())->method('markAllReadFor')->willReturn(7);

        $service = new NotificationService($em, $repo);

        self::assertSame(7, $service->markAllAsRead($this->makeUser()));
    }

    public function testListForUserBoundsLimit(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(NotificationRepository::class);
        $repo->expects(self::once())
            ->method('listForUser')
            ->with(self::isInstanceOf(Utilisateur::class), true, 200)
            ->willReturn([]);

        $service = new NotificationService($em, $repo);
        $service->listForUser($this->makeUser(), true, 9999);
    }

    public function testCountUnreadDelegates(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(NotificationRepository::class);
        $repo->expects(self::once())->method('countUnread')->willReturn(3);
        $service = new NotificationService($em, $repo);

        self::assertSame(3, $service->countUnread($this->makeUser()));
    }

    private function makeUser(): Utilisateur
    {
        $u = new Utilisateur();
        $u->setNom('N')->setPrenom('P')->setEmail('u@test.com')->setMotDePasse('x');

        return $u;
    }
}
