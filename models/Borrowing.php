<?php

namespace Models;

use PDO;
use Models\CreditCard;

class Borrowing extends BaseModel
{
    private ?CreditCard $creditCardModel = null;

    public function create(array $input): bool
    {
        $contactId    = (int) ($input['contact_id'] ?? 0);
        $principal    = (float) ($input['principal_amount'] ?? 0);
        if ($contactId <= 0 || $principal <= 0) return false;

        $interestRate   = (float) ($input['interest_rate'] ?? 0);
        $borrowedDate   = !empty($input['borrowed_date']) ? $input['borrowed_date'] : date('Y-m-d');
        $dueDate        = !empty($input['due_date']) ? $input['due_date'] : null;
        $notes          = trim((string) ($input['notes'] ?? ''));

        $stmt = $this->db->prepare(
            'INSERT INTO borrowing_records
                (contact_id, principal_amount, interest_rate, borrowed_date, due_date,
                 total_repaid, outstanding_amount, status, notes)
             VALUES
                (:contact_id, :principal_amount, :interest_rate, :borrowed_date, :due_date,
                 0, :outstanding_amount, :status, :notes)'
        );
        $ok = $stmt->execute([
            ':contact_id'        => $contactId,
            ':principal_amount'  => $principal,
            ':interest_rate'     => $interestRate,
            ':borrowed_date'     => $borrowedDate,
            ':due_date'          => $dueDate,
            ':outstanding_amount'=> $principal,
            ':status'            => 'ongoing',
            ':notes'             => $notes !== '' ? $notes : null,
        ]);

        if (!$ok) return false;

        $recordId = (int) $this->db->lastInsertId();
        $this->createReceiptLedger($recordId, $contactId, $principal, $borrowedDate, (string) ($input['deposit_account'] ?? ''), $notes);
        return true;
    }

