<?php

namespace Parsers;

/**
 * Yes Bank Savings / Current account CSV statement.
 *
 * Header: Date | Narration | Ref No./Chq. No. | Value Date | Withdrawal Amt. | Deposit Amt. | Closing Balance
 * Date format: DD-MM-YYYY
 */
class YesBankSavingsParser implements BankParserInterface
{
    public static function name(): string
    {
        return 'Yes Bank Savings / Current';
    }

    public static function detect(array $allRows): bool
    {
        foreach ($allRows as $row) {
            $cells = array_map('trim', $row);
            if (in_array('Withdrawal Amt.', $cells, true)
                && in_array('Deposit Amt.', $cells, true)) {
                return true;
            }
        }
        return false;
    }

    public static function parse(array $allRows): array
    {
        $headerIdx = null;
        $header    = [];
        foreach ($allRows as $i => $row) {
            $cells = array_map('trim', $row);
            if (in_array('Withdrawal Amt.', $cells, true)) {
                $headerIdx = $i;
                $header    = $cells;
                break;
            }
        }
        if ($headerIdx === null) return [];

        $col = [];
        foreach ($header as $idx => $name) {
            $key = match (true) {
                $name === 'Date'                      => 'date',
                $name === 'Narration'                 => 'desc',
                str_contains($name, 'Ref')            => 'ref',
                str_contains($name, 'Withdrawal')     => 'dr',
                str_contains($name, 'Deposit')        => 'cr',
                str_contains($name, 'Closing Balance') => 'bal',
                default                               => null,
            };
            if ($key) $col[$key] = $idx;
        }

        if (!isset($col['date'], $col['desc'])) return [];

        $result = [];
        for ($i = $headerIdx + 1, $total = count($allRows); $i < $total; $i++) {
            $row     = $allRows[$i];
            $dateRaw = trim($row[$col['date']] ?? '');
            if ($dateRaw === '') continue;

            $isoDate = null;
            if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $dateRaw, $m)) {
                $isoDate = "{$m[3]}-{$m[2]}-{$m[1]}";
            } else {
                $dt = \DateTime::createFromFormat('d M Y', $dateRaw);
                if ($dt) $isoDate = $dt->format('Y-m-d');
            }
            if (!$isoDate) continue;

            $dr  = isset($col['dr'])  ? (float) str_replace([',', ' '], '', $row[$col['dr']]  ?? '0') : 0.0;
            $cr  = isset($col['cr'])  ? (float) str_replace([',', ' '], '', $row[$col['cr']]  ?? '0') : 0.0;
            $bal = isset($col['bal']) ? (float) str_replace([',', ' '], '', $row[$col['bal']] ?? '0') : 0.0;
            if ($dr <= 0 && $cr <= 0) continue;

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
}
