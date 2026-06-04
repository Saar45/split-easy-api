<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Appartenir;
use App\Entity\Depense;
use App\Entity\Groupe;
use App\Entity\Repartir;
use App\Entity\Utilisateur;
use App\Repository\AppartenirRepository;
use App\Repository\DepenseRepository;
use App\Repository\RemboursementRepository;
use App\Repository\RepartirRepository;
use App\Service\DebtOptimizerService;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class DebtOptimizerServiceTest extends TestCase
{
    public function testThreeMembersOneSpenderProducesTwoTransactions(): void
    {
        $alice = $this->makeUser(1, 'Alice', 'A');
        $bob = $this->makeUser(2, 'Bob', 'B');
        $carol = $this->makeUser(3, 'Carol', 'C');
        $groupe = $this->makeGroupe(10);

        // Alice paie 30 ; split equitable 3 -> 10 chacun. Soldes : A +20, B -10, C -10.
        $depense = $this->makeDepense(100, $groupe, $alice, '30.00');
        $repartitions = [
            $this->makeRepartir($alice, $depense, '10.00'),
            $this->makeRepartir($bob, $depense, '10.00'),
            $this->makeRepartir($carol, $depense, '10.00'),
        ];

        $service = $this->buildService(
            members: [$alice, $bob, $carol],
            depenses: [$depense],
            repartitions: $repartitions,
            remboursementsValides: [],
            groupe: $groupe,
        );

        $result = $service->computeForGroup($groupe);

        self::assertCount(3, $result['soldes']);
        $balanceById = [];
        foreach ($result['soldes'] as $s) {
            $balanceById[$s['user']['id']] = $s['balance'];
        }
        self::assertSame('20.00', $balanceById[1]);
        self::assertSame('-10.00', $balanceById[2]);
        self::assertSame('-10.00', $balanceById[3]);

        self::assertCount(2, $result['remboursements']);
        foreach ($result['remboursements'] as $r) {
            self::assertSame(1, $r['to']['id']);
            self::assertContains($r['from']['id'], [2, 3]);
            self::assertSame('10.00', $r['montant']);
        }
    }

    public function testFiveMembersComplexConvergence(): void
    {
        $u = [];
        for ($i = 1; $i <= 5; ++$i) {
            $u[$i] = $this->makeUser($i, 'U'.$i, 'L');
        }
        $groupe = $this->makeGroupe(11);

        // Soldes ciblés : +40, +10, -20, -25, -5 (somme = 0).
        // Depense 1 : U1 paie 50, bénéficiaires {U1:10, U3:20, U4:20}. Solde U1=+40, U3=-20, U4=-20.
        $d1 = $this->makeDepense(1, $groupe, $u[1], '50.00');
        $r1 = [
            $this->makeRepartir($u[1], $d1, '10.00'),
            $this->makeRepartir($u[3], $d1, '20.00'),
            $this->makeRepartir($u[4], $d1, '20.00'),
        ];
        // Depense 2 : U2 paie 10, bénéficiaires {U4:5, U5:5}. Solde U2=+10, U4=-5, U5=-5.
        $d2 = $this->makeDepense(2, $groupe, $u[2], '10.00');
        $r2 = [
            $this->makeRepartir($u[4], $d2, '5.00'),
            $this->makeRepartir($u[5], $d2, '5.00'),
        ];

        $service = $this->buildService(
            members: array_values($u),
            depenses: [$d1, $d2],
            repartitions: array_merge($r1, $r2),
            remboursementsValides: [],
            groupe: $groupe,
        );

        $result = $service->computeForGroup($groupe);

        $balanceById = [];
        foreach ($result['soldes'] as $s) {
            $balanceById[$s['user']['id']] = $s['balance'];
        }
        self::assertSame('40.00', $balanceById[1]);
        self::assertSame('10.00', $balanceById[2]);
        self::assertSame('-20.00', $balanceById[3]);
        self::assertSame('-25.00', $balanceById[4]);
        self::assertSame('-5.00', $balanceById[5]);

        // n - 1 = 4 transactions maximum, on espère moins grâce au greedy.
        self::assertLessThanOrEqual(4, count($result['remboursements']));
        self::assertGreaterThanOrEqual(1, count($result['remboursements']));

        // Vérifie l'invariant : somme nette par membre = solde initial (les remboursements doivent solder).
        $netti = $balanceById;
        foreach ($result['remboursements'] as $tx) {
            $netti[$tx['from']['id']] = bcadd($netti[$tx['from']['id']], $tx['montant'], 2);
            $netti[$tx['to']['id']] = bcsub($netti[$tx['to']['id']], $tx['montant'], 2);
        }
        foreach ($netti as $id => $bal) {
            self::assertSame('0.00', $bal, 'Solde '.$id.' non équilibré');
        }
    }

    public function testAllBalancedNoTransactions(): void
    {
        $alice = $this->makeUser(1, 'Alice', 'A');
        $bob = $this->makeUser(2, 'Bob', 'B');
        $groupe = $this->makeGroupe(12);

        // Alice paie 10, bénéficiaire = elle-même. Aucune dette.
        $depense = $this->makeDepense(99, $groupe, $alice, '10.00');
        $repartitions = [$this->makeRepartir($alice, $depense, '10.00')];

        $service = $this->buildService(
            members: [$alice, $bob],
            depenses: [$depense],
            repartitions: $repartitions,
            remboursementsValides: [],
            groupe: $groupe,
        );

        $result = $service->computeForGroup($groupe);

        self::assertCount(2, $result['soldes']);
        self::assertCount(0, $result['remboursements']);
        foreach ($result['soldes'] as $s) {
            self::assertSame('0.00', $s['balance']);
        }
    }

    public function testEmptyGroupNoExpenseReturnsEmpty(): void
    {
        $groupe = $this->makeGroupe(13);
        $service = $this->buildService(
            members: [],
            depenses: [],
            repartitions: [],
            remboursementsValides: [],
            groupe: $groupe,
        );

        $result = $service->computeForGroup($groupe);
        self::assertSame([], $result['soldes']);
        self::assertSame([], $result['remboursements']);
    }

    /**
     * @param Utilisateur[]     $members
     * @param Depense[]         $depenses
     * @param Repartir[]        $repartitions
     * @param array<int, mixed> $remboursementsValides
     */
    private function buildService(
        array $members,
        array $depenses,
        array $repartitions,
        array $remboursementsValides,
        Groupe $groupe,
    ): DebtOptimizerService {
        $appartenirRepo = $this->createMock(AppartenirRepository::class);
        $appartenances = array_map(function (Utilisateur $u) use ($groupe) {
            $a = new Appartenir();
            $a->setUtilisateur($u);
            $a->setGroupe($groupe);
            $a->setTokenInvitation('t'.$u->getId());

            return $a;
        }, $members);
        $appartenirRepo->method('findBy')->willReturn($appartenances);

        $depenseRepo = $this->createMock(DepenseRepository::class);
        $depenseRepo->method('findBy')->willReturn($depenses);

        $repartirRepo = $this->createMock(RepartirRepository::class);
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMock();
        $query->method('getResult')->willReturn($repartitions);
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $repartirRepo->method('createQueryBuilder')->willReturn($qb);

        $rbRepo = $this->createMock(RemboursementRepository::class);
        $rbRepo->method('findBy')->willReturn($remboursementsValides);

        return new DebtOptimizerService($appartenirRepo, $depenseRepo, $repartirRepo, $rbRepo);
    }

    private function makeUser(int $id, string $prenom, string $nom): Utilisateur
    {
        $u = new Utilisateur();
        $u->setEmail($prenom.'@test.com');
        $u->setNom($nom);
        $u->setPrenom($prenom);
        $u->setMotDePasse('x');

        $ref = new \ReflectionClass(Utilisateur::class);
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($u, $id);

        return $u;
    }

    private function makeGroupe(int $id): Groupe
    {
        $g = new Groupe();
        $g->setNom('G'.$id);

        $ref = new \ReflectionClass(Groupe::class);
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($g, $id);

        return $g;
    }

    private function makeDepense(int $id, Groupe $g, Utilisateur $payeur, string $montant): Depense
    {
        $d = new Depense();
        $d->setDescription('test');
        $d->setMontant($montant);
        $d->setGroupe($g);
        $d->setPayeur($payeur);

        $ref = new \ReflectionClass(Depense::class);
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($d, $id);

        return $d;
    }

    private function makeRepartir(Utilisateur $u, Depense $d, string $part): Repartir
    {
        $r = new Repartir();
        $r->setBeneficiaire($u);
        $r->setDepense($d);
        $r->setMontantPart($part);

        return $r;
    }
}
