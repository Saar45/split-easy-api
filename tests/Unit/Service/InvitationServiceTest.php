<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Appartenir;
use App\Entity\Groupe;
use App\Entity\Utilisateur;
use App\Enum\RoleAppartenir;
use App\Enum\StatutInvitation;
use App\Repository\AppartenirRepository;
use App\Repository\UtilisateurRepository;
use App\Service\InvitationService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[AllowMockObjectsWithoutExpectations]
final class InvitationServiceTest extends TestCase
{
    public function testCreateInvitationGeneratesTokenAndSetsExpiration(): void
    {
        $creator = $this->makeUser(1, 'creator@test.com');
        $invitee = $this->makeUser(2, 'invitee@test.com');
        $groupe = $this->makeGroup($creator);

        $service = $this->makeService(
            currentUser: $creator,
            groupe: $groupe,
            findUserByEmail: $invitee,
            existingAppartenirForGroupAndInvitee: null,
        );

        $appartenir = $service->createInvitation($groupe, 'invitee@test.com', $creator);

        self::assertSame(StatutInvitation::EnAttente, $appartenir->getStatutInvitation());
        self::assertSame(RoleAppartenir::Membre, $appartenir->getRole());
        self::assertSame($invitee, $appartenir->getUtilisateur());
        self::assertNotEmpty($appartenir->getTokenInvitation());
        self::assertSame(64, strlen($appartenir->getTokenInvitation()));

        $expiration = $appartenir->getDateExpiration();
        self::assertNotNull($expiration);
        $diff = $expiration->getTimestamp() - (new \DateTimeImmutable())->getTimestamp();
        // Tolerance : 7 days +- 60 seconds for clock skew.
        self::assertGreaterThan(7 * 86400 - 60, $diff);
        self::assertLessThan(7 * 86400 + 60, $diff);
    }

    public function testCreateInvitationDeniesNonCreator(): void
    {
        $creator = $this->makeUser(1, 'creator@test.com');
        $nonCreator = $this->makeUser(3, 'other@test.com');
        $invitee = $this->makeUser(2, 'invitee@test.com');
        $groupe = $this->makeGroup($creator);

        $service = $this->makeService(
            currentUser: $nonCreator,
            groupe: $groupe,
            findUserByEmail: $invitee,
            existingAppartenirForGroupAndInvitee: null,
            actualCreator: $creator,
        );

        $this->expectException(AccessDeniedHttpException::class);
        $service->createInvitation($groupe, 'invitee@test.com', $nonCreator);
    }

    public function testCreateInvitationRequiresExistingUser(): void
    {
        $creator = $this->makeUser(1, 'creator@test.com');
        $groupe = $this->makeGroup($creator);

        $service = $this->makeService(
            currentUser: $creator,
            groupe: $groupe,
            findUserByEmail: null,
            existingAppartenirForGroupAndInvitee: null,
        );

        $this->expectException(UnprocessableEntityHttpException::class);
        $service->createInvitation($groupe, 'ghost@test.com', $creator);
    }

    public function testCreateInvitationRejectsExistingActiveMembership(): void
    {
        $creator = $this->makeUser(1, 'creator@test.com');
        $invitee = $this->makeUser(2, 'invitee@test.com');
        $groupe = $this->makeGroup($creator);

        $existing = (new Appartenir())
            ->setUtilisateur($invitee)
            ->setGroupe($groupe)
            ->setStatutInvitation(StatutInvitation::Acceptee)
            ->setTokenInvitation('whatever');

        $service = $this->makeService(
            currentUser: $creator,
            groupe: $groupe,
            findUserByEmail: $invitee,
            existingAppartenirForGroupAndInvitee: $existing,
        );

        $this->expectException(ConflictHttpException::class);
        $service->createInvitation($groupe, 'invitee@test.com', $creator);
    }

    public function testAcceptInvitationRejectsWrongUser(): void
    {
        $creator = $this->makeUser(1, 'creator@test.com');
        $invitee = $this->makeUser(2, 'invitee@test.com');
        $intruder = $this->makeUser(3, 'intruder@test.com');
        $groupe = $this->makeGroup($creator);

        $appartenir = (new Appartenir())
            ->setUtilisateur($invitee)
            ->setGroupe($groupe)
            ->setStatutInvitation(StatutInvitation::EnAttente)
            ->setTokenInvitation('abc123')
            ->setDateExpiration(new \DateTimeImmutable('+5 days'));

        $service = $this->makeService(
            currentUser: $intruder,
            groupe: $groupe,
            findUserByEmail: null,
            existingAppartenirForGroupAndInvitee: null,
            findByToken: $appartenir,
        );

        $this->expectException(AccessDeniedHttpException::class);
        $service->acceptInvitation('abc123', $intruder);
    }

