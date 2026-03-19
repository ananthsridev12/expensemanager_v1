<?php

namespace Models;

use PDO;

class PaymentMethod extends BaseModel
{
    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT id, name FROM payment_methods ORDER BY name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findOrCreate(string $name): ?int
    {
        $normalized = trim($name);
        if ($normalized === '') {
            return null;
        }

        $select = $this->db->prepare('SELECT id FROM payment_methods WHERE LOWER(name) = LOWER(:name) LIMIT 1');
        $select->execute([':name' => $normalized]);
        $existing = $select->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return (int) $existing['id'];
        }

        $insert = $this->db->prepare('INSERT INTO payment_methods (name, is_system) VALUES (:name, 0)');
        $ok = $insert->execute([':name' => $normalized]);
        if (!$ok) {
            return null;
        }

        return (int) $this->db->lastInsertId();
    }
}
