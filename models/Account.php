<?php

namespace Models;

class Account extends BaseModel
{
    private const SYSTEM_TYPES = ['savings', 'current', 'credit_card', 'cash', 'wallet', 'other'];

    public function getAllWithBalances(): array
    {
        $sql = <<<SQL
SELECT
    a.*,
    at.name AS account_type_name,
    cc.id AS credit_card_id,
    cc.credit_limit,
    cc.outstanding_balance,
    cc.outstanding_principal,
    COALESCE(a.opening_balance + SUM(CASE
        WHEN t.transaction_type = 'income' THEN t.amount
        WHEN t.transaction_type = 'expense' THEN -t.amount
        ELSE 0
    END), a.opening_balance) AS balance,
    COALESCE(SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE 0 END), 0) AS total_income,
    COALESCE(SUM(CASE WHEN t.transaction_type = 'expense' THEN t.amount ELSE 0 END), 0) AS total_expense,
    GREATEST(0, COALESCE(
        cc.outstanding_balance + SUM(CASE
            WHEN t.transaction_type = 'expense' THEN t.amount
            WHEN t.transaction_type = 'income'  THEN -t.amount
            ELSE 0
        END),
        cc.outstanding_balance
    )) AS live_cc_outstanding
FROM accounts a
LEFT JOIN account_types at ON at.id = a.account_type_id
LEFT JOIN transactions t ON t.account_id = a.id
LEFT JOIN credit_cards cc ON cc.account_id = a.id
GROUP BY a.id
ORDER BY a.created_at DESC
SQL;
        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    public function create(array $input): bool
    {
        [$accountType, $accountTypeId] = $this->resolveAccountType($input, null);

        $this->db->beginTransaction();
        try {
            $sql = 'INSERT INTO accounts (bank_name, account_name, account_type, account_type_id, account_number, ifsc, opening_balance) VALUES (:bank_name, :account_name, :account_type, :account_type_id, :account_number, :ifsc, :opening_balance)';
            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':bank_name' => trim((string) ($input['bank_name'] ?? '')),
                ':account_name' => trim((string) ($input['account_name'] ?? '')),
                ':account_type' => $accountType,
                ':account_type_id' => $accountTypeId,
                ':account_number' => !empty($input['account_number']) ? trim((string) $input['account_number']) : null,
                ':ifsc' => !empty($input['ifsc']) ? trim((string) $input['ifsc']) : null,
                ':opening_balance' => is_numeric($input['opening_balance'] ?? null) ? (float) $input['opening_balance'] : 0.0,
            ]);

            $accountId = (int) $this->db->lastInsertId();