    public function testAcceptInvitationRejectsExpired(): void
    {
        $invitee = $this->makeUser(2, 'invitee@test.com');
        $groupe = $this->makeGroup($this->makeUser(1, 'creator@test.com'));

        $appartenir = (new Appartenir())
            ->setUtilisateur($invitee)
            ->setGroupe($groupe)
            ->setStatutInvitation(StatutInvitation::EnAttente)
            ->setTokenInvitation('abc123')
            ->setDateExpiration(new \DateTimeImmutable('-1 day'));

        $service = $this->makeService(
            currentUser: $invitee,
            groupe: $groupe,
            findUserByEmail: null,
            existingAppartenirForGroupAndInvitee: null,
            findByToken: $appartenir,
        );

        $this->expectException(GoneHttpException::class);
        $service->acceptInvitation('abc123', $invitee);
    }

    public function testAcceptInvitationSucceedsAndSetsDates(): void
    {
        $invitee = $this->makeUser(2, 'invitee@test.com');
        $groupe = $this->makeGroup($this->makeUser(1, 'creator@test.com'));

        $appartenir = (new Appartenir())
            ->setUtilisateur($invitee)
            ->setGroupe($groupe)
            ->setStatutInvitation(StatutInvitation::EnAttente)
            ->setTokenInvitation('abc123')
            ->setDateExpiration(new \DateTimeImmutable('+5 days'));

        $service = $this->makeService(
            currentUser: $invitee,
            groupe: $groupe,
            findUserByEmail: null,
            existingAppartenirForGroupAndInvitee: null,
            findByToken: $appartenir,
        );

        $result = $service->acceptInvitation('abc123', $invitee);

        self::assertSame(StatutInvitation::Acceptee, $result->getStatutInvitation());
        self::assertNotNull($result->getDateAcceptation());
        self::assertNotNull($result->getDateAdhesion());
    }

    public function testAcceptInvitationRejectsTokenNotFound(): void
    {
        $invitee = $this->makeUser(2, 'invitee@test.com');

        $service = $this->makeService(
            currentUser: $invitee,
            groupe: null,
            findUserByEmail: null,
            existingAppartenirForGroupAndInvitee: null,
            findByToken: null,
        );

        $this->expectException(NotFoundHttpException::class);
        $service->acceptInvitation('does-not-exist', $invitee);
    }

    private function makeUser(int $id, string $email): Utilisateur
    {
        $u = new Utilisateur();
        $u->setNom('Doe')->setPrenom('Jane')->setEmail($email)->setMotDePasse('x');
        $ref = new \ReflectionClass($u);
        $prop = $ref->getProperty('id');
        $prop->setValue($u, $id);

        return $u;
    }

    private function makeGroup(Utilisateur $creator): Groupe
    {
        $g = new Groupe();
        $g->setNom('Test')->setDescription(null)->setCouleur(null);

        return $g;
    }

    private function makeService(
        Utilisateur $currentUser,
        ?Groupe $groupe,
        ?Utilisateur $findUserByEmail,
        ?Appartenir $existingAppartenirForGroupAndInvitee,
        ?Appartenir $findByToken = null,
        ?Utilisateur $actualCreator = null,
    ): InvitationService {
        // Par défaut, le créateur du groupe est le currentUser (cas créateur). On peut overrider
        // pour simuler un currentUser non-créateur.
        $creator = $actualCreator ?? $currentUser;

        $em = $this->createMock(EntityManagerInterface::class);

        $appartenirRepo = $this->createMock(AppartenirRepository::class);
        $appartenirRepo->method('findOneBy')->willReturnCallback(
            function (array $criteria) use ($groupe, $creator, $existingAppartenirForGroupAndInvitee, $findByToken) {
                if (isset($criteria['tokenInvitation'])) {
                    return $findByToken;
                }

                if (isset($criteria['groupe'], $criteria['utilisateur'], $criteria['statutInvitation'])) {
                    $isCreator = $groupe !== null
                        && $criteria['utilisateur'] === $creator
                        && $criteria['statutInvitation'] === StatutInvitation::Acceptee;
                    if (!$isCreator) {
                        return null;
                    }
                    $a = new Appartenir();
                    $a->setUtilisateur($creator)
                        ->setGroupe($groupe)
                        ->setRole(RoleAppartenir::Createur)
                        ->setStatutInvitation(StatutInvitation::Acceptee)
                        ->setTokenInvitation('x');

                    return $a;
                }

                if (isset($criteria['groupe'], $criteria['utilisateur'])) {
                    return $existingAppartenirForGroupAndInvitee;
                }

                return null;
            }
        );

        $userRepo = $this->createMock(UtilisateurRepository::class);
        $userRepo->method('findOneBy')->willReturn($findUserByEmail);

        $notifications = $this->createMock(NotificationService::class);

        return new InvitationService($em, $appartenirRepo, $userRepo, $notifications);
    }
}
