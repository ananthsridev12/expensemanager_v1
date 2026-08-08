<?php

namespace Parsers;

/**
 * Axis Bank Savings / Current account CSV statement.
 *
 * Format: multi-line text header, then a CSV data block starting at a row
 * whose first cell is "Tran Date", followed by transaction rows, followed by
 * a multi-line legal footer.
 *
 * Columns (0-indexed): Tran Date | CHQNO | PARTICULARS | DR | CR | BAL | SOL
 * Date format: DD-MM-YYYY
 * Amounts: may have leading/trailing spaces and comma separators.
 */
class AxisBankSavingsParser implements BankParserInterface
{
    public static function name(): string
    {
        return 'Axis Bank Savings / Current';
    }

    public static function detect(array $allRows): bool
    {
        foreach ($allRows as $row) {
            if (isset($row[0], $row[1], $row[2])
                && trim($row[0]) === 'Tran Date'
                && trim($row[1]) === 'CHQNO'
                && trim($row[2]) === 'PARTICULARS') {
                return true;
            }
        }
        return false;
    }

    public static function parse(array $allRows): array
    {
        // Find the header row
        $headerIdx = null;
        foreach ($allRows as $i => $row) {
            if (isset($row[0]) && trim($row[0]) === 'Tran Date') {
                $headerIdx = $i;
                break;
            }
        }
        if ($headerIdx === null) return [];

        $result = [];
        for ($i = $headerIdx + 1, $total = count($allRows); $i < $total; $i++) {
            $row     = $allRows[$i];
            $dateRaw = trim($row[0] ?? '');

            // Stop at footer rows (not a DD-MM-YYYY date)
            if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $dateRaw)) {
                continue; // skip blank / footer lines rather than breaking early
            }

            $dr  = (float) str_replace([',', ' '], '', $row[3] ?? '0');
            $cr  = (float) str_replace([',', ' '], '', $row[4] ?? '0');
            $bal = (float) str_replace([',', ' '], '', $row[5] ?? '0');

            if ($dr <= 0 && $cr <= 0) continue;

            // DD-MM-YYYY → YYYY-MM-DD
            [$d, $m, $y] = explode('-', $dateRaw);
            $isoDate = "{$y}-{$m}-{$d}";

            $ref = trim($row[1] ?? '');
            if ($ref === '-') $ref = '';

            $result[] = [
                'date'        => $isoDate,
                'description' => trim($row[2] ?? ''),
                'amount'      => $cr > 0 ? $cr : $dr,
                'type'        => $cr > 0 ? 'credit' : 'debit',
                'reference'   => $ref,
                'balance'     => $bal,
            ];
        }

        return $result;
    }
}
