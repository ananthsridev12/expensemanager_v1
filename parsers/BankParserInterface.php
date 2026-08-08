<?php

namespace Parsers;

interface BankParserInterface
{
    /** Human-readable name shown in the UI, e.g. "Axis Bank Savings" */
    public static function name(): string;

    /**
     * Return true if the given raw rows (indexed string arrays, as read verbatim
     * from CSV or XLSX before any processing) match this bank's layout.
     * The parser must scan for its own header row — do NOT assume row 0 is a header.
     *
     * @param array<int, array<int, string>> $allRows
     */
    public static function detect(array $allRows): bool;

    /**
     * Parse all raw rows and return normalised transaction rows.
     * Each returned element must have:
     *   date        string  YYYY-MM-DD
     *   description string  merchant / narration
     *   amount      float   always positive
     *   type        string  'debit' | 'credit'
     *   reference   string  UTR / ref (empty string if none)
     *   balance     float   closing balance (0.0 if not available)
     *
     * Skip junk rows (opening balance, totals, blank lines, footer text).
     *
     * @param array<int, array<int, string>> $allRows
     * @return array<int, array<string, mixed>>
     */
    public static function parse(array $allRows): array;
}
