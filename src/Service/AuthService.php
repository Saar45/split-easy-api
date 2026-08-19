<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\RegisterDto;
use App\Entity\Utilisateur;
use App\Exception\EmailAlreadyTakenException;
use App\Repository\UtilisateurRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthService
{
    public function __construct(
        private readonly UtilisateurRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @throws EmailAlreadyTakenException */
    public function registerUser(RegisterDto $dto): Utilisateur
    {
        if (null !== $this->userRepository->findOneBy(['email' => $dto->email])) {
            throw new EmailAlreadyTakenException($dto->email);
        }

        $user = new Utilisateur();
        $user->setNom($dto->nom);
        $user->setPrenom($dto->prenom);
        $user->setEmail($dto->email);
        $user->setMotDePasse($this->passwordHasher->hashPassword($user, $dto->motDePasse));
        $user->setCguAccepteesLe(new \DateTimeImmutable());

        $this->em->persist($user);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            // Filet de sécurité contre la race condition entre findOneBy() et flush().
            throw new EmailAlreadyTakenException($dto->email, $e);
        }

        $this->logger->info('User registered', ['email' => $user->getEmail(), 'id' => $user->getId()]);

        return $user;
    }
}