            if ($accountType === 'credit_card') {
                $cardSql = 'INSERT INTO credit_cards (account_id, bank_name, card_name, credit_limit, billing_date, due_date, outstanding_balance, outstanding_principal, interest_rate, tenure_months, processing_fee, gst_rate, emi_amount, emi_start_date)
                            VALUES (:account_id, :bank_name, :card_name, :credit_limit, :billing_date, :due_date, :outstanding_balance, :outstanding_principal, :interest_rate, :tenure_months, :processing_fee, :gst_rate, :emi_amount, :emi_start_date)';
                $cardStmt = $this->db->prepare($cardSql);
                $cardStmt->execute([
                    ':account_id' => $accountId,
                    ':bank_name' => trim((string) ($input['bank_name'] ?? '')),
                    ':card_name' => !empty($input['card_name']) ? trim((string) $input['card_name']) : trim((string) ($input['account_name'] ?? '')),
                    ':credit_limit' => is_numeric($input['credit_limit'] ?? null) ? (float) $input['credit_limit'] : 0.0,
                    ':billing_date' => (int) ($input['billing_date'] ?? 1),
                    ':due_date' => (int) ($input['due_date'] ?? 1),
                    ':outstanding_balance' => is_numeric($input['outstanding_balance'] ?? null) ? (float) $input['outstanding_balance'] : 0.0,
                    ':outstanding_principal' => is_numeric($input['outstanding_principal'] ?? null) ? (float) $input['outstanding_principal'] : 0.0,
                    ':interest_rate' => is_numeric($input['interest_rate'] ?? null) ? (float) $input['interest_rate'] : 0.0,
                    ':tenure_months' => (int) ($input['tenure_months'] ?? 0),
                    ':processing_fee' => is_numeric($input['processing_fee'] ?? null) ? (float) $input['processing_fee'] : 0.0,
                    ':gst_rate' => is_numeric($input['gst_rate'] ?? null) ? (float) $input['gst_rate'] : 0.0,
                    ':emi_amount' => is_numeric($input['emi_amount'] ?? null) ? (float) $input['emi_amount'] : 0.0,
                    ':emi_start_date' => !empty($input['emi_start_date']) ? $input['emi_start_date'] : null,
                ]);

                $points = is_numeric($input['points_balance'] ?? null) ? (float) $input['points_balance'] : 0.0;
                if ($points > 0) {
                    $this->db->prepare('UPDATE credit_cards SET points_balance = :points WHERE account_id = :account_id')
                        ->execute([
                            ':points' => $points,
                            ':account_id' => $accountId,
                        ]);
                }
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            return false;
        }
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, at.name AS account_type_name,
                    cc.id AS credit_card_id, cc.bank_name AS card_bank_name, cc.card_name, cc.credit_limit,
                    cc.billing_date, cc.due_date, cc.outstanding_balance, cc.outstanding_principal,
                    cc.interest_rate, cc.tenure_months, cc.processing_fee, cc.gst_rate,
                    cc.emi_amount, cc.emi_start_date, cc.points_balance
             FROM accounts a
             LEFT JOIN account_types at ON at.id = a.account_type_id
             LEFT JOIN credit_cards cc ON cc.account_id = a.id
             WHERE a.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(array $input): bool
    {
        $accountId = (int) ($input['account_id'] ?? 0);
        if ($accountId <= 0) {
            return false;
        }

        $existing = $this->getById($accountId);
        if (!$existing) {
            return false;
        }

        $existingType = (string) ($existing['account_type'] ?? 'savings');
        [$accountType, $accountTypeId] = $this->resolveAccountType($input, $existingType);

        if ($existingType === 'credit_card' && $accountType !== 'credit_card') {
            $accountType = 'credit_card';
            $accountTypeId = $this->getAccountTypeIdBySystemKey('credit_card');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'UPDATE accounts
                 SET bank_name = :bank_name,
                     account_name = :account_name,
                     account_type = :account_type,
                     account_type_id = :account_type_id,
                     account_number = :account_number,
                     ifsc = :ifsc,
                     opening_balance = :opening_balance,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $stmt->execute([
                ':bank_name' => trim((string) ($input['bank_name'] ?? $existing['bank_name'] ?? '')),
                ':account_name' => trim((string) ($input['account_name'] ?? $existing['account_name'] ?? '')),
                ':account_type' => $accountType,
                ':account_type_id' => $accountTypeId,
                ':account_number' => !empty($input['account_number']) ? trim((string) $input['account_number']) : ($existing['account_number'] ?? null),
                ':ifsc' => !empty($input['ifsc']) ? trim((string) $input['ifsc']) : ($existing['ifsc'] ?? null),
                ':opening_balance' => is_numeric($input['opening_balance'] ?? null) ? (float) $input['opening_balance'] : (float) ($existing['opening_balance'] ?? 0),
                ':id' => $accountId,
            ]);

            if ($accountType === 'credit_card') {
                $cardId = (int) ($existing['credit_card_id'] ?? 0);
                if ($cardId <= 0) {
                    $this->db->prepare(
                        'INSERT INTO credit_cards (account_id, bank_name, card_name, credit_limit, billing_date, due_date, outstanding_balance, outstanding_principal, interest_rate, tenure_months, processing_fee, gst_rate, emi_amount, emi_start_date, points_balance)
                         VALUES (:account_id, :bank_name, :card_name, :credit_limit, :billing_date, :due_date, :outstanding_balance, :outstanding_principal, :interest_rate, :tenure_months, :processing_fee, :gst_rate, :emi_amount, :emi_start_date, :points_balance)'
                    )->execute([
                        ':account_id' => $accountId,
                        ':bank_name' => trim((string) ($input['bank_name'] ?? $existing['bank_name'] ?? '')),
                        ':card_name' => trim((string) ($input['card_name'] ?? $existing['card_name'] ?? $existing['account_name'] ?? '')),
                        ':credit_limit' => is_numeric($input['credit_limit'] ?? null) ? (float) $input['credit_limit'] : (float) ($existing['credit_limit'] ?? 0),
                        ':billing_date' => (int) ($input['billing_date'] ?? $existing['billing_date'] ?? 1),
                        ':due_date' => (int) ($input['due_date'] ?? $existing['due_date'] ?? 1),
                        ':outstanding_balance' => is_numeric($input['outstanding_balance'] ?? null) ? (float) $input['outstanding_balance'] : (float) ($existing['outstanding_balance'] ?? 0),
                        ':outstanding_principal' => is_numeric($input['outstanding_principal'] ?? null) ? (float) $input['outstanding_principal'] : (float) ($existing['outstanding_principal'] ?? 0),
                        ':interest_rate' => is_numeric($input['interest_rate'] ?? null) ? (float) $input['interest_rate'] : (float) ($existing['interest_rate'] ?? 0),
                        ':tenure_months' => (int) ($input['tenure_months'] ?? $existing['tenure_months'] ?? 0),
                        ':processing_fee' => is_numeric($input['processing_fee'] ?? null) ? (float) $input['processing_fee'] : (float) ($existing['processing_fee'] ?? 0),
                        ':gst_rate' => is_numeric($input['gst_rate'] ?? null) ? (float) $input['gst_rate'] : (float) ($existing['gst_rate'] ?? 0),
                        ':emi_amount' => is_numeric($input['emi_amount'] ?? null) ? (float) $input['emi_amount'] : (float) ($existing['emi_amount'] ?? 0),
                        ':emi_start_date' => !empty($input['emi_start_date']) ? $input['emi_start_date'] : ($existing['emi_start_date'] ?? null),
                        ':points_balance' => is_numeric($input['points_balance'] ?? null) ? (float) $input['points_balance'] : (float) ($existing['points_balance'] ?? 0),
                    ]);
                } else {
                    $this->db->prepare(
                        'UPDATE credit_cards
                         SET bank_name = :bank_name,
                             card_name = :card_name,
                             credit_limit = :credit_limit,
                             billing_date = :billing_date,
                             due_date = :due_date,
                             outstanding_balance = :outstanding_balance,
                             outstanding_principal = :outstanding_principal,
                             interest_rate = :interest_rate,
                             tenure_months = :tenure_months,
                             processing_fee = :processing_fee,
                             gst_rate = :gst_rate,
                             emi_amount = :emi_amount,
                             emi_start_date = :emi_start_date,
                             points_balance = :points_balance,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE id = :id'
                    )->execute([
                        ':bank_name' => trim((string) ($input['bank_name'] ?? $existing['bank_name'] ?? '')),
                        ':card_name' => trim((string) ($input['card_name'] ?? $existing['card_name'] ?? $existing['account_name'] ?? '')),
                        ':credit_limit' => is_numeric($input['credit_limit'] ?? null) ? (float) $input['credit_limit'] : (float) ($existing['credit_limit'] ?? 0),
                        ':billing_date' => (int) ($input['billing_date'] ?? $existing['billing_date'] ?? 1),
                        ':due_date' => (int) ($input['due_date'] ?? $existing['due_date'] ?? 1),
                        ':outstanding_balance' => is_numeric($input['outstanding_balance'] ?? null) ? (float) $input['outstanding_balance'] : (float) ($existing['outstanding_balance'] ?? 0),
                        ':outstanding_principal' => is_numeric($input['outstanding_principal'] ?? null) ? (float) $input['outstanding_principal'] : (float) ($existing['outstanding_principal'] ?? 0),
                        ':interest_rate' => is_numeric($input['interest_rate'] ?? null) ? (float) $input['interest_rate'] : (float) ($existing['interest_rate'] ?? 0),
                        ':tenure_months' => (int) ($input['tenure_months'] ?? $existing['tenure_months'] ?? 0),
                        ':processing_fee' => is_numeric($input['processing_fee'] ?? null) ? (float) $input['processing_fee'] : (float) ($existing['processing_fee'] ?? 0),
                        ':gst_rate' => is_numeric($input['gst_rate'] ?? null) ? (float) $input['gst_rate'] : (float) ($existing['gst_rate'] ?? 0),
                        ':emi_amount' => is_numeric($input['emi_amount'] ?? null) ? (float) $input['emi_amount'] : (float) ($existing['emi_amount'] ?? 0),
                        ':emi_start_date' => !empty($input['emi_start_date']) ? $input['emi_start_date'] : ($existing['emi_start_date'] ?? null),
                        ':points_balance' => is_numeric($input['points_balance'] ?? null) ? (float) $input['points_balance'] : (float) ($existing['points_balance'] ?? 0),
                        ':id' => $cardId,
                    ]);
                }
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function getSummary(): array
    {
        $accounts = array_filter(
            $this->getAllWithBalances(),
            fn (array $row): bool => ($row['account_type'] ?? 'savings') !== 'credit_card'
        );
        $totalBalance = array_sum(array_column($accounts, 'balance'));

        return [
            'count' => count($accounts),
            'total_balance' => $totalBalance,
        ];
    }

    public function getList(): array
    {
        $stmt = $this->db->query(
            'SELECT a.id, a.bank_name, a.account_name, a.account_type, at.name AS account_type_name
             FROM accounts a
             LEFT JOIN account_types at ON at.id = a.account_type_id
             ORDER BY a.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public function getAccountTypes(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, system_key
             FROM account_types
             ORDER BY system_key IS NULL, name ASC'
        );
        return $stmt->fetchAll();
    }

    private function resolveAccountType(array $input, ?string $fallbackType): array
    {
        $accountTypeRaw = trim((string) ($input['account_type'] ?? ($fallbackType ?? 'savings')));
        $accountType = $accountTypeRaw;
        $accountTypeId = null;

        if (strpos($accountTypeRaw, 'custom:') === 0) {
            $accountType = 'other';
            $accountTypeId = (int) substr($accountTypeRaw, 7);
        } elseif ($accountTypeRaw === 'new') {
            $accountType = 'other';
            $customName = trim((string) ($input['new_account_type'] ?? ''));
            if ($customName !== '') {
                $accountTypeId = $this->findOrCreateAccountType($customName);
            }
        } elseif (!in_array($accountTypeRaw, self::SYSTEM_TYPES, true)) {
            $accountType = $fallbackType ?? 'savings';
        }

        if ($accountTypeId === null && in_array($accountType, self::SYSTEM_TYPES, true)) {
            $accountTypeId = $this->getAccountTypeIdBySystemKey($accountType);
        }

        return [$accountType, $accountTypeId];
    }

    private function findOrCreateAccountType(string $name): ?int
    {
        $cleanName = trim($name);
        if ($cleanName === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id FROM account_types WHERE name = :name LIMIT 1');
        $stmt->execute([':name' => $cleanName]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }

        $insert = $this->db->prepare('INSERT INTO account_types (name) VALUES (:name)');
        $insert->execute([':name' => $cleanName]);
        return (int) $this->db->lastInsertId();
    }

    private function getAccountTypeIdBySystemKey(string $systemKey): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM account_types WHERE system_key = :key LIMIT 1');
        $stmt->execute([':key' => $systemKey]);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }
}
