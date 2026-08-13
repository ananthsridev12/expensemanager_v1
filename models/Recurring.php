<?php

namespace Models;

use PDO;

class Recurring extends BaseModel
{
    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $input): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO recurring_transactions
               (name, transaction_type, account_type, account_id, amount,
                category_id, subcategory_id, payment_method_id, contact_id,
                notes, frequency, day_of_month, next_due_date, end_date)
             VALUES
               (:name, :tx_type, :acct_type, :acct_id, :amount,
                :cat_id, :sub_id, :pm_id, :contact_id,
                :notes, :freq, :dom, :next_due, :end_date)'
        );
        $ok = $stmt->execute([
            ':name'      => trim((string) ($input['name'] ?? '')),
            ':tx_type'   => in_array($input['transaction_type'] ?? '', ['income', 'expense'], true) ? $input['transaction_type'] : 'expense',
            ':acct_type' => $input['account_type'] ?? 'savings',
            ':acct_id'   => !empty($input['account_id']) ? (int) $input['account_id'] : null,
            ':amount'    => is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : 0.0,
            ':cat_id'    => !empty($input['category_id'])       ? (int) $input['category_id']       : null,
            ':sub_id'    => !empty($input['subcategory_id'])    ? (int) $input['subcategory_id']    : null,
            ':pm_id'     => !empty($input['payment_method_id']) ? (int) $input['payment_method_id'] : null,
            ':contact_id'=> !empty($input['contact_id'])        ? (int) $input['contact_id']        : null,
            ':notes'     => $input['notes'] !== '' ? ($input['notes'] ?? null) : null,
            ':freq'      => in_array($input['frequency'] ?? '', ['daily', 'weekly', 'monthly', 'yearly'], true) ? $input['frequency'] : 'monthly',
            ':dom'       => !empty($input['day_of_month']) ? (int) $input['day_of_month'] : null,
            ':next_due'  => $input['next_due_date'] ?? date('Y-m-d'),
            ':end_date'  => !empty($input['end_date']) ? $input['end_date'] : null,
        ]);
        return $ok ? (int) $this->db->lastInsertId() : 0;
    }

    public function getAll(): array
    {
        return $this->db->query(
            "SELECT r.*,
                    c.name  AS category_name,
                    sc.name AS subcategory_name,
                    pm.name AS payment_method_name,
                    CASE
                        WHEN a.account_type = 'credit_card' THEN COALESCE(cc.bank_name, a.bank_name)
                        ELSE a.bank_name
                    END AS bank_name,
                    CASE
                        WHEN a.account_type = 'credit_card' THEN COALESCE(cc.card_name, a.account_name)
                        ELSE a.account_name
                    END AS account_name
               FROM recurring_transactions r
               LEFT JOIN categories c      ON c.id  = r.category_id
               LEFT JOIN subcategories sc  ON sc.id = r.subcategory_id
               LEFT JOIN payment_methods pm ON pm.id = r.payment_method_id
               LEFT JOIN accounts a        ON a.id  = r.account_id
               LEFT JOIN credit_cards cc   ON cc.account_id = a.id
              WHERE r.is_active = 1
              ORDER BY r.next_due_date ASC, r.name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deactivate(int $id): void
    {
        $this->db->prepare('UPDATE recurring_transactions SET is_active = 0 WHERE id = :id')
                 ->execute([':id' => $id]);
    }

    // ── Pending generation ────────────────────────────────────────────────────

    /**
     * For every active recurring transaction that is due (next_due_date <= today),
     * insert a pending row unless one already exists for that recurring + date.
     * Also deactivates records that have passed their end_date.
     */
    public function generatePending(): void
    {
        $today = date('Y-m-d');
        $stmt  = $this->db->prepare(
            "SELECT * FROM recurring_transactions
              WHERE is_active = 1 AND next_due_date <= :today"
        );
        $stmt->execute([':today' => $today]);
        $due = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $checkExists = $this->db->prepare(
            "SELECT 1 FROM recurring_pending
              WHERE recurring_id = :rid AND due_date = :date AND status != 'skipped'
              LIMIT 1"
        );

        foreach ($due as $rec) {
            if (!empty($rec['end_date']) && $rec['next_due_date'] > $rec['end_date']) {
                $this->db->prepare('UPDATE recurring_transactions SET is_active = 0 WHERE id = :id')
                         ->execute([':id' => $rec['id']]);
                continue;
            }

            $checkExists->execute([':rid' => $rec['id'], ':date' => $rec['next_due_date']]);
            if ($checkExists->fetchColumn()) continue;

            $this->db->prepare(
                "INSERT INTO recurring_pending (recurring_id, due_date, status)
                 VALUES (:rid, :date, 'pending')"
            )->execute([':rid' => $rec['id'], ':date' => $rec['next_due_date']]);
        }
    }

    // ── Pending review ────────────────────────────────────────────────────────

    public function getPendingItems(): array
    {
        return $this->db->query(
            "SELECT rp.id AS pending_id, rp.due_date, rp.status,
                    r.id AS recurring_id, r.name, r.transaction_type,
                    r.account_type, r.account_id, r.amount, r.notes,
                    r.frequency, r.category_id, r.subcategory_id,
                    r.payment_method_id, r.contact_id,
                    c.name  AS category_name,
                    sc.name AS subcategory_name,
                    pm.name AS payment_method_name,
                    ct.name AS contact_name,
                    CASE
                        WHEN a.account_type = 'credit_card' THEN COALESCE(cc.bank_name, a.bank_name)
                        ELSE a.bank_name
                    END AS bank_name,
                    CASE
                        WHEN a.account_type = 'credit_card' THEN COALESCE(cc.card_name, a.account_name)
                        ELSE a.account_name
                    END AS account_name
               FROM recurring_pending rp
               JOIN recurring_transactions r ON r.id = rp.recurring_id
               LEFT JOIN categories c        ON c.id  = r.category_id
               LEFT JOIN subcategories sc    ON sc.id = r.subcategory_id
               LEFT JOIN payment_methods pm  ON pm.id = r.payment_method_id
               LEFT JOIN contacts ct         ON ct.id = r.contact_id
               LEFT JOIN accounts a          ON a.id  = r.account_id
               LEFT JOIN credit_cards cc     ON cc.account_id = a.id
              WHERE rp.status = 'pending'
              ORDER BY rp.due_date ASC, r.name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPending(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM recurring_pending WHERE status = 'pending'"
        )->fetchColumn();
    }

    public function approvePending(int $pendingId, array $data): int
    {
        $stmt = $this->db->prepare(
            'SELECT rp.due_date, r.id AS rec_id, r.frequency, r.day_of_month,
                    r.account_type, r.account_id, r.transaction_type,
                    r.category_id, r.subcategory_id, r.payment_method_id,
                    r.contact_id, r.amount, r.notes
               FROM recurring_pending rp
               JOIN recurring_transactions r ON r.id = rp.recurring_id
              WHERE rp.id = :id AND rp.status = \'pending\'
              LIMIT 1'
        );
        $stmt->execute([':id' => $pendingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException('Pending item not found or already processed.');
        }

        $txModel = new Transaction($this->database);
        $txId    = $txModel->create([
            'transaction_date'  => $data['transaction_date']   ?? $row['due_date'],
            'account_type'      => $row['account_type'],
            'account_id'        => $row['account_id'],
            'transaction_type'  => $row['transaction_type'],
            'category_id'       => !empty($data['category_id'])       ? (int) $data['category_id']       : $row['category_id'],
            'subcategory_id'    => !empty($data['subcategory_id'])     ? (int) $data['subcategory_id']     : $row['subcategory_id'],
            'payment_method_id' => !empty($data['payment_method_id'])  ? (int) $data['payment_method_id']  : $row['payment_method_id'],
            'contact_id'        => !empty($data['contact_id'])         ? (int) $data['contact_id']         : $row['contact_id'],
            'amount'            => is_numeric($data['amount'] ?? null)  ? (float) $data['amount']           : (float) $row['amount'],
            'notes'             => trim((string) ($data['notes'] ?? $row['notes'] ?? '')),
            'reference_type'    => 'recurring',
            'reference_id'      => (int) $row['rec_id'],
        ]);

        $this->db->prepare(
            "UPDATE recurring_pending
                SET status = 'approved', transaction_id = :txid, reviewed_at = NOW()
              WHERE id = :id"
        )->execute([':txid' => $txId, ':id' => $pendingId]);

        $this->advanceDueDate((int) $row['rec_id'], $row['due_date'], $row['frequency'], !empty($row['day_of_month']) ? (int) $row['day_of_month'] : null);

        return $txId;
    }

    public function skipPending(int $pendingId): void
    {
        $stmt = $this->db->prepare(
            'SELECT rp.due_date, r.id AS rec_id, r.frequency, r.day_of_month
               FROM recurring_pending rp
               JOIN recurring_transactions r ON r.id = rp.recurring_id
              WHERE rp.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $pendingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;

        $this->db->prepare(
            "UPDATE recurring_pending SET status = 'skipped', reviewed_at = NOW() WHERE id = :id"
        )->execute([':id' => $pendingId]);

        $this->advanceDueDate((int) $row['rec_id'], $row['due_date'], $row['frequency'], !empty($row['day_of_month']) ? (int) $row['day_of_month'] : null);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function advanceDueDate(int $recId, string $fromDate, string $frequency, ?int $dayOfMonth): void
    {
        $next = $this->computeNextDueDate($frequency, $fromDate, $dayOfMonth);
        $this->db->prepare('UPDATE recurring_transactions SET next_due_date = :next WHERE id = :id')
                 ->execute([':next' => $next, ':id' => $recId]);
    }

    public function computeNextDueDate(string $frequency, string $fromDate, ?int $dayOfMonth = null): string
    {
        $dt = new \DateTime($fromDate);
        switch ($frequency) {
            case 'daily':
                $dt->modify('+1 day');
                break;
            case 'weekly':
                $dt->modify('+1 week');
                break;
            case 'yearly':
                $dt->modify('+1 year');
                break;
            default: // monthly
                if ($dayOfMonth !== null && $dayOfMonth >= 1 && $dayOfMonth <= 31) {
                    $dt->modify('first day of next month');
                    $day = min($dayOfMonth, (int) $dt->format('t'));
                    $dt->setDate((int) $dt->format('Y'), (int) $dt->format('n'), $day);
                } else {
                    $dt->modify('+1 month');
                }
        }
        return $dt->format('Y-m-d');
    }
}
