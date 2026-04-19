<?php

namespace Models;

use PDO;

class RentedHome extends BaseModel
{
    // category_id / subcategory_id auto-mapped by expense_type
    private const CATEGORY_MAP = [
        'advance'     => ['category_id' => 13,   'subcategory_id' => null],
        'rent'        => ['category_id' => 16,   'subcategory_id' => 28],
        'maintenance' => ['category_id' => 16,   'subcategory_id' => 23],
        'electricity' => ['category_id' => 18,   'subcategory_id' => null],
        'other'       => ['category_id' => null, 'subcategory_id' => null],
    ];

    public function getAll(): array
    {
        return $this->db->query(
            'SELECT * FROM rented_homes ORDER BY status ASC, created_at DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActive(): array
    {
        return $this->db->query(
            "SELECT * FROM rented_homes WHERE status = 'active' ORDER BY label ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM rented_homes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $input): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO rented_homes (label, landlord_name, address, monthly_rent, advance_amount, maintenance_amount, start_date, notes, status)
             VALUES (:label, :landlord_name, :address, :monthly_rent, :advance_amount, :maintenance_amount, :start_date, :notes, :status)'
        );
        $ok = $stmt->execute([
            ':label'              => trim($input['label'] ?? ''),
            ':landlord_name'      => trim($input['landlord_name'] ?? '') ?: null,
            ':address'            => trim($input['address'] ?? '') ?: null,
            ':monthly_rent'       => is_numeric($input['monthly_rent'] ?? null) ? (float) $input['monthly_rent'] : 0,
            ':advance_amount'     => is_numeric($input['advance_amount'] ?? null) ? (float) $input['advance_amount'] : 0,
            ':maintenance_amount' => is_numeric($input['maintenance_amount'] ?? null) ? (float) $input['maintenance_amount'] : 0,
            ':start_date'         => !empty($input['start_date']) ? $input['start_date'] : null,
            ':notes'              => trim($input['notes'] ?? '') ?: null,
            ':status'             => in_array($input['status'] ?? '', ['active','vacated']) ? $input['status'] : 'active',
        ]);
        return $ok ? (int) $this->db->lastInsertId() : 0;
    }

    public function update(array $input): bool
    {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) return false;
        $stmt = $this->db->prepare(
            'UPDATE rented_homes SET label=:label, landlord_name=:landlord_name, address=:address,
             monthly_rent=:monthly_rent, advance_amount=:advance_amount, maintenance_amount=:maintenance_amount,
             start_date=:start_date, notes=:notes, status=:status WHERE id=:id'
        );
        return $stmt->execute([
            ':label'              => trim($input['label'] ?? ''),
            ':landlord_name'      => trim($input['landlord_name'] ?? '') ?: null,
            ':address'            => trim($input['address'] ?? '') ?: null,
            ':monthly_rent'       => is_numeric($input['monthly_rent'] ?? null) ? (float) $input['monthly_rent'] : 0,
            ':advance_amount'     => is_numeric($input['advance_amount'] ?? null) ? (float) $input['advance_amount'] : 0,
            ':maintenance_amount' => is_numeric($input['maintenance_amount'] ?? null) ? (float) $input['maintenance_amount'] : 0,
            ':start_date'         => !empty($input['start_date']) ? $input['start_date'] : null,
            ':notes'              => trim($input['notes'] ?? '') ?: null,
            ':status'             => in_array($input['status'] ?? '', ['active','vacated']) ? $input['status'] : 'active',
            ':id'                 => $id,
        ]);
    }

    public function getExpenses(int $homeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT rhe.*, rh.label AS home_label, a.bank_name, a.account_name
             FROM rented_home_expenses rhe
             JOIN rented_homes rh ON rh.id = rhe.home_id
             LEFT JOIN accounts a ON a.id = rhe.account_id
             WHERE rhe.home_id = :home_id
             ORDER BY rhe.expense_date DESC, rhe.created_at DESC'
        );
        $stmt->execute([':home_id' => $homeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentExpenses(int $limit = 15): array
    {
        $stmt = $this->db->prepare(
            'SELECT rhe.*, rh.label AS home_label, a.bank_name, a.account_name
             FROM rented_home_expenses rhe
             JOIN rented_homes rh ON rh.id = rhe.home_id
             LEFT JOIN accounts a ON a.id = rhe.account_id
             ORDER BY rhe.expense_date DESC, rhe.created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record an expense (advance / rent / maintenance / electricity / other).
     * Creates the rented_home_expenses record AND an expense entry in the
     * master transactions ledger.
     *
     * $input keys:
     *   home_id, expense_type, amount, expense_date, account_token (type:id),
     *   period_month (optional), notes (optional)
     */
    public function recordExpense(array $input): bool
    {
        $homeId      = (int) ($input['home_id'] ?? 0);
        $expType     = $input['expense_type'] ?? 'other';
        $amount      = is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : 0;
        $date        = !empty($input['expense_date']) ? $input['expense_date'] : date('Y-m-d');
        $token       = (string) ($input['account_token'] ?? '');
        $periodMonth = !empty($input['period_month']) ? $input['period_month'] . '-01' : null;
        $notes       = trim($input['notes'] ?? '') ?: null;

        if ($homeId <= 0 || $amount <= 0) return false;

        // Parse account token
        $accountId = null;
        $accountType = null;
        if ($token !== '' && strpos($token, ':') !== false) {
            [$accountType, $accountIdRaw] = explode(':', $token, 2);
            $accountId = (int) $accountIdRaw;
            $allowed = ['savings', 'current', 'cash', 'wallet', 'other', 'credit_card'];
            if ($accountId <= 0 || !in_array($accountType, $allowed, true)) {
                $accountId = null;
                $accountType = null;
            }
        }

        $catMap = self::CATEGORY_MAP[$expType] ?? self::CATEGORY_MAP['other'];

        // Insert rented_home_expenses record
        $stmt = $this->db->prepare(
            'INSERT INTO rented_home_expenses (home_id, expense_type, amount, expense_date, account_id, period_month, notes)
             VALUES (:home_id, :expense_type, :amount, :expense_date, :account_id, :period_month, :notes)'
        );
        $ok = $stmt->execute([
            ':home_id'      => $homeId,
            ':expense_type' => $expType,
            ':amount'       => $amount,
            ':expense_date' => $date,
            ':account_id'   => $accountId,
            ':period_month' => $periodMonth,
            ':notes'        => $notes,
        ]);
        if (!$ok) return false;

        $expenseId = (int) $this->db->lastInsertId();

        // Insert into master transactions ledger
        if ($accountId !== null && $accountType !== null) {
            $home = $this->getById($homeId);
            $autoNote = $notes ?? ucfirst($expType) . ' — ' . ($home['label'] ?? 'Rented Home');
            $this->db->prepare(
                'INSERT INTO transactions
                    (transaction_date, account_type, account_id, transaction_type, category_id, subcategory_id, amount, reference_type, reference_id, notes)
                 VALUES
                    (:date, :acct_type, :acct_id, \'expense\', :category_id, :subcategory_id, :amount, \'rented_home\', :ref_id, :notes)'
            )->execute([
                ':date'          => $date,
                ':acct_type'     => $accountType,
                ':acct_id'       => $accountId,
                ':category_id'   => $catMap['category_id'],
                ':subcategory_id'=> $catMap['subcategory_id'],
                ':amount'        => $amount,
                ':ref_id'        => $expenseId,
                ':notes'         => $autoNote,
            ]);
        }

        return true;
    }

    public function getSummary(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total_homes,
                SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_homes,
                SUM(CASE WHEN status='active' THEN monthly_rent ELSE 0 END) AS monthly_committed,
                SUM(CASE WHEN status='active' THEN advance_amount ELSE 0 END) AS advance_committed
             FROM rented_homes"
        )->fetch(PDO::FETCH_ASSOC);
        return [
            'total_homes'       => (int) ($row['total_homes'] ?? 0),
            'active_homes'      => (int) ($row['active_homes'] ?? 0),
            'monthly_committed' => (float) ($row['monthly_committed'] ?? 0),
            'advance_committed' => (float) ($row['advance_committed'] ?? 0),
        ];
    }
}
