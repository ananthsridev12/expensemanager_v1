<?php

namespace Models;

use PDO;

class Reminder extends BaseModel
{
    public function create(array $input): bool
    {
        $sql = 'INSERT INTO reminders (name, amount, frequency, next_due_date, status, notes) VALUES (:name, :amount, :frequency, :next_due_date, :status, :notes)';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':name' => trim($input['name'] ?? ''),
            ':amount' => is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : null,
            ':frequency' => $input['frequency'] ?? 'monthly',
            ':next_due_date' => $input['next_due_date'] ?? date('Y-m-d'),
            ':status' => $input['status'] ?? 'upcoming',
            ':notes' => $input['notes'] ?? null,
        ]);
    }

    public function getUpcoming(int $limit = 10): array
    {
        $stmt = $this->db->prepare('SELECT * FROM reminders WHERE status != "completed" ORDER BY next_due_date ASC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM reminders ORDER BY next_due_date ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM reminders');

        return (int) $stmt->fetchColumn();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM reminders WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function update(array $input): bool
    {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) return false;
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') return false;
        $stmt = $this->db->prepare(
            'UPDATE reminders SET name=:name, amount=:amount, frequency=:frequency, next_due_date=:next_due_date, status=:status, notes=:notes WHERE id=:id'
        );
        return $stmt->execute([
            ':name' => $name,
            ':amount' => is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : null,
            ':frequency' => $input['frequency'] ?? 'monthly',
            ':next_due_date' => $input['next_due_date'] ?? date('Y-m-d'),
            ':status' => $input['status'] ?? 'upcoming',
            ':notes' => $input['notes'] ?? null,
            ':id' => $id,
        ]);
    }
}
