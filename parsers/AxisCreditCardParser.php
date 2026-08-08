<?php

namespace Parsers;

/**
 * Axis Bank Credit Card monthly statement — XLSX format.
 * Covers Flipkart Axis, Neo Rupay Axis, and other Axis co-branded cards
 * that share the same statement layout.
 *
 * Column layout (row with "Date" as first cell):
 *   0: Date  |  1: Transaction Details  |  2: (empty)  |  3: Amount (INR)  |  4: Debit/Credit
 *
 * Date format:  "16 May '26"
 * Amount:       "₹ 394.00"  (₹ symbol + space + number)
 * Type:         "Debit" | "Credit"
 */
class AxisCreditCardParser implements BankParserInterface
{
    public static function name(): string
    {
        return 'Axis Credit Card (Flip / Neo / Co-branded)';
    }

    public static function detect(array $allRows): bool
    {
        foreach ($allRows as $row) {
            if (isset($row[0], $row[3], $row[4])
                && trim($row[0]) === 'Date'
                && str_contains(trim($row[3]), 'Amount')
                && str_contains(trim($row[4]), 'Debit')) {
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
            if (isset($row[0]) && trim($row[0]) === 'Date'
                && isset($row[4]) && str_contains(trim($row[4]), 'Debit')) {
                $headerIdx = $i;
                break;
            }
        }
        if ($headerIdx === null) return [];

        $result = [];
        for ($i = $headerIdx + 1, $total = count($allRows); $i < $total; $i++) {
            $row     = $allRows[$i];
            $dateRaw = trim($row[0] ?? '');

            // End-of-statement sentinel or blank
            if ($dateRaw === '' || str_contains($dateRaw, '** End')) break;

            // Parse "16 May '26" → 2026-05-16
            $clean = str_replace("'", '', $dateRaw);       // "16 May 26"
            $dt    = \DateTime::createFromFormat('d M y', $clean);
            if (!$dt) continue;
            $isoDate = $dt->format('Y-m-d');

            // Parse "₹ 394.00" → 394.00
            $amountRaw = preg_replace('/[^\d.]/', '', $row[3] ?? '0');
            $amount    = (float) $amountRaw;
            if ($amount <= 0) continue;

            $typeRaw = strtolower(trim($row[4] ?? 'debit'));
            $type    = $typeRaw === 'credit' ? 'credit' : 'debit';

            $result[] = [
                'date'        => $isoDate,
                'description' => trim($row[1] ?? ''),
                'amount'      => $amount,
                'type'        => $type,
                'reference'   => '',
                'balance'     => 0.0,
            ];
        }

        return $result;
    }
}