    public function recordRepayment(array $input): bool
    {
        $recordId      = (int) ($input['borrowing_record_id'] ?? 0);
        $amount        = (float) ($input['repayment_amount'] ?? 0);
        $repaymentDate = !empty($input['repayment_date']) ? $input['repayment_date'] : date('Y-m-d');
        $payAccount    = (string) ($input['payment_account'] ?? '');
        $notes         = trim((string) ($input['notes'] ?? ''));

        if ($recordId <= 0 || $amount <= 0) return false;

        $stmt = $this->db->prepare(
            'SELECT br.*, c.name AS contact_name,
                    GREATEST(0, br.principal_amount - COALESCE(
                        (SELECT SUM(p.amount) FROM borrowing_repayments p WHERE p.borrowing_record_id = br.id), 0
                    )) AS live_outstanding
             FROM borrowing_records br
             JOIN contacts c ON c.id = br.contact_id
             WHERE br.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $recordId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) return false;

        $payAmount = min($amount, (float) $record['live_outstanding']);
        if ($payAmount <= 0) return false;

        $this->db->beginTransaction();
        try {
            [$payType, $payId] = $payAccount !== '' && strpos($payAccount, ':') !== false
                ? explode(':', $payAccount, 2) : [null, null];

            $this->db->prepare(
                'INSERT INTO borrowing_repayments
                    (borrowing_record_id, amount, repayment_date, payment_account_type, payment_account_id, notes)
                 VALUES (:record_id, :amount, :date, :acct_type, :acct_id, :notes)'
            )->execute([
                ':record_id' => $recordId,
                ':amount'    => $payAmount,
                ':date'      => $repaymentDate,
                ':acct_type' => $payType,
                ':acct_id'   => $payId !== null ? (int) $payId : null,
                ':notes'     => $notes !== '' ? $notes : null,
            ]);

            // Compute new totals via separate query to avoid correlated-subquery id ambiguity in UPDATE SET
            $totalsStmt = $this->db->prepare('SELECT COALESCE(SUM(amount), 0) FROM borrowing_repayments WHERE borrowing_record_id = :id');
            $totalsStmt->execute([':id' => $recordId]);
            $sumRepaid      = (float) $totalsStmt->fetchColumn();
            $newOutstanding = max(0.0, (float) $record['principal_amount'] - $sumRepaid);
            $newStatus      = $newOutstanding <= 0 ? 'closed' : 'ongoing';
            $this->db->prepare(
                'UPDATE borrowing_records SET total_repaid=:r, outstanding_amount=:o, status=:s, updated_at=CURRENT_TIMESTAMP WHERE id=:id'
            )->execute([':r' => $sumRepaid, ':o' => $newOutstanding, ':s' => $newStatus, ':id' => $recordId]);

            $this->createRepaymentLedger(
                $recordId,
                (int) ($record['contact_id'] ?? 0),
                (string) ($record['contact_name'] ?? ''),
                $payAmount,
                $repaymentDate,
                $payAccount,
                $notes
            );

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }

    public function getAll(): array
    {
        $sql = <<<SQL
SELECT
    br.*,
    COALESCE((SELECT SUM(p.amount) FROM borrowing_repayments p WHERE p.borrowing_record_id = br.id), 0) AS total_repaid,
    GREATEST(0, br.principal_amount - COALESCE((SELECT SUM(p.amount) FROM borrowing_repayments p WHERE p.borrowing_record_id = br.id), 0)) AS outstanding_amount,
    c.name AS contact_name, c.mobile, c.email
FROM borrowing_records br
JOIN contacts c ON c.id = br.contact_id
ORDER BY br.borrowed_date DESC
SQL;
        return $this->db->query($sql)->fetchAll();
    }

    public function getOpenRecords(): array
    {
        $sql = <<<SQL
SELECT
    br.id,
    GREATEST(0, br.principal_amount - COALESCE((SELECT SUM(p.amount) FROM borrowing_repayments p WHERE p.borrowing_record_id = br.id), 0)) AS outstanding_amount,
    c.name AS contact_name
FROM borrowing_records br
JOIN contacts c ON c.id = br.contact_id
WHERE br.status = 'ongoing'
HAVING outstanding_amount > 0
ORDER BY br.borrowed_date DESC
SQL;
        return $this->db->query($sql)->fetchAll();
    }

    public function getSummary(): array
    {
        $sql = <<<SQL
SELECT
    COUNT(*) AS total_records,
    COALESCE(SUM(GREATEST(0, br.principal_amount - COALESCE(
        (SELECT SUM(p.amount) FROM borrowing_repayments p WHERE p.borrowing_record_id = br.id), 0
    ))), 0) AS total_outstanding
FROM borrowing_records br
SQL;
        $row = $this->db->query($sql)->fetch();
        return [
            'count'       => (int) $row['total_records'],
            'outstanding' => (float) $row['total_outstanding'],
        ];
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT br.*, c.name AS contact_name, c.email,
                    COALESCE((SELECT SUM(p.amount) FROM borrowing_repayments p WHERE p.borrowing_record_id = br.id), 0) AS total_repaid,
                    GREATEST(0, br.principal_amount - COALESCE((SELECT SUM(p.amount) FROM borrowing_repayments p WHERE p.borrowing_record_id = br.id), 0)) AS outstanding_amount
             FROM borrowing_records br
             JOIN contacts c ON c.id = br.contact_id
             WHERE br.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getRepaymentsByRecord(int $recordId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM borrowing_repayments WHERE borrowing_record_id = :id ORDER BY repayment_date DESC, created_at DESC'
        );
        $stmt->execute([':id' => $recordId]);
        return $stmt->fetchAll();
    }

    public function getAllRepayments(): array
    {
        $sql = <<<SQL
SELECT
    p.id AS repayment_id, p.borrowing_record_id, p.amount,
    p.repayment_date, p.payment_account_type, p.payment_account_id,
    p.notes, p.created_at,
    c.name AS contact_name, c.mobile, c.email
FROM borrowing_repayments p
JOIN borrowing_records br ON br.id = p.borrowing_record_id
JOIN contacts c ON c.id = br.contact_id
ORDER BY p.repayment_date DESC, p.created_at DESC
SQL;
        return $this->db->query($sql)->fetchAll();
    }

    public function getRepaymentById(int $repaymentId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT brp.*, br.contact_id, br.principal_amount, c.name AS contact_name, c.email,
                    GREATEST(0, br.principal_amount - COALESCE((SELECT SUM(x.amount) FROM borrowing_repayments x WHERE x.borrowing_record_id = br.id), 0)) AS outstanding_amount
             FROM borrowing_repayments brp
             JOIN borrowing_records br ON br.id = brp.borrowing_record_id
             JOIN contacts c ON c.id = br.contact_id
             WHERE brp.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $repaymentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function deleteRepayment(int $repaymentId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT brp.*, br.principal_amount FROM borrowing_repayments brp
             JOIN borrowing_records br ON br.id = brp.borrowing_record_id
             WHERE brp.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $repaymentId]);
        $rep = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rep) return false;

        $recordId = (int) $rep['borrowing_record_id'];
        $amount   = (float) $rep['amount'];
        $date     = $rep['repayment_date'];
        $acctType = $rep['payment_account_type'] ?? null;
        $acctId   = (int) ($rep['payment_account_id'] ?? 0);

        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM borrowing_repayments WHERE id = :id')
                ->execute([':id' => $repaymentId]);

            $this->db->prepare(
                "DELETE FROM transactions WHERE reference_type='borrowing' AND reference_id=:ref AND transaction_type='expense' AND amount=:amt AND transaction_date=:date LIMIT 1"
            )->execute([':ref' => $recordId, ':amt' => $amount, ':date' => $date]);

            if ($acctType === 'credit_card' && $acctId > 0) {
                $this->applyCreditCardDeltaIfNeeded($acctType, $acctId, 'income', $amount);
            }

