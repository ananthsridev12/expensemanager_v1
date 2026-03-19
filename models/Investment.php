<?php

namespace Models;

use PDO;

class Investment extends BaseModel
{
    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM investments ORDER BY created_at DESC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $input): bool
    {
        $sql = 'INSERT INTO investments (type, name, notes) VALUES (:type, :name, :notes)';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':type' => $input['type'] ?? 'mutual_fund',
            ':name' => trim($input['name'] ?? 'Untitled'),
            ':notes' => $input['notes'] ?? null,
        ]);
    }

    public function createTransaction(array $input): bool
    {
        $sql = 'INSERT INTO investment_transactions (investment_id, transaction_type, amount, units, transaction_date, account_id, notes) VALUES (:investment_id, :transaction_type, :amount, :units, :transaction_date, :account_id, :notes)';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':investment_id' => (int) ($input['investment_id'] ?? 0),
            ':transaction_type' => $input['transaction_type'] ?? 'buy',
            ':amount' => (float) ($input['amount'] ?? 0),
            ':units' => (float) ($input['units'] ?? 0),
            ':transaction_date' => $input['transaction_date'] ?? date('Y-m-d'),
            ':account_id' => !empty($input['account_id']) ? (int) $input['account_id'] : null,
            ':notes' => $input['notes'] ?? null,
        ]);
    }

    public function getRecentTransactions(int $limit = 10): array
    {
        $sql = <<<SQL
SELECT
    it.*, i.name AS investment_name, a.account_name
FROM investment_transactions it
LEFT JOIN investments i ON i.id = it.investment_id
LEFT JOIN accounts a ON a.id = it.account_id
ORDER BY it.transaction_date DESC, it.created_at DESC
LIMIT :limit
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createWithId(array $input): int
    {
        $sql = 'INSERT INTO investments (type, name, notes) VALUES (:type, :name, :notes)';
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':type' => $input['type'] ?? 'mutual_fund',
            ':name' => trim($input['name'] ?? 'Untitled'),
            ':notes' => $input['notes'] ?? null,
        ]);
        return $result ? (int) $this->db->lastInsertId() : 0;
    }

    public function createTransactionWithLedger(array $input, string $fundingToken): bool
    {
        $investmentId = (int) ($input['investment_id'] ?? 0);
        $txType = $input['transaction_type'] ?? 'buy';
        $amount = (float) ($input['amount'] ?? 0);
        $units = (float) ($input['units'] ?? 0);
        $txDate = $input['transaction_date'] ?? date('Y-m-d');
        $notes = trim($input['notes'] ?? '');

        if ($investmentId <= 0 || $amount <= 0) {
            return false;
        }

        $sql = 'INSERT INTO investment_transactions (investment_id, transaction_type, amount, units, transaction_date, notes) VALUES (:investment_id, :transaction_type, :amount, :units, :transaction_date, :notes)';
        $stmt = $this->db->prepare($sql);
        $created = $stmt->execute([
            ':investment_id' => $investmentId,
            ':transaction_type' => $txType,
            ':amount' => $amount,
            ':units' => $units > 0 ? $units : null,
            ':transaction_date' => $txDate,
            ':notes' => $notes !== '' ? $notes : null,
        ]);

        if (!$created) {
            return false;
        }

        $invTxId = (int) $this->db->lastInsertId();

        if ($fundingToken !== '' && strpos($fundingToken, ':') !== false) {
            [$accountType, $accountIdRaw] = explode(':', $fundingToken, 2);
            $accountId = (int) $accountIdRaw;
            $allowedTypes = ['savings', 'current', 'cash', 'wallet', 'other', 'credit_card'];
            if ($accountId > 0 && in_array($accountType, $allowedTypes, true)) {
                $entryNote = $notes !== '' ? $notes : ('Investment ' . $txType . ' #' . $investmentId);
                $stmt2 = $this->db->prepare(
                    'INSERT INTO transactions (transaction_date, account_type, account_id, transaction_type, amount, reference_type, reference_id, notes)
                     VALUES (:transaction_date, :account_type, :account_id, :transaction_type, :amount, :reference_type, :reference_id, :notes)'
                );
                $stmt2->execute([
                    ':transaction_date' => $txDate,
                    ':account_type' => $accountType,
                    ':account_id' => $accountId,
                    ':transaction_type' => 'transfer',
                    ':amount' => $amount,
                    ':reference_type' => 'investment',
                    ':reference_id' => $invTxId,
                    ':notes' => $entryNote,
                ]);
                $stmt2->execute([
                    ':transaction_date' => $txDate,
                    ':account_type' => 'investment',
                    ':account_id' => null,
                    ':transaction_type' => 'transfer',
                    ':amount' => $amount,
                    ':reference_type' => 'investment',
                    ':reference_id' => $invTxId,
                    ':notes' => $entryNote,
                ]);
            }
        }

        return true;
    }

    public function getSummary(): array
    {
        $stmt = $this->db->query('SELECT COUNT(*) AS total_investments FROM investments');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'count' => (int) $row['total_investments'],
        ];
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM investments WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function update(array $input): bool
    {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) return false;
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') return false;
        $stmt = $this->db->prepare('UPDATE investments SET type=:type, name=:name, notes=:notes WHERE id=:id');
        return $stmt->execute([
            ':type' => $input['type'] ?? 'mutual_fund',
            ':name' => $name,
            ':notes' => $input['notes'] ?? null,
            ':id' => $id,
        ]);
    }
}
