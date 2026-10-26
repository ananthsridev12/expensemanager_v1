<?php

namespace Parsers;

/**
 * HDFC Bank Credit Card CSV statement.
 *
 * Header row: Date | Narration | Debit Amount | Credit Amount | Chq/Ref Number
 * OR:         Date | Transaction Details | Domestic Amount (INR) | ...
 * Date formats: DD/MM/YYYY or "10 Oct 2024"
 */
class HdfcCreditCardParser implements BankParserInterface
{
    public static function name(): string
    {
        return 'HDFC Credit Card';
    }

    public static function detect(array $allRows): bool
    {
        foreach ($allRows as $row) {
            // Standard CSV export
            if (isset($row[0], $row[1], $row[2], $row[3])
                && trim($row[0]) === 'Date'
                && trim($row[1]) === 'Narration'
                && str_contains(trim($row[2]), 'Debit')
                && str_contains(trim($row[3]), 'Credit')
                && !isset($row[6])) {  // savings has 7 cols, CC has 5
                return true;
            }
            // PDF-export style: "Date","Transaction Details","Domestic Amount (INR)"
            if (isset($row[0], $row[1], $row[2])
                && trim($row[0]) === 'Date'
                && str_contains(trim($row[1]), 'Transaction')
                && str_contains(trim($row[2]), 'Domestic')) {
                return true;
            }
        }
        return false;
    }

    public static function parse(array $allRows): array
    {
        $headerIdx = null;
        $mode      = 'standard';
        foreach ($allRows as $i => $row) {
            if (isset($row[0]) && trim($row[0]) === 'Date') {
                if (isset($row[2]) && str_contains(trim($row[2]), 'Domestic')) {
                    $mode = 'pdf';
                }
                $headerIdx = $i;
                break;
            }
        }
        if ($headerIdx === null) return [];

        $result = [];
        for ($i = $headerIdx + 1, $total = count($allRows); $i < $total; $i++) {
            $row     = $allRows[$i];
            $dateRaw = trim($row[0] ?? '');
            if ($dateRaw === '') continue;

            // Try DD/MM/YYYY first
            $isoDate = null;
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateRaw)) {
                [$d, $m, $y] = explode('/', $dateRaw);
                $isoDate = "{$y}-{$m}-{$d}";
            } else {
                $dt = \DateTime::createFromFormat('d M Y', $dateRaw)
                   ?: \DateTime::createFromFormat('d-M-Y', $dateRaw);
                if ($dt) $isoDate = $dt->format('Y-m-d');
            }
            if (!$isoDate) continue;

            if ($mode === 'pdf') {
                // "Domestic Amount (INR)" column; negative = credit (payment)
                $raw    = trim($row[2] ?? '0');
                $amount = (float) preg_replace('/[^\d.\-]/', '', str_replace(',', '', $raw));
                if ($amount == 0) continue;
                $type   = $amount < 0 ? 'credit' : 'debit';
                $amount = abs($amount);
                $result[] = [
                    'date'        => $isoDate,
                    'description' => trim($row[1] ?? ''),
                    'amount'      => $amount,
                    'type'        => $type,
                    'reference'   => '',
                    'balance'     => 0.0,
                ];
            } else {
                $dr  = (float) str_replace([',', ' '], '', $row[2] ?? '0');
                $cr  = (float) str_replace([',', ' '], '', $row[3] ?? '0');
                if ($dr <= 0 && $cr <= 0) continue;
                $result[] = [
                    'date'        => $isoDate,
                    'description' => trim($row[1] ?? ''),
                    'amount'      => $cr > 0 ? $cr : $dr,
                    'type'        => $cr > 0 ? 'credit' : 'debit',
                    'reference'   => trim($row[4] ?? ''),
                    'balance'     => 0.0,
                ];
            }
        }
        return $result;
    }
}
