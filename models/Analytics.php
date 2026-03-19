<?php

namespace Models;

use PDO;

class Analytics extends BaseModel
{
    private const EARNINGS_CATEGORY_ID = 1;

    public function getSummary(string $startDate, string $endDate): array
    {
        $sql = <<<SQL
SELECT
    COALESCE(SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE 0 END), 0) AS total_income,
    COALESCE(SUM(CASE WHEN t.transaction_type = 'expense' THEN t.amount ELSE 0 END), 0) AS total_expense,
    COALESCE(SUM(CASE WHEN t.transaction_type = 'transfer' THEN t.amount ELSE 0 END), 0) AS total_transfer
FROM transactions t
WHERE t.transaction_date BETWEEN :start_date AND :end_date
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_income' => (float) ($row['total_income'] ?? 0),
            'total_expense' => (float) ($row['total_expense'] ?? 0),
            'total_transfer' => (float) ($row['total_transfer'] ?? 0),
            'net_cashflow' => (float) ($row['total_income'] ?? 0) - (float) ($row['total_expense'] ?? 0),
        ];
    }

    public function getEarningsSummary(string $startDate, string $endDate): array
    {
        $sql = <<<SQL
SELECT
    COALESCE(SUM(t.amount), 0) AS total_earnings,
    COUNT(*) AS entries
FROM transactions t
JOIN categories c ON c.id = t.category_id
WHERE t.transaction_date BETWEEN :start_date AND :end_date
  AND t.transaction_type = 'income'
  AND (c.id = :category_id OR c.name = :category_name)
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':category_id' => self::EARNINGS_CATEGORY_ID,
            ':category_name' => 'Earnings',
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_earnings' => (float) ($row['total_earnings'] ?? 0),
            'entries' => (int) ($row['entries'] ?? 0),
        ];
    }

    public function getEarningsBySubcategory(string $startDate, string $endDate): array
    {
        $sql = <<<SQL
SELECT
    COALESCE(sc.name, 'Unspecified') AS subcategory_name,
    COALESCE(SUM(t.amount), 0) AS total_amount
FROM transactions t
JOIN categories c ON c.id = t.category_id
LEFT JOIN subcategories sc ON sc.id = t.subcategory_id
WHERE t.transaction_date BETWEEN :start_date AND :end_date
  AND t.transaction_type = 'income'
  AND (c.id = :category_id OR c.name = :category_name)
GROUP BY sc.id, sc.name
ORDER BY total_amount DESC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':category_id' => self::EARNINGS_CATEGORY_ID,
            ':category_name' => 'Earnings',
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMonthlyEarningsTrend(int $months = 12): array
    {
        $months = max(1, min(24, $months));
        $start = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));
        $end = date('Y-m-t');

        $sql = <<<SQL
SELECT
    DATE_FORMAT(t.transaction_date, '%Y-%m') AS period,
    COALESCE(SUM(t.amount), 0) AS total_amount
FROM transactions t
JOIN categories c ON c.id = t.category_id
WHERE t.transaction_date BETWEEN :start_date AND :end_date
  AND t.transaction_type = 'income'
  AND (c.id = :category_id OR c.name = :category_name)
GROUP BY DATE_FORMAT(t.transaction_date, '%Y-%m')
ORDER BY period ASC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start_date' => $start,
            ':end_date' => $end,
            ':category_id' => self::EARNINGS_CATEGORY_ID,
            ':category_name' => 'Earnings',
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExpensesByCategory(string $startDate, string $endDate): array
    {
        $sql = <<<SQL
SELECT
    COALESCE(c.name, 'Uncategorized') AS category_name,
    COALESCE(SUM(t.amount), 0) AS total_amount
FROM transactions t
LEFT JOIN categories c ON c.id = t.category_id
WHERE t.transaction_date BETWEEN :start_date AND :end_date
  AND t.transaction_type = 'expense'
GROUP BY c.id, c.name
ORDER BY total_amount DESC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getIncomeByCategory(string $startDate, string $endDate): array
    {
        $sql = <<<SQL
SELECT
    COALESCE(c.name, 'Uncategorized') AS category_name,
    COALESCE(SUM(t.amount), 0) AS total_amount
FROM transactions t
LEFT JOIN categories c ON c.id = t.category_id
WHERE t.transaction_date BETWEEN :start_date AND :end_date
  AND t.transaction_type = 'income'
GROUP BY c.id, c.name
ORDER BY total_amount DESC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMonthlyIncomeVsExpense(int $months = 12): array
    {
        $months = max(1, min(24, $months));
        $start = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));
        $end = date('Y-m-t');

        $sql = <<<SQL
SELECT
    DATE_FORMAT(t.transaction_date, '%Y-%m') AS period,
    COALESCE(SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE 0 END), 0) AS income,
    COALESCE(SUM(CASE WHEN t.transaction_type = 'expense' THEN t.amount ELSE 0 END), 0) AS expense
