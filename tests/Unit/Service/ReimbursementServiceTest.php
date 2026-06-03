<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Groupe;
use App\Entity\Remboursement;
use App\Entity\Utilisateur;
use App\Enum\StatutRemboursement;
use App\Service\NotificationService;
use App\Service\ReimbursementService;
use App\Repository\AppartenirRepository;
use App\Repository\RemboursementRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

#[AllowMockObjectsWithoutExpectations]
final class ReimbursementServiceTest extends TestCase
{
    public function testAcceptTransitionsProposeToValide(): void
    {
        $service = $this->makeService();
        $rb = $this->makeRb(StatutRemboursement::Propose);

        $updated = $service->accept($rb);

        self::assertSame(StatutRemboursement::Valide, $updated->getStatut());
        self::assertNotNull($updated->getDateValidation());
    }

    public function testRejectTransitionsProposeToConteste(): void
    {
        $service = $this->makeService();
        $rb = $this->makeRb(StatutRemboursement::Propose);

        $updated = $service->reject($rb);

        self::assertSame(StatutRemboursement::Conteste, $updated->getStatut());
        self::assertNull($updated->getDateValidation());
    }

    public function testCancelTransitionsProposeToAnnule(): void
    {
        $service = $this->makeService();
        $rb = $this->makeRb(StatutRemboursement::Propose);

        $updated = $service->cancel($rb);

        self::assertSame(StatutRemboursement::Annule, $updated->getStatut());
    }

    public function testCannotAcceptAlreadyValidatedRejects409(): void
    {
        $service = $this->makeService();
        $rb = $this->makeRb(StatutRemboursement::Valide);

        $this->expectException(ConflictHttpException::class);
        $service->accept($rb);
    }

    public function testCannotRejectAnnuleRejects409(): void
    {
        $service = $this->makeService();
        $rb = $this->makeRb(StatutRemboursement::Annule);

        $this->expectException(ConflictHttpException::class);
        $service->reject($rb);
    }

    public function testCannotCancelContesteRejects409(): void
    {
        $service = $this->makeService();
        $rb = $this->makeRb(StatutRemboursement::Conteste);

        $this->expectException(ConflictHttpException::class);
        $service->cancel($rb);
    }

    private function makeService(): ReimbursementService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $rbRepo = $this->createMock(RemboursementRepository::class);
        $appRepo = $this->createMock(AppartenirRepository::class);
        $userRepo = $this->createMock(UtilisateurRepository::class);
        $notif = $this->createMock(NotificationService::class);

        return new ReimbursementService($em, $rbRepo, $appRepo, $userRepo, $notif);
    }

    private function makeRb(StatutRemboursement $status): Remboursement
    {
        $debiteur = (new Utilisateur())->setNom('D')->setPrenom('Dette')->setEmail('d@t.com')->setMotDePasse('x');
        $crediteur = (new Utilisateur())->setNom('C')->setPrenom('Cred')->setEmail('c@t.com')->setMotDePasse('x');
        $groupe = (new Groupe())->setNom('G');

        return (new Remboursement())
            ->setGroupe($groupe)
            ->setDebiteur($debiteur)
            ->setCrediteur($crediteur)
            ->setMontant('10.00')
            ->setStatut($status);
    }
}
