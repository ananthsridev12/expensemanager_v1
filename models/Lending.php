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
    lr.outstanding_amount,
    c.name AS contact_name
FROM lending_records lr
JOIN contacts c ON c.id = lr.contact_id
WHERE lr.status = 'ongoing' AND lr.outstanding_amount > 0
ORDER BY lr.lending_date DESC
SQL;
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll(): array
    {
        $sql = <<<SQL
SELECT
    lr.*, c.name AS contact_name, c.mobile, c.email
FROM lending_records lr
JOIN contacts c ON c.id = lr.contact_id
ORDER BY lr.lending_date DESC
SQL;
        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSummary(): array
    {
        $stmt = $this->db->query('SELECT COUNT(*) AS total_records, COALESCE(SUM(outstanding_amount),0) AS total_outstanding FROM lending_records');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'count' => (int) $row['total_records'],
            'outstanding' => (float) $row['total_outstanding'],
        ];
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
            'SELECT lr.id, lr.contact_id, lr.total_repaid, lr.outstanding_amount, c.name AS contact_name
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

        $newTotalRepaid = round((float) $record['total_repaid'] + $payAmount, 2);
        $newOutstanding = round(max(0, (float) $record['outstanding_amount'] - $payAmount), 2);
        $newStatus = $newOutstanding <= 0 ? 'closed' : 'ongoing';

        $this->db->beginTransaction();
        try {
            $update = $this->db->prepare(
                'UPDATE lending_records
                 SET total_repaid = :total_repaid,
                     outstanding_amount = :outstanding_amount,
                     status = :status,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $update->execute([
                ':total_repaid' => $newTotalRepaid,
                ':outstanding_amount' => $newOutstanding,
                ':status' => $newStatus,
                ':id' => $recordId,
            ]);

            $this->createRepaymentLedgerTransactions(
                $recordId,
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
            'SELECT lr.*, c.name AS contact_name FROM lending_records lr JOIN contacts c ON c.id = lr.contact_id WHERE lr.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
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
        $stmt = $this->db->prepare(
            'INSERT INTO transactions (transaction_date, account_type, account_id, transaction_type, category_id, amount, reference_type, reference_id, notes)
             VALUES (:transaction_date, :account_type, :account_id, :transaction_type, :category_id, :amount, :reference_type, :reference_id, :notes)'
        );

        // Money moved from selected account to lending exposure.
        $stmt->execute([
            ':transaction_date' => $lendingDate,
            ':account_type' => $accountType,
            ':account_id' => $accountId,
            ':transaction_type' => 'expense',
            ':category_id' => self::LENDING_CATEGORY_ID,
            ':amount' => $principal,
            ':reference_type' => 'lending',
            ':reference_id' => $recordId,
            ':notes' => $entryNote,
        ]);
        $this->applyCreditCardDeltaIfNeeded($accountType, $accountId, 'expense', $principal);

        // Mirror transfer entry for lending module.
        $stmt->execute([
            ':transaction_date' => $lendingDate,
            ':account_type' => 'lending',
            ':account_id' => null,
            ':transaction_type' => 'transfer',
            ':category_id' => self::LENDING_CATEGORY_ID,
            ':amount' => $principal,
            ':reference_type' => 'lending',
            ':reference_id' => $recordId,
            ':notes' => $entryNote,
        ]);
    }

    private function createRepaymentLedgerTransactions(
        int $recordId,
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
        $stmt = $this->db->prepare(
            'INSERT INTO transactions (transaction_date, account_type, account_id, transaction_type, category_id, amount, reference_type, reference_id, notes)
             VALUES (:transaction_date, :account_type, :account_id, :transaction_type, :category_id, :amount, :reference_type, :reference_id, :notes)'
        );

        // Money moved from lending exposure into selected account.
        $stmt->execute([
            ':transaction_date' => $repaymentDate,
            ':account_type' => $accountType,
            ':account_id' => $accountId,
            ':transaction_type' => 'income',
            ':category_id' => self::LENDING_CATEGORY_ID,
            ':amount' => $amount,
            ':reference_type' => 'lending',
            ':reference_id' => $recordId,
            ':notes' => $entryNote,
        ]);
        $this->applyCreditCardDeltaIfNeeded($accountType, $accountId, 'income', $amount);

        // Mirror transfer entry for lending module.
        $stmt->execute([
            ':transaction_date' => $repaymentDate,
            ':account_type' => 'lending',
            ':account_id' => null,
            ':transaction_type' => 'transfer',
            ':category_id' => self::LENDING_CATEGORY_ID,
            ':amount' => $amount,
            ':reference_type' => 'lending',
            ':reference_id' => $recordId,
            ':notes' => $entryNote,
        ]);
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
