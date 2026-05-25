<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\SplitCalculatorService;
use PHPUnit\Framework\TestCase;

final class SplitCalculatorServiceTest extends TestCase
{
    private SplitCalculatorService $service;

    protected function setUp(): void
    {
        $this->service = new SplitCalculatorService();
    }

    public function testEqualSplitDivisible(): void
    {
        // 30 / 3 = 10.00 each, no surplus
        $result = $this->service->calculateEqual('30.00', [1, 2, 3]);

        self::assertSame('10.00', $result[1]);
        self::assertSame('10.00', $result[2]);
        self::assertSame('10.00', $result[3]);
        self::assertSame('30.00', $this->sumParts($result));
    }

    public function testEqualSplitWithSurplusGoesToLast(): void
    {
        // 10 / 3 = 3.33 + 3.33 + 3.34 (surplus 0.01 to last = payeur per §6.3.2)
        $result = $this->service->calculateEqual('10.00', [1, 2, 3]);

        self::assertSame('3.33', $result[1]);
        self::assertSame('3.33', $result[2]);
        self::assertSame('3.34', $result[3]);
        self::assertSame('10.00', $this->sumParts($result));
    }

    public function testSingleBeneficiaire(): void
    {
        // 1 beneficiaire = gets 100%
        $result = $this->service->calculateEqual('99.99', [42]);

        self::assertSame('99.99', $result[42]);
        self::assertSame('99.99', $this->sumParts($result));
    }

    public function testEmptyBeneficiaires(): void
    {
        $result = $this->service->calculateEqual('50.00', []);

        self::assertSame([], $result);
    }

    public function testEqualSplitTwoPersons(): void
    {
        // 1.00 / 2 = 0.50 each, no surplus
        $result = $this->service->calculateEqual('1.00', [10, 20]);

        self::assertSame('0.50', $result[10]);
        self::assertSame('0.50', $result[20]);
        self::assertSame('1.00', $this->sumParts($result));
    }

    public function testEqualSplitWithLargerSurplus(): void
    {
        // 100.00 / 3 = 33.33 + 33.33 + 33.34
        $result = $this->service->calculateEqual('100.00', [1, 2, 3]);

        self::assertSame('33.33', $result[1]);
        self::assertSame('33.33', $result[2]);
        self::assertSame('33.34', $result[3]);
        self::assertSame('100.00', $this->sumParts($result));
    }

    public function testSumAlwaysEqualsTotalForFivePersons(): void
    {
        // 7 / 5 = 1.40 each
        $result = $this->service->calculateEqual('7.00', [1, 2, 3, 4, 5]);

        self::assertSame('7.00', $this->sumParts($result));
    }

    public function testSurplusGoesToLastId(): void
    {
        // payeur (id=99) is last; verify it gets the surplus
        $result = $this->service->calculateEqual('10.00', [1, 2, 99]);

        self::assertSame('3.33', $result[1]);
        self::assertSame('3.33', $result[2]);
        self::assertSame('3.34', $result[99]);
    }

    /** @param array<int, string> $parts */
    private function sumParts(array $parts): string
    {
        $sum = '0.00';
        foreach ($parts as $part) {
            $sum = bcadd($sum, $part, 2);
        }

        return $sum;
    }
}
