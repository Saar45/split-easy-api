<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Appartenir;
use App\Entity\Depense;
use App\Entity\Repartir;
use App\Entity\Utilisateur;
use App\Service\UserDataExportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class UserDataExportServiceTest extends TestCase
{
    public function testExportIncludesCguConsentTimestamp(): void
    {
        $user = new Utilisateur();
        $user->setNom('Sarr');
        $user->setPrenom('Mouhamed');
        $user->setEmail('mouhamed@test.com');
        $user->setCguAccepteesLe(new \DateTimeImmutable('2026-01-15T09:30:00+00:00'));

        $emptyRepo = $this->createMock(EntityRepository::class);
        $emptyRepo->method('findBy')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [Appartenir::class, $emptyRepo],
            [Depense::class, $emptyRepo],
            [Repartir::class, $emptyRepo],
        ]);

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em->method('createQueryBuilder')->willReturn($qb);

        $service = new UserDataExportService($em);
        $payload = $service->export($user);

        self::assertSame(
            '2026-01-15T09:30:00+00:00',
            $payload['utilisateur']['cgu_acceptees_le'],
        );
    }
}