            $totalsStmt = $this->db->prepare('SELECT COALESCE(SUM(amount), 0) FROM borrowing_repayments WHERE borrowing_record_id = :id');
            $totalsStmt->execute([':id' => $recordId]);
            $sumRepaid      = (float) $totalsStmt->fetchColumn();
            $newOutstanding = max(0.0, (float) $rep['principal_amount'] - $sumRepaid);
            $newStatus      = $newOutstanding <= 0 ? 'closed' : 'ongoing';
            $this->db->prepare(
                'UPDATE borrowing_records SET total_repaid=:r, outstanding_amount=:o, status=:s, updated_at=CURRENT_TIMESTAMP WHERE id=:id'
            )->execute([':r' => $sumRepaid, ':o' => $newOutstanding, ':s' => $newStatus, ':id' => $recordId]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }

    public function update(array $input): bool
    {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) return false;
        $stmt = $this->db->prepare(
            'UPDATE borrowing_records
             SET borrowed_date=:borrowed_date, interest_rate=:interest_rate,
                 due_date=:due_date, status=:status, notes=:notes
             WHERE id=:id'
        );
        return $stmt->execute([
            ':borrowed_date'  => !empty($input['borrowed_date']) ? $input['borrowed_date'] : date('Y-m-d'),
            ':interest_rate'  => (float) ($input['interest_rate'] ?? 0),
            ':due_date'       => !empty($input['due_date']) ? $input['due_date'] : null,
            ':status'         => $input['status'] ?? 'ongoing',
            ':notes'          => !empty($input['notes']) ? $input['notes'] : null,
            ':id'             => $id,
        ]);
    }

    // ── Ledger helpers ──────────────────────────────────────────────────────

    /**
     * When borrowing: money received → income on user's account.
     */
    private function createReceiptLedger(
        int $recordId, int $contactId, float $amount,
        string $date, string $depositAccount, string $notes
    ): void {
        if ($recordId <= 0 || $amount <= 0 || $depositAccount === '' || strpos($depositAccount, ':') === false) return;

        [$acctType, $acctIdRaw] = explode(':', $depositAccount, 2);
        $acctId = (int) $acctIdRaw;
        $allowed = ['savings', 'current', 'cash', 'wallet', 'other', 'credit_card'];
        if ($acctId <= 0 || !in_array($acctType, $allowed, true)) return;

        $note = $notes !== '' ? $notes : 'Borrowed from contact #' . $contactId;

        // Income into user's account
        $this->db->prepare(
            'INSERT INTO transactions (transaction_date, account_type, account_id, transaction_type, category_id, contact_id, amount, reference_type, reference_id, notes)
             VALUES (:date, :acct_type, :acct_id, \'income\', 33, :contact_id, :amount, \'borrowing\', :ref_id, :notes)'
        )->execute([
            ':date'       => $date,
            ':acct_type'  => $acctType,
            ':acct_id'    => $acctId,
            ':contact_id' => $contactId > 0 ? $contactId : null,
            ':amount'     => $amount,
            ':ref_id'     => $recordId,
            ':notes'      => $note,
        ]);
        $this->applyCreditCardDeltaIfNeeded($acctType, $acctId, 'income', $amount);
    }

    /**
     * When repaying: money paid out → expense on user's account.
     */
    private function createRepaymentLedger(
        int $recordId, int $contactId, string $contactName, float $amount,
        string $date, string $payAccount, string $notes
    ): void {
        if ($recordId <= 0 || $amount <= 0 || $payAccount === '' || strpos($payAccount, ':') === false) return;

        [$acctType, $acctIdRaw] = explode(':', $payAccount, 2);
        $acctId = (int) $acctIdRaw;
        $allowed = ['savings', 'current', 'cash', 'wallet', 'other', 'credit_card'];
        if ($acctId <= 0 || !in_array($acctType, $allowed, true)) return;

        $note = $notes !== '' ? $notes : 'Repayment to ' . $contactName;

        // Expense from user's account
        $this->db->prepare(
            'INSERT INTO transactions (transaction_date, account_type, account_id, transaction_type, category_id, contact_id, amount, reference_type, reference_id, notes)
             VALUES (:date, :acct_type, :acct_id, \'expense\', 32, :contact_id, :amount, \'borrowing\', :ref_id, :notes)'
        )->execute([
            ':date'       => $date,
            ':acct_type'  => $acctType,
            ':acct_id'    => $acctId,
            ':contact_id' => $contactId > 0 ? $contactId : null,
            ':amount'     => $amount,
            ':ref_id'     => $recordId,
            ':notes'      => $note,
        ]);
        $this->applyCreditCardDeltaIfNeeded($acctType, $acctId, 'expense', $amount);
    }

    private function applyCreditCardDeltaIfNeeded(string $accountType, int $accountId, string $transactionType, float $amount): void
    {
        if ($accountType !== 'credit_card' || $amount <= 0) return;
        if ($this->creditCardModel === null) {
            $this->creditCardModel = new CreditCard($this->database);
        }
        $this->creditCardModel->applyTransactionMovementByAccount($accountId, $transactionType, $amount);
    }
}
