<?php

namespace Models;

use PDO;
use Models\CreditCard;

class Lending extends BaseModel
{
    private const LENDING_CATEGORY_ID = 27;
    private ?CreditCard $creditCardModel = null;

    public function create(array $input): bool
    {
        $contactId = (int) ($input['contact_id'] ?? 0);
        if ($contactId <= 0) {
            return false;
        }

        $principal = (float) ($input['principal_amount'] ?? 0);
        $interestRate = (float) ($input['interest_rate'] ?? 0);
        $lendingDate = $input['lending_date'] ?? date('Y-m-d');
        $dueDate = $input['due_date'] ?? null;
        $totalRepaid = (float) ($input['total_repaid'] ?? 0);
        $outstanding = $principal - $totalRepaid;

        if ($principal <= 0) {
            return false;
        }

        $sql = 'INSERT INTO lending_records (contact_id, principal_amount, interest_rate, lending_date, due_date, total_repaid, outstanding_amount, status, notes) VALUES (:contact_id, :principal_amount, :interest_rate, :lending_date, :due_date, :total_repaid, :outstanding_amount, :status, :notes)';
        $stmt = $this->db->prepare($sql);
        $created = $stmt->execute([
            ':contact_id' => $contactId,
            ':principal_amount' => $principal,
            ':interest_rate' => $interestRate,
            ':lending_date' => $lendingDate,
            ':due_date' => $dueDate,
            ':total_repaid' => $totalRepaid,
            ':outstanding_amount' => max(0, $outstanding),
            ':status' => $input['status'] ?? 'ongoing',
            ':notes' => $input['notes'] ?? null,
        ]);

        if (!$created) {
            return false;
        }

        $recordId = (int) $this->db->lastInsertId();
        $this->createLedgerTransactions($recordId, $contactId, $principal, $lendingDate, (string) ($input['funding_account'] ?? ''), (string) ($input['notes'] ?? ''));
        return true;
    }

    public function getContacts(): array
    {
        $stmt = $this->db->query('SELECT id, name, mobile, email FROM contacts ORDER BY name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOpenRecords(): array
    {
        $sql = <<<SQL
SELECT
    lr.id,
    GREATEST(0, lr.principal_amount - COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = lr.id), 0)) AS outstanding_amount,
    c.name AS contact_name
FROM lending_records lr
JOIN contacts c ON c.id = lr.contact_id
WHERE lr.status = 'ongoing'
  AND GREATEST(0, lr.principal_amount - COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = lr.id), 0)) > 0
ORDER BY lr.lending_date DESC
SQL;
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll(): array
    {
        $sql = <<<SQL
SELECT
    lr.*,
    COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = lr.id), 0) AS total_repaid,
    GREATEST(0, lr.principal_amount - COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = lr.id), 0)) AS outstanding_amount,
    c.name AS contact_name, c.mobile, c.email,
    ln.id AS linked_loan_id, ln.loan_name AS linked_loan_name
FROM lending_records lr
JOIN contacts c ON c.id = lr.contact_id
LEFT JOIN loans ln ON ln.linked_lending_id = lr.id
ORDER BY lr.lending_date DESC
SQL;
        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSummary(): array
    {
        $sql = <<<SQL
SELECT
    COUNT(*) AS total_records,
    COALESCE(SUM(GREATEST(0, lr.principal_amount - COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = lr.id), 0))), 0) AS total_outstanding
