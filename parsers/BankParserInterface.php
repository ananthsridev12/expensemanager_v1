<?php

namespace Parsers;

interface BankParserInterface
{
    /** Human-readable bank/format name shown in the UI */
    public static function name(): string;

    /**
     * Return true if the given CSV header row (trimmed, as-read) belongs to this bank.
     * @param string[] $headers
     */
    public static function detect(array $headers): bool;

    /**
     * Parse CSV data rows (each row is an assoc array keyed by header) and return
     * normalised transaction rows.  Every returned row must contain:
     *   date        string  YYYY-MM-DD
     *   description string  merchant / narration
     *   amount      float   always positive
     *   type        string  'debit' | 'credit'
     *   reference   string  UTR / cheque / ref (may be empty)
     *   balance     float   closing balance (0 if not available)
     *
     * Skip rows that are not real transactions (opening balance, totals, blank, etc.).
     *
     * @param array<int, array<string, string>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function parse(array $rows): array;
}
