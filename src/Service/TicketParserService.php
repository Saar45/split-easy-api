<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Extrait montant, date et commerçant d'un texte brut OCR de ticket de caisse.
 *
 * Service pur (aucun appel réseau, aucune dépendance) pour rester testable
 * indépendamment du fournisseur OCR (OCR.space).
 */
final class TicketParserService
{
    /**
     * Ancres de montant total par ordre de priorité décroissante.
     * "TOTAL" est volontairement en dernier car trop générique (matcherait aussi
     * les lignes exclues ci-dessous si elles n'étaient pas filtrées avant).
     *
     * @var string[]
     */
    private const AMOUNT_ANCHORS = [
        'NET A PAYER',
        'TOTAL TTC',
        'MONTANT DU',
        'A PAYER',
        'TOTAL',
    ];

    /**
     * Lignes à exclure de la recherche par ancre : ce sont des sous-totaux,
     * pas le montant final dû par le client.
     *
     * @var string[]
     */
    private const EXCLUDED_LINES = [
        'TOTAL HT',
        'SOUS-TOTAL',
        'SOUS TOTAL',
        'TOTAL TVA',
    ];

    private const AMOUNT_PATTERN = '/\d+[.,]\d{2}/';

    private const DATE_PATTERN = '/\b(\d{1,2})([\/\-.])(\d{1,2})\2(\d{2}|\d{4})\b/';

    /** @return array{montant: ?string, date: ?string, commercant: ?string} */
    public function parse(string $texteBrut): array
    {
        $lines = $this->splitLines($texteBrut);

        return [
            'montant' => $this->extractMontant($lines),
            'date' => $this->extractDate($texteBrut),
            'commercant' => $this->extractCommercant($lines),
        ];
    }

    /** @return string[] */
    private function splitLines(string $texteBrut): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $texteBrut) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $l) => '' !== $l));
    }

    private function normalize(string $line): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $line);

        return strtoupper(false !== $transliterated ? $transliterated : $line);
    }

    private function isExcluded(string $normalizedLine): bool
    {
        foreach (self::EXCLUDED_LINES as $excluded) {
            if (str_contains($normalizedLine, $excluded)) {
                return true;
            }
        }

        return false;
    }

    /** @param string[] $lines */
    private function extractMontant(array $lines): ?string
    {
        foreach (self::AMOUNT_ANCHORS as $anchor) {
            foreach ($lines as $line) {
                $normalized = $this->normalize($line);
                if ($this->isExcluded($normalized)) {
                    continue;
                }
                if (str_contains($normalized, $anchor) && null !== $amount = $this->extractAmountFromLine($line)) {
                    return $amount;
                }
            }
        }

        return $this->extractLargestAmount($lines);
    }

    /** @param string[] $lines */
    private function extractLargestAmount(array $lines): ?string
    {
        $largest = null;
        foreach ($lines as $line) {
            if ($this->isExcluded($this->normalize($line))) {
                continue;
            }
            if (!preg_match_all(self::AMOUNT_PATTERN, $line, $matches)) {
                continue;
            }
            foreach ($matches[0] as $match) {
                $value = (float) str_replace(',', '.', $match);
                if (null === $largest || $value > $largest) {
                    $largest = $value;
                }
            }
        }

        return null !== $largest ? number_format($largest, 2, '.', '') : null;
    }

    private function extractAmountFromLine(string $line): ?string
    {
        if (!preg_match_all(self::AMOUNT_PATTERN, $line, $matches) || [] === $matches[0]) {
            return null;
        }

        // Le montant suit généralement le libellé : on garde la dernière occurrence de la ligne.
        $lastMatch = (string) end($matches[0]);
        $value = (float) str_replace(',', '.', $lastMatch);

        return number_format($value, 2, '.', '');
    }

    private function extractDate(string $texteBrut): ?string
    {
        if (!preg_match_all(self::DATE_PATTERN, $texteBrut, $matches, \PREG_SET_ORDER)) {
            return null;
        }

        foreach ($matches as $match) {
            $day = (int) $match[1];
            $month = (int) $match[3];
            $year = (int) $match[4];
            if (2 === strlen($match[4])) {
                $year += 2000;
            }

            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        return null;
    }

    /** @param string[] $lines */
    private function extractCommercant(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (strlen($line) <= 2) {
                continue;
            }
            if (preg_match(self::DATE_PATTERN, $line)) {
                continue;
            }
            // Une ligne qui n'est qu'un montant (avec éventuellement une devise) n'est pas un commerçant.
            if (preg_match('/^[\d\s.,€EUR]+$/i', $line)) {
                continue;
            }

            return $line;
        }

        return null;
    }
}
