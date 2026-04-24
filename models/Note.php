<?php

namespace Models;

class Note extends BaseModel
{
    public function getAll(): array
    {
        return $this->db
            ->query('SELECT * FROM notes ORDER BY updated_at DESC')
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM notes WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function save(string $title, string $content): void
    {
        $stmt = $this->db->prepare('INSERT INTO notes (title, content) VALUES (?, ?)');
        $stmt->execute([$title, $content]);
    }

    public function update(int $id, string $title, string $content): void
    {
        $stmt = $this->db->prepare('UPDATE notes SET title = ?, content = ? WHERE id = ?');
        $stmt->execute([$title, $content, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM notes WHERE id = ?')->execute([$id]);
    }
}
