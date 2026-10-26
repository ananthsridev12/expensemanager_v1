<?php

namespace Parsers;

/**
 * Generic fallback CSV parser.
 *
 * Detects any tabular statement that has date, description, and
 * debit/credit columns by scanning header names case-insensitively.
 * Loaded last (Z prefix) so specific bank parsers take priority.
 */
class ZGenericCsvParser implements BankParserInterface
{
    public static function name(): string
    {
        return 'Generic Bank CSV (auto-detect columns)';
    }

    public static function detect(array $allRows): bool
    {
        return self::findHeader($allRows) !== null;
    }

    public static function parse(array $allRows): array
    {
        $info = self::findHeader($allRows);
        if (!$info) return [];

        [$headerIdx, $col] = $info;
        $result = [];

        for ($i = $headerIdx + 1, $total = count($allRows); $i < $total; $i++) {
            $row     = $allRows[$i];
            $dateRaw = trim($row[$col['date']] ?? '');
            if ($dateRaw === '') continue;

            $isoDate = self::parseDate($dateRaw);
            if (!$isoDate) continue;

            // Single amount column with sign, or separate debit/credit columns
            $dr = 0.0; $cr = 0.0;
            if (isset($col['amount'])) {
                $raw = (float) str_replace([',', ' ', '₹', 'INR'], '', $row[$col['amount']] ?? '0');
                if ($raw > 0) $cr = $raw;
                elseif ($raw < 0) $dr = abs($raw);
            } else {
                $dr = isset($col['dr']) ? (float) str_replace([',', ' ', '₹'], '', $row[$col['dr']] ?? '0') : 0.0;
                $cr = isset($col['cr']) ? (float) str_replace([',', ' ', '₹'], '', $row[$col['cr']] ?? '0') : 0.0;
            }
            if ($dr <= 0 && $cr <= 0) continue;

            $bal = isset($col['bal']) ? (float) str_replace([',', ' ', '₹'], '', $row[$col['bal']] ?? '0') : 0.0;

            $result[] = [
                'date'        => $isoDate,
                'description' => trim($row[$col['desc']] ?? ''),
                'amount'      => $cr > 0 ? $cr : $dr,
                'type'        => $cr > 0 ? 'credit' : 'debit',
                'reference'   => isset($col['ref']) ? trim($row[$col['ref']] ?? '') : '',
                'balance'     => $bal,
            ];
        }
        return $result;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** @return array{int, array<string,int>}|null [headerIndex, colMap] */
    private static function findHeader(array $allRows): ?array
    {
        foreach ($allRows as $i => $row) {
            $cells = array_map(fn($c) => strtolower(trim($c)), $row);
            $col   = [];
            foreach ($cells as $idx => $cell) {
                if (!$col && ($cell === '' || $cell === 's no.' || $cell === 'sr no.')) continue;
                if (!isset($col['date'])   && preg_match('/\bdate\b/', $cell))           $col['date']   = $idx;
                if (!isset($col['desc'])   && preg_match('/narration|description|particulars|details|merchant|remarks/', $cell)) $col['desc'] = $idx;
                if (!isset($col['ref'])    && preg_match('/\bref\b|chq|cheque|utr/', $cell)) $col['ref']  = $idx;
                if (!isset($col['dr'])     && preg_match('/debit|withdrawal|dr\b|paid out/', $cell)) $col['dr']   = $idx;
                if (!isset($col['cr'])     && preg_match('/credit|deposit|cr\b|paid in/', $cell))   $col['cr']   = $idx;
                if (!isset($col['amount']) && $cell === 'amount' && !isset($col['dr']))              $col['amount'] = $idx;
                if (!isset($col['bal'])    && preg_match('/balance|closing|bal\b/', $cell))          $col['bal']  = $idx;
            }
            // Require at least date + description + one amount column
            if (isset($col['date'], $col['desc']) && (isset($col['dr']) || isset($col['cr']) || isset($col['amount']))) {
                return [$i, $col];
            }
        }
        return null;
    }

    private static function parseDate(string $raw): ?string
    {
        // DD/MM/YYYY or DD-MM-YYYY
        if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $raw, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        // YYYY-MM-DD (ISO already)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        // DD Mon YYYY  or  DD-Mon-YY
        foreach (['d M Y', 'd-M-Y', 'd M y', 'd-M-y', 'd/M/Y'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $raw);
            if ($dt) return $dt->format('Y-m-d');
        }
        // MM/DD/YYYY (US format, less common for Indian banks)
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m) && (int)$m[1] <= 12 && (int)$m[2] > 12) {
            return "{$m[3]}-{$m[1]}-{$m[2]}";
        }
        return null;
    }
}