FROM transactions t
WHERE t.transaction_date BETWEEN :start_date AND :end_date
GROUP BY DATE_FORMAT(t.transaction_date, '%Y-%m')
ORDER BY period ASC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':start_date' => $start, ':end_date' => $end]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getThisMonthVsLastMonth(): array
    {
        $thisStart  = date('Y-m-01');
        $thisEnd    = date('Y-m-d');
        $lastStart  = date('Y-m-01', strtotime('first day of last month'));
        $lastEnd    = date('Y-m-t',  strtotime('last day of last month'));

        $sql = <<<SQL
SELECT
    COALESCE(SUM(CASE WHEN t.transaction_type = 'income'  THEN t.amount ELSE 0 END), 0) AS income,
    COALESCE(SUM(CASE WHEN t.transaction_type = 'expense' THEN t.amount ELSE 0 END), 0) AS expense
FROM transactions t
WHERE t.transaction_date BETWEEN :start AND :end
SQL;
        $fetch = function (string $start, string $end) use ($sql): array {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':start' => $start, ':end' => $end]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['income' => 0, 'expense' => 0];
        };

        $curr  = $fetch($thisStart, $thisEnd);
        $last  = $fetch($lastStart, $lastEnd);

        $pctChange = function (float $current, float $previous): ?float {
            if ($previous == 0) return null;
            return round((($current - $previous) / $previous) * 100, 1);
        };

        return [
            'this_income'   => (float) $curr['income'],
            'this_expense'  => (float) $curr['expense'],
            'this_net'      => (float) $curr['income'] - (float) $curr['expense'],
            'last_income'   => (float) $last['income'],
            'last_expense'  => (float) $last['expense'],
            'income_pct'    => $pctChange((float) $curr['income'],  (float) $last['income']),
            'expense_pct'   => $pctChange((float) $curr['expense'], (float) $last['expense']),
        ];
    }

    public function getMiniSparkline(int $months = 6): array
    {
        $months = max(2, min(12, $months));
        $start  = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));
        $end    = date('Y-m-t');

        $sql = <<<SQL
SELECT
    DATE_FORMAT(t.transaction_date, '%Y-%m') AS period,
    COALESCE(SUM(CASE WHEN t.transaction_type = 'income'  THEN t.amount ELSE 0 END), 0) AS income,
    COALESCE(SUM(CASE WHEN t.transaction_type = 'expense' THEN t.amount ELSE 0 END), 0) AS expense
FROM transactions t
WHERE t.transaction_date BETWEEN :start AND :end
GROUP BY DATE_FORMAT(t.transaction_date, '%Y-%m')
ORDER BY period ASC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAccountWiseExpense(string $startDate, string $endDate): array
    {
        $sql = <<<SQL
SELECT
    CASE
        WHEN t.account_type = 'savings'     THEN CONCAT(a.account_name, ' (Savings)')
        WHEN t.account_type = 'current'     THEN CONCAT(a.account_name, ' (Current)')
        WHEN t.account_type = 'cash'        THEN CONCAT(a.account_name, ' (Cash)')
        WHEN t.account_type = 'wallet'      THEN CONCAT(a.account_name, ' (Wallet)')
        WHEN t.account_type = 'other'       THEN CONCAT(a.account_name, ' (Other)')
        WHEN t.account_type = 'credit_card' THEN CONCAT(cc.card_name, ' (CC)')
        ELSE t.account_type
    END AS account_label,
    COALESCE(SUM(t.amount), 0) AS total_amount
FROM transactions t
LEFT JOIN accounts a ON a.id = t.account_id AND t.account_type IN ('savings','current','cash','wallet','other')
LEFT JOIN credit_cards cc ON cc.id = t.account_id AND t.account_type = 'credit_card'
WHERE t.transaction_date BETWEEN :start_date AND :end_date
  AND t.transaction_type = 'expense'
  AND t.account_type NOT IN ('lending','investment','rental','loan')
GROUP BY t.account_type, t.account_id
ORDER BY total_amount DESC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDayOfWeekSpend(string $startDate, string $endDate): array
    {
        $sql = <<<SQL
SELECT
    DAYOFWEEK(t.transaction_date) AS dow_num,
    DAYNAME(t.transaction_date)   AS dow_name,
    COALESCE(SUM(t.amount), 0)    AS total_amount,
    COUNT(*)                      AS tx_count
FROM transactions t
WHERE t.transaction_date BETWEEN :start_date AND :end_date
  AND t.transaction_type = 'expense'
GROUP BY DAYOFWEEK(t.transaction_date), DAYNAME(t.transaction_date)
ORDER BY dow_num ASC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);

        // Fill all 7 days so chart has no gaps
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['dow_num']] = $row;
        }
        $days = [1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday'];
        $result = [];
        foreach ($days as $num => $name) {
            $result[] = [
                'dow_name'     => $name,
                'total_amount' => (float) ($map[$num]['total_amount'] ?? 0),
                'tx_count'     => (int) ($map[$num]['tx_count'] ?? 0),
            ];
        }

        return $result;
    }
}
