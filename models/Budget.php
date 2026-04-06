<?php

namespace Models;

use PDO;

class Budget extends BaseModel
{
    /**
     * All budgets applicable to the given month/year, with actual spending joined in.
     * Includes month-specific budgets AND recurring budgets (month/year both NULL).
     */
    public function getForMonth(int $month, int $year): array
    {
        $sql = <<<SQL
SELECT
    b.*,
    c.name AS category_name,
    COALESCE(SUM(t.amount), 0) AS spent
FROM budgets b
LEFT JOIN categories c ON c.id = b.category_id
LEFT JOIN transactions t
    ON  t.transaction_type = 'expense'
    AND MONTH(t.transaction_date) = :month
    AND YEAR(t.transaction_date)  = :year
    AND (b.category_id IS NULL OR t.category_id = b.category_id)
WHERE
    (b.month = :month2 AND b.year = :year2)
    OR (b.month IS NULL AND b.year IS NULL)
GROUP BY b.id
ORDER BY (b.category_id IS NULL) ASC, c.name ASC, b.name ASC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':month'  => $month,
            ':year'   => $year,
            ':month2' => $month,
            ':year2'  => $year,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Aggregated totals for the current month — used by dashboard widget.
     */
    public function getSummaryForMonth(int $month, int $year): array
    {
        $rows = $this->getForMonth($month, $year);
        $totalBudgeted = array_sum(array_column($rows, 'amount'));
        $totalSpent    = array_sum(array_column($rows, 'spent'));
        $overCount     = 0;
        foreach ($rows as $row) {
            if ((float) $row['spent'] >= (float) $row['amount']) {
                $overCount++;
            }
        }
        return [
            'count'           => count($rows),
            'total_budgeted'  => (float) $totalBudgeted,
            'total_spent'     => (float) $totalSpent,
            'over_count'      => $overCount,
            'rows'            => $rows,
        ];
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM budgets WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $input): int
    {
        $name       = trim((string) ($input['name'] ?? ''));
        $categoryId = !empty($input['category_id']) ? (int) $input['category_id'] : null;
        $amount     = (float) ($input['amount'] ?? 0);
        $recurring  = !empty($input['recurring']);
        $month      = $recurring ? null : ((int) ($input['month'] ?? date('n')) ?: null);
        $year       = $recurring ? null : ((int) ($input['year']  ?? date('Y')) ?: null);

        if ($name === '' || $amount <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO budgets (name, category_id, amount, month, year)
             VALUES (:name, :category_id, :amount, :month, :year)'
        );
        $ok = $stmt->execute([
            ':name'        => $name,
            ':category_id' => $categoryId,
            ':amount'      => $amount,
            ':month'       => $month,
            ':year'        => $year,
        ]);
        return $ok ? (int) $this->db->lastInsertId() : 0;
    }

    public function update(array $input): bool
    {
        $id         = (int) ($input['id'] ?? 0);
        $name       = trim((string) ($input['name'] ?? ''));
        $categoryId = !empty($input['category_id']) ? (int) $input['category_id'] : null;
        $amount     = (float) ($input['amount'] ?? 0);
        $recurring  = !empty($input['recurring']);
        $month      = $recurring ? null : ((int) ($input['month'] ?? date('n')) ?: null);
        $year       = $recurring ? null : ((int) ($input['year']  ?? date('Y')) ?: null);

        if ($id <= 0 || $name === '' || $amount <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE budgets SET name=:name, category_id=:category_id, amount=:amount,
             month=:month, year=:year WHERE id=:id'
        );
        return $stmt->execute([
            ':name'        => $name,
            ':category_id' => $categoryId,
            ':amount'      => $amount,
            ':month'       => $month,
            ':year'        => $year,
            ':id'          => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        if ($id <= 0) return false;
        $stmt = $this->db->prepare('DELETE FROM budgets WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
