<?php

namespace Models;

use PDO;

class Category extends BaseModel
{
    public function getAllWithSubcategories(): array
    {
        $sql = <<<SQL
SELECT
    c.id AS category_id,
    c.name AS category_name,
    c.type AS category_type,
    c.is_fuel AS category_is_fuel,
    c.created_at AS category_created_at,
    sc.id AS sub_id,
    sc.name AS sub_name,
    sc.created_at AS sub_created_at
FROM categories c
LEFT JOIN subcategories sc ON sc.category_id = c.id
ORDER BY c.name ASC, sc.name ASC
SQL;
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            if (!isset($result[$row['category_id']])) {
                $result[$row['category_id']] = [
                    'id' => $row['category_id'],
                    'name' => $row['category_name'],
                    'type' => $row['category_type'],
                    'is_fuel' => (bool) $row['category_is_fuel'],
                    'created_at' => $row['category_created_at'],
                    'subcategories' => [],
                ];
            }

            if ($row['sub_id']) {
                $result[$row['category_id']]['subcategories'][] = [
                    'id' => $row['sub_id'],
                    'name' => $row['sub_name'],
                    'created_at' => $row['sub_created_at'],
                ];
            }
        }

        return array_values($result);
    }

    public function getCategoryList(): array
    {
        $stmt = $this->db->query('SELECT id, name FROM categories ORDER BY name ASC');

        return $stmt->fetchAll();
    }

    public function createCategory(string $name, string $type, bool $isFuel = false): int
    {
        $sql = 'INSERT INTO categories (name, type, is_fuel) VALUES (:name, :type, :is_fuel)';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            ':name' => trim($name),
            ':type' => $type,
            ':is_fuel' => $isFuel ? 1 : 0,
        ]);
        return $ok ? (int) $this->db->lastInsertId() : 0;
    }

    public function createSubcategory(int $categoryId, string $name): int
    {
        $sql = 'INSERT INTO subcategories (category_id, name) VALUES (:category_id, :name)';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            ':category_id' => $categoryId,
            ':name' => trim($name),
        ]);
        return $ok ? (int) $this->db->lastInsertId() : 0;
    }

    public function count(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM categories');

        return (int) $stmt->fetchColumn();
    }

    public function getCategoryById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getSubcategoryById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM subcategories WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateCategory(array $input): bool
    {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) return false;
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') return false;
        $type = (string) ($input['type'] ?? 'expense');
        $allowed = ['income', 'expense', 'transfer'];
        if (!in_array($type, $allowed, true)) $type = 'expense';
        $isFuel = isset($input['is_fuel']) && $input['is_fuel'] ? 1 : 0;
        $stmt = $this->db->prepare('UPDATE categories SET name=:name, type=:type, is_fuel=:is_fuel WHERE id=:id');
        return $stmt->execute([':name' => $name, ':type' => $type, ':is_fuel' => $isFuel, ':id' => $id]);
    }

    public function updateSubcategory(array $input): bool
    {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) return false;
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') return false;
        $stmt = $this->db->prepare('UPDATE subcategories SET name=:name WHERE id=:id');
        return $stmt->execute([':name' => $name, ':id' => $id]);
    }
}
