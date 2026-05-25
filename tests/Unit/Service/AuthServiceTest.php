<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\RegisterDto;
use App\Entity\Utilisateur;
use App\Exception\EmailAlreadyTakenException;
use App\Repository\UtilisateurRepository;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthServiceTest extends TestCase
{
    public function testRegisterUserHashesPasswordAndPersists(): void
    {
        $repo = $this->createMock(UtilisateurRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects(self::once())
            ->method('hashPassword')
            ->with(self::isInstanceOf(Utilisateur::class), 'SecurePass1')
            ->willReturn('$argon2id$hashed$');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Utilisateur::class));
        $em->expects(self::once())->method('flush');

        $service = new AuthService($repo, $hasher, $em, new NullLogger());

        $dto = new RegisterDto('Sarr', 'Mouhamed', 'mouhamed@test.com', 'SecurePass1', true);
        $user = $service->registerUser($dto);

        self::assertSame('Sarr', $user->getNom());
        self::assertSame('mouhamed@test.com', $user->getEmail());
        self::assertSame('$argon2id$hashed$', $user->getMotDePasse());
        self::assertFalse($user->isEmailVerifie());
    }

    public function testRegisterThrowsWhenEmailAlreadyExists(): void
    {
        $existing = new Utilisateur();
        $repo = $this->createMock(UtilisateurRepository::class);
        $repo->method('findOneBy')->willReturn($existing);

        $service = new AuthService(
            $repo,
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(EntityManagerInterface::class),
            new NullLogger(),
        );

        $this->expectException(EmailAlreadyTakenException::class);
        $service->registerUser(new RegisterDto('A', 'B', 'taken@test.com', 'SecurePass1', true));
    }
}
