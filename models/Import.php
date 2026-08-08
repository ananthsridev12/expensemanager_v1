<?php

namespace Models;

use PDO;

class Import extends BaseModel
{
    // ── Parser registry ──────────────────────────────────────────────────────

    private bool $parsersLoaded = false;

    private function loadParsers(): void
    {
        if ($this->parsersLoaded) return;
        $dir = __DIR__ . '/../parsers/';
        foreach (glob($dir . '*.php') as $file) {
            require_once $file;
        }
        $this->parsersLoaded = true;
    }

    /** @return string[] fully-qualified class names implementing BankParserInterface */
    public function getAvailableParsers(): array
    {
        $this->loadParsers();
        $result = [];
        foreach (get_declared_classes() as $class) {
            if (str_starts_with($class, 'Parsers\\') && $class !== 'Parsers\\BankParserInterface') {
                $implements = class_implements($class) ?: [];
                if (in_array('Parsers\\BankParserInterface', $implements, true)) {
                    $result[] = $class;
                }
            }
        }
        return $result;
    }

    public function detectParser(array $allRows): ?string
    {
        foreach ($this->getAvailableParsers() as $class) {
            if ($class::detect($allRows)) return $class;
        }
        return null;
    }

    // ── File readers ─────────────────────────────────────────────────────────

