<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TicketParserService;
use PHPUnit\Framework\TestCase;

final class TicketParserServiceTest extends TestCase
{
    private TicketParserService $service;

    protected function setUp(): void
    {
        $this->service = new TicketParserService();
    }

    public function testTotalTtcAnchorIsPreferredOverItemLines(): void
    {
        $texte = <<<TICKET
            CARREFOUR CITY
            12 RUE DE PARIS
            Pain            1.20
            Lait            0.95
            TOTAL HT       18.50
            TVA 5.5%        1.02
            TOTAL TTC      19.52 EUR
            Merci de votre visite
            15/07/2026
            TICKET;

        $result = $this->service->parse($texte);

        self::assertSame('19.52', $result['montant']);
        self::assertSame('2026-07-15', $result['date']);
        self::assertSame('CARREFOUR CITY', $result['commercant']);
    }

    public function testNetAPayerHasTopPriorityOverTotalTtc(): void
    {
        $texte = <<<TICKET
            MONOPRIX
            SOUS-TOTAL      8.50
            TOTAL TTC       9.90
            NET A PAYER     8.50 EUR
            01/03/25
            TICKET;

        $result = $this->service->parse($texte);

        self::assertSame('8.50', $result['montant']);
        self::assertSame('2025-03-01', $result['date']);
    }

    public function testTotalHtIsNeverPickedAsTotalAnchor(): void
    {
        $texte = <<<TICKET
            CARREFOUR MARKET
            TOTAL HT       18.50
            TVA 20%         3.70
            TOTAL          22.20
            TICKET;

        $result = $this->service->parse($texte);

        self::assertSame('22.20', $result['montant']);
    }

    public function testNoAnchorFallsBackToLargestPlausibleAmount(): void
    {
        $texte = <<<TICKET
            BOULANGERIE DUPONT
            Baguette        1.10
            Croissant       1.30
            Merci de votre visite
            TICKET;

        $result = $this->service->parse($texte);

        self::assertSame('1.30', $result['montant']);
        self::assertSame('BOULANGERIE DUPONT', $result['commercant']);
    }

    public function testFrenchDecimalCommaIsNormalizedToDot(): void
    {
        $texte = "E.LECLERC\nTOTAL          23,45\n";

        $result = $this->service->parse($texte);

        self::assertSame('23.45', $result['montant']);
    }

    public function testDdMmYySlashDateIsNormalized(): void
    {
        $result = $this->service->parse("TICKET\nDate: 05/09/25\n");

        self::assertSame('2025-09-05', $result['date']);
    }

    public function testDashDateFormatIsNormalized(): void
    {
        $result = $this->service->parse("TICKET\n15-07-2026\n");

        self::assertSame('2026-07-15', $result['date']);
    }

    public function testDotDateFormatIsNormalized(): void
    {
        $result = $this->service->parse("TICKET\n15.07.2026\n");

        self::assertSame('2026-07-15', $result['date']);
    }

    public function testInvalidDateIsIgnored(): void
    {
        $result = $this->service->parse("TICKET\n32/13/2026\n");

        self::assertNull($result['date']);
    }

    public function testMontantDuAnchorIsUsedWhenNoHigherPriorityAnchor(): void
    {
        $texte = <<<TICKET
            RESTAURANT LE PETIT COIN
            MONTANT DU     45.00
            TICKET;

        $result = $this->service->parse($texte);

        self::assertSame('45.00', $result['montant']);
    }

    public function testAPayerAnchorIsUsedWhenNoHigherPriorityAnchor(): void
    {
        $texte = <<<TICKET
            STATION TOTAL ENERGIES
            A PAYER        60.00
            TICKET;

        $result = $this->service->parse($texte);

        self::assertSame('60.00', $result['montant']);
    }

    public function testEmptyTextReturnsAllNullFields(): void
    {
        $result = $this->service->parse('');

        self::assertNull($result['montant']);
        self::assertNull($result['date']);
        self::assertNull($result['commercant']);
    }

    public function testBlankTextReturnsAllNullFields(): void
    {
        $result = $this->service->parse("   \n\n   ");

        self::assertNull($result['montant']);
        self::assertNull($result['date']);
        self::assertNull($result['commercant']);
    }
}
