<?php

namespace Parsers;

/**
 * HDFC Bank Savings / Current account CSV statement.
 *
 * Header row: Date | Narration | Value Dat | Debit Amount | Credit Amount | Chq/Ref Number | Closing Balance
 * Date format: DD/MM/YYYY
 */
class HdfcBankSavingsParser implements BankParserInterface
{
    public static function name(): string
    {
        return 'HDFC Bank Savings / Current';
    }

    public static function detect(array $allRows): bool
    {
        foreach ($allRows as $row) {
            if (isset($row[0], $row[1], $row[3], $row[4])
                && trim($row[0]) === 'Date'
                && trim($row[1]) === 'Narration'
                && str_contains(trim($row[3]), 'Debit')
                && str_contains(trim($row[4]), 'Credit')) {
                return true;
            }
        }
        return false;
    }

    public static function parse(array $allRows): array
    {
        $headerIdx = null;
        foreach ($allRows as $i => $row) {
            if (isset($row[0], $row[1]) && trim($row[0]) === 'Date' && trim($row[1]) === 'Narration') {
                $headerIdx = $i;
                break;
            }
        }
        if ($headerIdx === null) return [];

        $result = [];
        for ($i = $headerIdx + 1, $total = count($allRows); $i < $total; $i++) {
            $row     = $allRows[$i];
            $dateRaw = trim($row[0] ?? '');
            if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateRaw)) continue;

            [$d, $m, $y] = explode('/', $dateRaw);
            $isoDate = "{$y}-{$m}-{$d}";

            $dr  = (float) str_replace([',', ' '], '', $row[3] ?? '0');
            $cr  = (float) str_replace([',', ' '], '', $row[4] ?? '0');
            $bal = (float) str_replace([',', ' '], '', $row[6] ?? '0');
            if ($dr <= 0 && $cr <= 0) continue;

            $result[] = [
                'date'        => $isoDate,
                'description' => trim($row[1] ?? ''),
                'amount'      => $cr > 0 ? $cr : $dr,
                'type'        => $cr > 0 ? 'credit' : 'debit',
                'reference'   => trim($row[5] ?? ''),
                'balance'     => $bal,
            ];
        }
        return $result;
    }
}
