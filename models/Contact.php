<?php

namespace Models;

use PDO;

class Contact extends BaseModel
{
    public function create(array $input): bool
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return false;
        }

        $contactType = (string) ($input['contact_type'] ?? 'other');
        $allowedTypes = ['tenant', 'lending', 'both', 'other'];
        if (!in_array($contactType, $allowedTypes, true)) {
            $contactType = 'other';
        }

        $sql = 'INSERT INTO contacts (name, mobile, email, address, city, state, contact_type, notes) VALUES (:name, :mobile, :email, :address, :city, :state, :contact_type, :notes)';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':name' => $name,
            ':mobile' => $input['mobile'] ?? null,
            ':email' => $input['email'] ?? null,
            ':address' => $input['address'] ?? null,
            ':city' => $input['city'] ?? null,
            ':state' => $input['state'] ?? null,
            ':contact_type' => $contactType,
            ':notes' => $input['notes'] ?? null,
        ]);
    }

    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM contacts ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->getAll();
        }

        $sql = <<<SQL
SELECT id, name, mobile, email
FROM contacts
WHERE name LIKE :q
   OR mobile LIKE :q
   OR email LIKE :q
ORDER BY name ASC
LIMIT :limit
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':q', '%' . $query . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM contacts WHERE id = :id LIMIT 1');
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
        $contactType = (string) ($input['contact_type'] ?? 'other');
        $allowedTypes = ['tenant', 'lending', 'both', 'other'];
        if (!in_array($contactType, $allowedTypes, true)) $contactType = 'other';
        $stmt = $this->db->prepare('UPDATE contacts SET name=:name, mobile=:mobile, email=:email, address=:address, city=:city, state=:state, contact_type=:contact_type, notes=:notes WHERE id=:id');
        return $stmt->execute([':name' => $name, ':mobile' => $input['mobile'] ?? null, ':email' => $input['email'] ?? null, ':address' => $input['address'] ?? null, ':city' => $input['city'] ?? null, ':state' => $input['state'] ?? null, ':contact_type' => $contactType, ':notes' => $input['notes'] ?? null, ':id' => $id]);
    }
}
