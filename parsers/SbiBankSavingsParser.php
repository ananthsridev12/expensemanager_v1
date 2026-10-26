<?php

namespace Parsers;

/**
 * State Bank of India (SBI) Savings / Current account CSV statement.
 *
 * Header: Txn Date | Value Date | Description | Ref No./Cheque No. | Debit | Credit | Balance
 * Date format: "03 Jan 2024"
 */
class SbiBankSavingsParser implements BankParserInterface
{
    public static function name(): string
    {
        return 'SBI Savings / Current';
    }

    public static function detect(array $allRows): bool
    {
        foreach ($allRows as $row) {
            if (isset($row[0], $row[2])
                && trim($row[0]) === 'Txn Date'
                && trim($row[2]) === 'Description') {
                return true;
            }
        }
        return false;
    }

    public static function parse(array $allRows): array
    {
        $headerIdx = null;
        foreach ($allRows as $i => $row) {
            if (isset($row[0]) && trim($row[0]) === 'Txn Date') {
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

            // "03 Jan 2024" → YYYY-MM-DD
            $dt = \DateTime::createFromFormat('d M Y', $dateRaw)
               ?: \DateTime::createFromFormat('d-M-Y', $dateRaw);
            if (!$dt) continue;
            $isoDate = $dt->format('Y-m-d');

            $dr  = (float) str_replace([',', ' '], '', $row[4] ?? '0');
            $cr  = (float) str_replace([',', ' '], '', $row[5] ?? '0');
            $bal = (float) str_replace([',', ' '], '', $row[6] ?? '0');
            if ($dr <= 0 && $cr <= 0) continue;

            $result[] = [
                'date'        => $isoDate,
                'description' => trim($row[2] ?? ''),
                'amount'      => $cr > 0 ? $cr : $dr,
                'type'        => $cr > 0 ? 'credit' : 'debit',
                'reference'   => trim($row[3] ?? ''),
                'balance'     => $bal,
            ];
        }
        return $result;
    }
}