FROM lending_records lr
SQL;
        $stmt = $this->db->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'count' => (int) $row['total_records'],
            'outstanding' => (float) $row['total_outstanding'],
        ];
    }

    public function topUp(array $input): bool
    {
        $recordId      = (int) ($input['lending_record_id'] ?? 0);
        $amount        = max(0, (float) ($input['amount'] ?? 0));
        $topUpDate     = !empty($input['topup_date']) ? (string) $input['topup_date'] : date('Y-m-d');
        $fundingAccount = (string) ($input['funding_account'] ?? '');
        $notes         = trim((string) ($input['notes'] ?? ''));

        if ($recordId <= 0 || $amount <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT lr.*, c.name AS contact_name FROM lending_records lr JOIN contacts c ON c.id = lr.contact_id WHERE lr.id = :id LIMIT 1');
        $stmt->execute([':id' => $recordId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'UPDATE lending_records
                 SET principal_amount   = principal_amount + :amount,
                     outstanding_amount = outstanding_amount + :amount,
                     status             = CASE WHEN status = \'closed\' THEN \'ongoing\' ELSE status END,
                     updated_at         = CURRENT_TIMESTAMP
                 WHERE id = :id'
            )->execute([':amount' => $amount, ':id' => $recordId]);

            $entryNote = $notes !== '' ? $notes : 'Top-up lending — ' . ($record['contact_name'] ?? 'Contact');
            $this->createLedgerTransactions($recordId, (int) $record['contact_id'], $amount, $topUpDate, $fundingAccount, $entryNote);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function recordRepayment(array $input): bool
    {
        $recordId = (int) ($input['lending_record_id'] ?? 0);
        $amount = max(0, (float) ($input['repayment_amount'] ?? 0));
        $repaymentDate = !empty($input['repayment_date']) ? (string) $input['repayment_date'] : date('Y-m-d');
        $depositAccount = (string) ($input['deposit_account'] ?? '');
        $notes = trim((string) ($input['notes'] ?? ''));

        if ($recordId <= 0 || $amount <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT lr.id, lr.contact_id, lr.principal_amount,
                    COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = lr.id), 0) AS total_repaid,
                    GREATEST(0, lr.principal_amount - COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = lr.id), 0)) AS outstanding_amount,
                    c.name AS contact_name
             FROM lending_records lr
             JOIN contacts c ON c.id = lr.contact_id
             WHERE lr.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $recordId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            return false;
        }

        $payAmount = min($amount, (float) $record['outstanding_amount']);
        if ($payAmount <= 0) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            // Insert repayment row first so the sync query below counts it
            [$depType, $depId] = $depositAccount !== '' && strpos($depositAccount, ':') !== false
                ? explode(':', $depositAccount, 2)
                : [null, null];

            $this->db->prepare(
                'INSERT INTO lending_repayments (lending_record_id, amount, repayment_date, deposit_account_type, deposit_account_id, notes)
                 VALUES (:lending_record_id, :amount, :repayment_date, :deposit_account_type, :deposit_account_id, :notes)'
            )->execute([
                ':lending_record_id'    => $recordId,
                ':amount'               => $payAmount,
                ':repayment_date'       => $repaymentDate,
                ':deposit_account_type' => $depType,
                ':deposit_account_id'   => $depId !== null ? (int) $depId : null,
                ':notes'                => $notes !== '' ? $notes : null,
            ]);

            // Sync stored columns from live sum
            $this->db->prepare(
                'UPDATE lending_records
                 SET total_repaid      = COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = id), 0),
                     outstanding_amount = GREATEST(0, principal_amount - COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = id), 0)),
                     status             = CASE WHEN GREATEST(0, principal_amount - COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = id), 0)) <= 0 THEN \'closed\' ELSE status END,
                     updated_at         = CURRENT_TIMESTAMP
                 WHERE id = :id'
            )->execute([':id' => $recordId]);

            $this->createRepaymentLedgerTransactions(
                $recordId,
                (int) ($record['contact_id'] ?? 0),
                $record['contact_name'] ?? ('Contact #' . $record['contact_id']),
                $payAmount,
                $repaymentDate,
                $depositAccount,
                $notes
            );

            $this->db->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT lr.*,
                    COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = lr.id), 0) AS total_repaid,
                    GREATEST(0, lr.principal_amount - COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = lr.id), 0)) AS outstanding_amount,
                    c.name AS contact_name, c.email
             FROM lending_records lr
             JOIN contacts c ON c.id = lr.contact_id
             WHERE lr.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getRepaymentsByRecord(int $recordId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM lending_repayments WHERE lending_record_id = :id ORDER BY repayment_date DESC, created_at DESC'
        );
        $stmt->execute([':id' => $recordId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllRepayments(): array
    {
        $sql = <<<SQL
SELECT
    lr2.id AS repayment_id,
    lr2.lending_record_id,
    lr2.amount,
    lr2.repayment_date,
    lr2.deposit_account_type,
    lr2.deposit_account_id,
    lr2.notes,
    lr2.created_at,
    c.name AS contact_name
FROM lending_repayments lr2
JOIN lending_records lr ON lr.id = lr2.lending_record_id
JOIN contacts c ON c.id = lr.contact_id
ORDER BY lr2.repayment_date DESC, lr2.created_at DESC
SQL;
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(array $input): bool
    {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) return false;
        $stmt = $this->db->prepare(
            'UPDATE lending_records SET lending_date=:lending_date, interest_rate=:interest_rate, due_date=:due_date, status=:status, notes=:notes WHERE id=:id'
        );
        return $stmt->execute([
            ':lending_date' => !empty($input['lending_date']) ? $input['lending_date'] : date('Y-m-d'),
            ':interest_rate' => (float) ($input['interest_rate'] ?? 0),
            ':due_date' => !empty($input['due_date']) ? $input['due_date'] : null,
            ':status' => $input['status'] ?? 'ongoing',
            ':notes' => $input['notes'] ?? null,
            ':id' => $id,
        ]);
    }

    private function createLedgerTransactions(
        int $recordId,
        int $contactId,
        float $principal,
        string $lendingDate,
        string $fundingAccount,
        string $notes
    ): void {
        if ($recordId <= 0 || $contactId <= 0 || $principal <= 0 || $fundingAccount === '' || strpos($fundingAccount, ':') === false) {
            return;
        }

        [$accountType, $accountIdRaw] = explode(':', $fundingAccount, 2);
        $accountId = (int) $accountIdRaw;
        $allowedTypes = ['savings', 'current', 'cash', 'wallet', 'other', 'credit_card'];
        if ($accountId <= 0 || !in_array($accountType, $allowedTypes, true)) {
            return;
        }

        $entryNote = $notes !== '' ? $notes : 'Lending disbursal to contact #' . $contactId;

        // Money moved from selected account to lending exposure.
        $this->db->prepare(
            'INSERT INTO transactions (transaction_date, account_type, account_id, transaction_type, category_id, contact_id, amount, reference_type, reference_id, notes)
             VALUES (:transaction_date, :account_type, :account_id, \'expense\', :category_id, :contact_id, :amount, \'lending\', :reference_id, :notes)'
        )->execute([
            ':transaction_date' => $lendingDate,
            ':account_type'     => $accountType,
            ':account_id'       => $accountId,
            ':category_id'      => self::LENDING_CATEGORY_ID,
            ':contact_id'       => $contactId > 0 ? $contactId : null,
            ':amount'           => $principal,
            ':reference_id'     => $recordId,
            ':notes'            => $entryNote,
        ]);
        $this->applyCreditCardDeltaIfNeeded($accountType, $accountId, 'expense', $principal);
    }

    private function createRepaymentLedgerTransactions(
        int $recordId,
        int $contactId,
        string $contactName,
        float $amount,
        string $repaymentDate,
        string $depositAccount,
        string $notes
    ): void {
        if ($recordId <= 0 || $amount <= 0 || $depositAccount === '' || strpos($depositAccount, ':') === false) {
            return;
        }

        [$accountType, $accountIdRaw] = explode(':', $depositAccount, 2);
        $accountId = (int) $accountIdRaw;
        $allowedTypes = ['savings', 'current', 'cash', 'wallet', 'other', 'credit_card'];
        if ($accountId <= 0 || !in_array($accountType, $allowedTypes, true)) {
            return;
        }

        $entryNote = $notes !== '' ? $notes : ('Repayment from ' . $contactName);

        // Money moved from lending exposure into selected account.
        $this->db->prepare(
            'INSERT INTO transactions (transaction_date, account_type, account_id, transaction_type, category_id, contact_id, amount, reference_type, reference_id, notes)
             VALUES (:transaction_date, :account_type, :account_id, \'income\', :category_id, :contact_id, :amount, \'lending\', :reference_id, :notes)'
        )->execute([
            ':transaction_date' => $repaymentDate,
            ':account_type'     => $accountType,
            ':account_id'       => $accountId,
            ':category_id'      => self::LENDING_CATEGORY_ID,
            ':contact_id'       => $contactId > 0 ? $contactId : null,
            ':amount'           => $amount,
            ':reference_id'     => $recordId,
            ':notes'            => $entryNote,
        ]);
        $this->applyCreditCardDeltaIfNeeded($accountType, $accountId, 'income', $amount);
    }

    private function applyCreditCardDeltaIfNeeded(string $accountType, int $accountId, string $transactionType, float $amount): void
    {
        if ($accountType !== 'credit_card' || $amount <= 0) {
            return;
        }

        if ($this->creditCardModel === null) {
            $this->creditCardModel = new CreditCard($this->database);
        }

        $this->creditCardModel->applyTransactionMovementByAccount($accountId, $transactionType, $amount);
    }
}