    /**
     * Read a file (CSV or XLSX) and return raw indexed rows (array of string arrays).
     * Parsers receive this — they find their own header rows.
     */
    public function readFile(string $path, string $filename): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return $ext === 'xlsx' ? $this->readXlsxFile($path) : $this->readCsvFile($path);
    }

    private function readCsvFile(string $path): array
    {
        $fh = fopen($path, 'r');
        if ($fh === false) return [];

        // Strip UTF-8 BOM
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($fh);

        $rows = [];
        while (($row = fgetcsv($fh)) !== false) {
            if ($row !== null) {
                $rows[] = array_map('trim', $row);
            }
        }
        fclose($fh);
        return $rows;
    }

    private function readXlsxFile(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return [];

        // Read shared strings table
        $shared = [];
        $ssXml  = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ss  = new \SimpleXMLElement($ssXml);
            $ns  = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $ss->registerXPathNamespace('x', $ns);
            foreach ($ss->xpath('//x:si') as $si) {
                $parts = [];
                foreach ($si->xpath('.//x:t') as $t) {
                    $parts[] = (string) $t;
                }
                $shared[] = implode('', $parts);
            }
        }

        // Read sheet
        $shXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!$shXml) return [];

        $sh = new \SimpleXMLElement($shXml);
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $sh->registerXPathNamespace('x', $ns);

        $rows = [];
        foreach ($sh->xpath('//x:row') as $xmlRow) {
            $rowIdx  = (int) $xmlRow['r'] - 1;
            $cells   = [];
            $maxCol  = 0;

            foreach ($xmlRow->xpath('x:c') as $c) {
                preg_match('/^([A-Z]+)/', (string) $c['r'], $m);
                $col    = $this->colLetterToIndex($m[1] ?? 'A');
                $maxCol = max($maxCol, $col);

                $vNode = $c->xpath('x:v');
                $v     = isset($vNode[0]) ? (string) $vNode[0] : '';
                if ((string) $c['t'] === 's' && $v !== '') {
                    $v = $shared[(int) $v] ?? '';
                }
                $cells[$col] = $v;
            }

            $indexed = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $indexed[] = $cells[$i] ?? '';
            }
            $rows[$rowIdx] = $indexed;
        }

        ksort($rows);
        return array_values($rows);
    }

    private function colLetterToIndex(string $col): int
    {
        $index = 0;
        foreach (str_split($col) as $ch) {
            $index = $index * 26 + (ord($ch) - ord('A') + 1);
        }
        return $index - 1;
    }

    // ── Staging insert ───────────────────────────────────────────────────────

    /**
     * Create a batch record and insert all parsed rows into import_staging.
     * Flags possible duplicates (same date+amount+type in same account).
     * Returns the new batch id.
     *
     * @param array<int, array<string, mixed>> $rows  normalised rows from parser
     * @param array{account_id:int, account_type:string, parser:string, bank_name:string} $config
     */
    public function insertToStaging(array $rows, array $config): int
    {
        $accountId   = (int)    $config['account_id'];
        $accountType = (string) $config['account_type'];
        $label       = ($config['bank_name'] ?? 'Import') . ' — ' . date('Y-m-d H:i');

        // Pre-fetch existing transactions for duplicate flagging
        $existing = [];
        if (!empty($rows)) {
            $dates = array_column($rows, 'date');
            sort($dates);
            $stmt = $this->db->prepare(
                'SELECT transaction_date, amount, transaction_type
                   FROM transactions
                  WHERE account_id = :aid
                    AND transaction_date BETWEEN :start AND :end'
            );
            $stmt->execute([':aid' => $accountId, ':start' => $dates[0], ':end' => end($dates)]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $tx) {
                $key = $tx['transaction_date'] . '|'
                     . number_format((float) $tx['amount'], 2) . '|'
                     . $tx['transaction_type'];
                $existing[$key] = true;
            }
        }

        $this->db->beginTransaction();
        try {
            // Batch record
            $this->db->prepare(
                'INSERT INTO import_batches
                   (label, bank_parser, account_id, account_type, row_count, skipped_count)
                 VALUES (:label, :parser, :aid, :atype, :cnt, 0)'
            )->execute([
                ':label'  => $label,
                ':parser' => $config['parser'] ?? '',
                ':aid'    => $accountId,
                ':atype'  => $accountType,
                ':cnt'    => count($rows),
            ]);
            $batchId = (int) $this->db->lastInsertId();

            // Staging rows
            $stmtIns = $this->db->prepare(
                'INSERT INTO import_staging
                   (batch_id, transaction_date, description, amount, transaction_type,
                    reference, closing_balance, is_duplicate_flag, notes)
                 VALUES
                   (:bid, :date, :desc, :amount, :type,
                    :ref, :bal, :dup, :notes)'
            );
            foreach ($rows as $row) {
                $txType = ($row['type'] ?? 'debit') === 'credit' ? 'income' : 'expense';
                $key    = ($row['date'] ?? '') . '|'
                        . number_format((float) ($row['amount'] ?? 0), 2) . '|'
                        . $txType;

                $stmtIns->execute([
                    ':bid'    => $batchId,
                    ':date'   => $row['date'] ?? date('Y-m-d'),
                    ':desc'   => $row['description'] ?? '',
                    ':amount' => (float) ($row['amount'] ?? 0),
                    ':type'   => $txType,
                    ':ref'    => $row['reference'] ?? '',
                    ':bal'    => (float) ($row['balance'] ?? 0),
                    ':dup'    => isset($existing[$key]) ? 1 : 0,
                    ':notes'  => $row['description'] ?? '',
                ]);
            }

            $this->db->commit();
            return $batchId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── Review actions ───────────────────────────────────────────────────────

    /**
     * Approve a staging row: creates a transaction and marks the row approved.
     * Returns the new transaction id.
     *
     * @param array{transaction_date?:string, notes?:string, amount?:float,
     *              transaction_type?:string, category_id?:int|string,
     *              subcategory_id?:int|string, payment_method_id?:int|string,
     *              contact_id?:int|string} $data
     */
    public function approveStagingRow(int $stagingId, array $data): int
    {
        // Load staging row + batch info
        $stmt = $this->db->prepare(
            'SELECT s.*, b.account_id, b.account_type
               FROM import_staging s
               JOIN import_batches b ON b.id = s.batch_id
              WHERE s.id = :id AND s.status = \'pending\''
        );
        $stmt->execute([':id' => $stagingId]);
        $staging = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$staging) {
            throw new \RuntimeException('Staging row not found or already processed.');
        }

        $txDate  = preg_replace('/[^0-9\-]/', '', (string) ($data['transaction_date'] ?? $staging['transaction_date']));
        $txType  = in_array($data['transaction_type'] ?? '', ['income', 'expense'], true)
                   ? $data['transaction_type']
                   : $staging['transaction_type'];
        $amount  = abs((float) ($data['amount'] ?? $staging['amount']));
        $notes   = trim((string) ($data['notes'] ?? $staging['description'] ?? ''));
        $catId   = !empty($data['category_id'])        ? (int) $data['category_id']        : null;
        $subId   = !empty($data['subcategory_id'])      ? (int) $data['subcategory_id']      : null;
        $pmId    = !empty($data['payment_method_id'])   ? (int) $data['payment_method_id']   : null;
        $cId     = !empty($data['contact_id'])          ? (int) $data['contact_id']          : null;

        $this->db->prepare(
            'INSERT INTO transactions
               (transaction_date, transaction_type, amount,
                account_id, account_type, category_id, subcategory_id,
                payment_method_id, contact_id, notes,
                reference_type, reference_id)
             VALUES
               (:date, :type, :amount,
                :aid, :atype, :catid, :subcatid,
                :pmid, :cid, :notes,
                \'import\', :batchid)'
        )->execute([
            ':date'    => $txDate,
            ':type'    => $txType,
            ':amount'  => $amount,
            ':aid'     => $staging['account_id'],
            ':atype'   => $staging['account_type'],
            ':catid'   => $catId,
            ':subcatid' => $subId,
            ':pmid'    => $pmId,
            ':cid'     => $cId,
            ':notes'   => $notes,
            ':batchid' => $staging['batch_id'],
        ]);
        $txId = (int) $this->db->lastInsertId();

        $this->db->prepare(
            "UPDATE import_staging
                SET status = 'approved', approved_tx_id = :txid, reviewed_at = NOW(),
                    notes = :notes, category_id = :catid, subcategory_id = :subid,
                    payment_method_id = :pmid, contact_id = :cid
              WHERE id = :id"
        )->execute([
            ':txid'  => $txId,
            ':notes' => $notes,
            ':catid' => $catId,
            ':subid' => $subId,
            ':pmid'  => $pmId,
            ':cid'   => $cId,
            ':id'    => $stagingId,
        ]);

        return $txId;
    }

    public function skipStagingRow(int $stagingId): void
    {
        $this->db->prepare(
            "UPDATE import_staging SET status = 'skipped', reviewed_at = NOW() WHERE id = :id"
        )->execute([':id' => $stagingId]);
    }

    // ── Queries ──────────────────────────────────────────────────────────────

    /** Pending staging rows, optionally filtered by batch. */
    public function getPendingRows(int $batchId = 0): array
    {
        if ($batchId > 0) {
            $stmt = $this->db->prepare(
                "SELECT s.*, b.label AS batch_label,
                        a.bank_name AS acct_bank, a.account_name AS acct_name
                   FROM import_staging s
                   JOIN import_batches b ON b.id = s.batch_id
                   LEFT JOIN accounts a ON a.id = b.account_id
                  WHERE s.batch_id = :bid AND s.status = 'pending'
                  ORDER BY s.transaction_date ASC, s.id ASC"
            );
            $stmt->execute([':bid' => $batchId]);
        } else {
            $stmt = $this->db->query(
                "SELECT s.*, b.label AS batch_label,
                        a.bank_name AS acct_bank, a.account_name AS acct_name
                   FROM import_staging s
                   JOIN import_batches b ON b.id = s.batch_id
                   LEFT JOIN accounts a ON a.id = b.account_id
                  WHERE s.status = 'pending'
                  ORDER BY s.transaction_date ASC, s.id ASC"
            );
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** All batches with live pending/approved/skipped counts from staging. */
    public function getBatches(): array
    {
        return $this->db->query(
            "SELECT b.*,
                    a.bank_name AS acct_bank, a.account_name AS acct_name,
                    COALESCE(SUM(s.status = 'pending'),  0) AS pending_count,
                    COALESCE(SUM(s.status = 'approved'), 0) AS approved_count,
                    COALESCE(SUM(s.status = 'skipped'),  0) AS skipped_count
               FROM import_batches b
               LEFT JOIN accounts a ON a.id = b.account_id
               LEFT JOIN import_staging s ON s.batch_id = b.id
              GROUP BY b.id
              ORDER BY b.created_at DESC
              LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Rollback ─────────────────────────────────────────────────────────────

    /** Delete all approved transactions + all staging rows for the batch. */
    public function rollbackBatch(int $batchId): int
    {
        $this->db->prepare(
            "DELETE FROM transactions WHERE reference_type = 'import' AND reference_id = :id"
        )->execute([':id' => $batchId]);
        $deleted = (int) $this->db->query('SELECT ROW_COUNT()')->fetchColumn();

        $this->db->prepare('DELETE FROM import_staging WHERE batch_id = :id')->execute([':id' => $batchId]);
        $this->db->prepare('DELETE FROM import_batches WHERE id = :id')->execute([':id' => $batchId]);

        return $deleted;
    }
}
