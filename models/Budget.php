<?php

namespace Models;

use PDO;

class Budget extends BaseModel
{
    /**
     * All expense categories with their budget (if set) and actual spending for the month.
     * Used for the bulk-entry page.
     */
    public function getAllCategoriesWithBudgets(int $month, int $year): array
    {
        $sql = <<<SQL
SELECT
    c.id   AS category_id,
    c.name AS category_name,
    COALESCE(b.amount, 0)       AS budget_amount,
    COALESCE(SUM(t.amount), 0)  AS spent
FROM categories c
LEFT JOIN budgets b
    ON  b.category_id = c.id
    AND b.month = :month
    AND b.year  = :year
LEFT JOIN transactions t
    ON  t.category_id        = c.id
    AND t.transaction_type   = 'expense'
    AND MONTH(t.transaction_date) = :month2
    AND YEAR(t.transaction_date)  = :year2
WHERE c.type = 'expense'
GROUP BY c.id, c.name, b.amount
ORDER BY c.name ASC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':month' => $month, ':year' => $year, ':month2' => $month, ':year2' => $year]);
        return $stmt->fetchAll();
    }

    /**
     * Bulk upsert: amounts > 0 are saved, empty/zero entries are deleted.
     * Requires unique key on (category_id, month, year).
     */
    public function saveAllForMonth(array $budgets, int $month, int $year): void
    {
        $upsert = $this->db->prepare(
            'INSERT INTO budgets (name, category_id, amount, month, year)
             VALUES (:name, :category_id, :amount, :month, :year)
             ON DUPLICATE KEY UPDATE name = VALUES(name), amount = VALUES(amount)'
        );
        $delete = $this->db->prepare(
            'DELETE FROM budgets WHERE category_id = :category_id AND month = :month AND year = :year'
        );

        foreach ($budgets as $categoryId => $data) {
            $categoryId = (int) $categoryId;
            $amount     = (float) ($data['amount'] ?? 0);
            $name       = trim((string) ($data['name'] ?? ''));

            if ($amount > 0) {
                $upsert->execute([
                    ':name'        => $name !== '' ? $name : 'Budget',
                    ':category_id' => $categoryId,
                    ':amount'      => $amount,
                    ':month'       => $month,
                    ':year'        => $year,
                ]);
            } else {
                $delete->execute([':category_id' => $categoryId, ':month' => $month, ':year' => $year]);
            }
        }
    }

    /**
     * Copy all per-category budgets from one month into another (upserts).
     */
    public function copyFromMonth(int $fromMonth, int $fromYear, int $toMonth, int $toYear): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO budgets (name, category_id, amount, month, year)
             SELECT name, category_id, amount, :to_month, :to_year
             FROM budgets
             WHERE month = :from_month AND year = :from_year AND category_id IS NOT NULL
             ON DUPLICATE KEY UPDATE amount = VALUES(amount), name = VALUES(name)'
        );
        $stmt->execute([
            ':to_month'   => $toMonth,
            ':to_year'    => $toYear,
            ':from_month' => $fromMonth,
            ':from_year'  => $fromYear,
        ]);
    }

    /**
     * Average spending per category over the last 3 complete months.
     * Returns [category_id => avg_amount].
     */
    public function getThreeMonthAverage(): array
    {
        // Last 3 complete calendar months
        $endDate   = date('Y-m-01');                          // first of current month
        $startDate = date('Y-m-01', strtotime('-3 months'));  // 3 months ago

        $stmt = $this->db->prepare(<<<SQL
SELECT
    t.category_id,
    ROUND(SUM(t.amount) / 3, 2) AS avg_amount
FROM transactions t
WHERE t.transaction_type = 'expense'
  AND t.transaction_date >= :start_date
  AND t.transaction_date <  :end_date
GROUP BY t.category_id
SQL);
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['category_id']] = (float) $row['avg_amount'];
        }
        return $result;
    }

    /**
     * Trend data: total budgeted vs actual spent per month (last N months with budgets).
     */
    public function getTrendData(int $limit = 6): array
    {
        $stmt = $this->db->prepare(<<<SQL
SELECT
    b.month,
    b.year,
    SUM(b.amount)                AS total_budgeted,
    COALESCE(SUM(ts.spent), 0)   AS total_spent
FROM budgets b
LEFT JOIN (
    SELECT
        category_id,
        MONTH(transaction_date) AS t_month,
        YEAR(transaction_date)  AS t_year,
        SUM(amount)             AS spent
    FROM transactions
    WHERE transaction_type = 'expense'
    GROUP BY category_id, MONTH(transaction_date), YEAR(transaction_date)
) ts ON ts.category_id = b.category_id
     AND ts.t_month    = b.month
     AND ts.t_year     = b.year
WHERE b.month IS NOT NULL AND b.year IS NOT NULL
GROUP BY b.year, b.month
ORDER BY b.year DESC, b.month DESC
LIMIT :lim
SQL);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_reverse($stmt->fetchAll()); // oldest → newest
        return $rows;
    }

    /**
     * Aggregated totals for the current month — used by dashboard widget.
     */
    public function getSummaryForMonth(int $month, int $year): array
    {
        $rows          = $this->getAllCategoriesWithBudgets($month, $year);
        $budgeted      = array_filter($rows, fn($r) => (float)$r['budget_amount'] > 0);
        $totalBudgeted = array_sum(array_column($budgeted, 'budget_amount'));
        $totalSpent    = array_sum(array_column($budgeted, 'spent'));
        $overCount     = count(array_filter($budgeted, fn($r) => (float)$r['spent'] >= (float)$r['budget_amount']));

        return [
            'count'          => count($budgeted),
            'total_budgeted' => (float) $totalBudgeted,
            'total_spent'    => (float) $totalSpent,
            'over_count'     => $overCount,
            'rows'           => array_values($budgeted),
        ];
    }
}
