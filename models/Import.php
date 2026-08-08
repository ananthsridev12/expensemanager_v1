<?php

namespace Models;

use PDO;

class Import extends BaseModel
{
    // ── Parser registry ──────────────────────────────────────────────────────

    private function loadParsers(): void
    {
        $dir = __DIR__ . '/../parsers/';
        foreach (glob($dir . '*.php') as $file) {
            require_once $file;
        }
    }

    /** @return string[] fully-qualified class names implementing BankParserInterface */
    public function getAvailableParsers(): array
    {
        $this->loadParsers();
        $result = [];
        foreach (get_declared_classes() as $class) {
            if (str_starts_with($class, 'Parsers\\') && $class !== 'Parsers\\BankParserInterface') {
                if (in_array('Parsers\\BankParserInterface', class_implements($class) ?: [], true)) {
                    $result[] = $class;
                }
            }
        }
        return $result;
    }

    public function detectParser(array $headers): ?string
    {
        foreach ($this->getAvailableParsers() as $class) {
            if ($class::detect($headers)) {
                return $class;
            }
        }
        return null;
    }

    // ── CSV parsing ──────────────────────────────────────────────────────────

    /**
     * Read a CSV file, detect BOM/encoding issues, return normalised rows via the parser.
     * @return array<int, array<string, mixed>>
     */
    public function parseCsvFile(string $path, string $parserClass): array
    {
        $fh = fopen($path, 'r');
        if ($fh === false) {
            return [];
        }

        // Strip UTF-8 BOM if present
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }

        $headers = null;
        $rawRows = [];
        while (($row = fgetcsv($fh)) !== false) {
            if ($row === null) continue;
            // Skip completely blank rows
            if (count($row) === 1 && trim($row[0] ?? '') === '') continue;

            if ($headers === null) {
                $headers = array_map('trim', $row);
                continue;
            }
            $padded = array_pad($row, count($headers), '');
            $rawRows[] = array_combine($headers, array_map('trim', $padded));
        }
        fclose($fh);

        return $parserClass::parse($rawRows);
    }

    // ── Duplicate detection ──────────────────────────────────────────────────

    /**
     * Flag each row with is_duplicate = true/false based on same date+amount+type in the account.
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function flagDuplicates(array $rows, int $accountId): array
    {
        if (empty($rows)) return $rows;

        $dates = array_column($rows, 'date');
        sort($dates);
        $startDate = $dates[0];
        $endDate   = end($dates);

        $stmt = $this->db->prepare(
            'SELECT transaction_date, amount, transaction_type
               FROM transactions
              WHERE account_id = :aid
                AND transaction_date BETWEEN :start AND :end'
        );
        $stmt->execute([':aid' => $accountId, ':start' => $startDate, ':end' => $endDate]);

        $existing = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $tx) {
            $key = $tx['transaction_date'] . '|' . number_format((float)$tx['amount'], 2) . '|' . $tx['transaction_type'];
            $existing[$key] = true;
        }

        foreach ($rows as &$row) {
            $txType = ($row['type'] ?? 'debit') === 'credit' ? 'income' : 'expense';
            $key    = ($row['date'] ?? '') . '|' . number_format((float)($row['amount'] ?? 0), 2) . '|' . $txType;
            $row['is_duplicate'] = isset($existing[$key]);
        }
        unset($row);

        return $rows;
    }

    // ── Batch insert ─────────────────────────────────────────────────────────

    /**
     * Bulk-insert normalised rows into transactions, create a batch record.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array{account_id:int, account_type:string, category_id:int|null,
     *              parser:string, bank_name:string, skip_duplicates:bool} $config
     * @return int new batch id
     */
    public function insertBatch(array $rows, array $config): int
    {
        $accountId   = (int)  $config['account_id'];
        $accountType = (string)$config['account_type'];
        $categoryId  = !empty($config['category_id']) ? (int)$config['category_id'] : null;
        $skipDups    = (bool) ($config['skip_duplicates'] ?? true);
        $label       = ($config['bank_name'] ?? 'Import') . ' — ' . date('Y-m-d H:i');

        $inserted = 0;
        $skipped  = 0;

        $this->db->beginTransaction();
        try {
            $stmtBatch = $this->db->prepare(
                'INSERT INTO import_batches (label, bank_parser, account_id, account_type, row_count, skipped_count)
                 VALUES (:label, :parser, :aid, :atype, 0, 0)'
            );
            $stmtBatch->execute([
                ':label'  => $label,
                ':parser' => $config['parser'] ?? '',
                ':aid'    => $accountId,
                ':atype'  => $accountType,
            ]);
            $batchId = (int)$this->db->lastInsertId();

            $stmtTx = $this->db->prepare(
                'INSERT INTO transactions
                   (transaction_date, transaction_type, amount,
                    account_id, account_type, category_id,
                    notes, reference_type, reference_id)
                 VALUES
                   (:date, :type, :amount,
                    :aid, :atype, :catid,
                    :notes, \'import\', :batchid)'
            );

            foreach ($rows as $row) {
                if ($skipDups && !empty($row['is_duplicate'])) {
                    $skipped++;
                    continue;
                }
                $txType = ($row['type'] ?? 'debit') === 'credit' ? 'income' : 'expense';
                $ref    = trim((string)($row['reference'] ?? ''));
                $desc   = trim((string)($row['description'] ?? ''));
                $notes  = $ref !== '' ? $desc . ' [' . $ref . ']' : $desc;

                $stmtTx->execute([
                    ':date'    => $row['date'],
                    ':type'    => $txType,
                    ':amount'  => (float)($row['amount'] ?? 0),
                    ':aid'     => $accountId,
                    ':atype'   => $accountType,
                    ':catid'   => $categoryId,
                    ':notes'   => $notes,
                    ':batchid' => $batchId,
                ]);
                $inserted++;
            }

            $this->db->prepare(
                'UPDATE import_batches SET row_count = :ins, skipped_count = :skip WHERE id = :id'
            )->execute([':ins' => $inserted, ':skip' => $skipped, ':id' => $batchId]);

            $this->db->commit();
            return $batchId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── Rollback ─────────────────────────────────────────────────────────────

    public function rollbackBatch(int $batchId): int
    {
        $this->db->prepare(
            "DELETE FROM transactions WHERE reference_type = 'import' AND reference_id = :id"
        )->execute([':id' => $batchId]);
        $deleted = (int)$this->db->query(
            "SELECT ROW_COUNT()"
        )->fetchColumn();

        $this->db->prepare('DELETE FROM import_batches WHERE id = :id')->execute([':id' => $batchId]);
        return $deleted;
    }

    // ── History ──────────────────────────────────────────────────────────────

    public function getBatches(): array
    {
        return $this->db->query(
            'SELECT b.*, a.bank_name AS acct_bank, a.account_name AS acct_name
               FROM import_batches b
               LEFT JOIN accounts a ON a.id = b.account_id
              ORDER BY b.created_at DESC
              LIMIT 50'
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
