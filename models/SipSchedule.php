<?php

namespace Models;

use PDO;

class SipSchedule extends BaseModel
{
    public function create(array $input): bool
    {
        $sql = 'INSERT INTO sip_schedules (investment_id, account_id, sip_amount, sip_day, frequency, start_date, end_date, next_run_date, status) VALUES (:investment_id, :account_id, :sip_amount, :sip_day, :frequency, :start_date, :end_date, :next_run_date, :status)';
        $stmt = $this->db->prepare($sql);

        $startDate = $input['start_date'] ?? date('Y-m-d');

        return $stmt->execute([
            ':investment_id' => (int) ($input['investment_id'] ?? 0),
            ':account_id' => (int) ($input['account_id'] ?? 0),
            ':sip_amount' => (float) ($input['sip_amount'] ?? 0),
            ':sip_day' => (int) ($input['sip_day'] ?? 1),
            ':frequency' => $input['frequency'] ?? 'monthly',
            ':start_date' => $startDate,
            ':end_date' => $input['end_date'] ?? null,
            ':next_run_date' => $input['next_run_date'] ?? $startDate,
            ':status' => $input['status'] ?? 'active',
        ]);
    }

    public function getAll(): array
    {
        $sql = <<<SQL
SELECT
    s.*, i.name AS investment_name, a.account_name
FROM sip_schedules s
LEFT JOIN investments i ON i.id = s.investment_id
LEFT JOIN accounts a ON a.id = s.account_id
ORDER BY s.created_at DESC
SQL;
        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUpcoming(int $limit = 5): array
    {
        $sql = <<<SQL
SELECT
    s.*, i.name AS investment_name
FROM sip_schedules s
LEFT JOIN investments i ON i.id = s.investment_id
WHERE s.status = 'active' AND s.next_run_date >= CURDATE()
ORDER BY s.next_run_date ASC
LIMIT :limit
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sip_schedules WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function update(array $input): bool
    {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) return false;
        $stmt = $this->db->prepare(
            'UPDATE sip_schedules SET sip_amount=:sip_amount, sip_day=:sip_day, frequency=:frequency, next_run_date=:next_run_date, end_date=:end_date, status=:status WHERE id=:id'
        );
        return $stmt->execute([
            ':sip_amount' => (float) ($input['sip_amount'] ?? 0),
            ':sip_day' => (int) ($input['sip_day'] ?? 1),
            ':frequency' => $input['frequency'] ?? 'monthly',
            ':next_run_date' => $input['next_run_date'] ?? date('Y-m-d'),
            ':end_date' => !empty($input['end_date']) ? $input['end_date'] : null,
            ':status' => $input['status'] ?? 'active',
            ':id' => $id,
        ]);
    }
}
