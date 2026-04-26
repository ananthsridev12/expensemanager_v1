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
    c.id AS category_id,
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
    c.id AS category_id,
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
}
