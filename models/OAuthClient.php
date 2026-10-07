<?php

namespace Models;

use Config\Database;
use PDO;

class OAuthClient extends BaseModel
{
    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    public function register(string $name, array $redirectUris): string
    {
        $id = bin2hex(random_bytes(16));
        $this->db->prepare(
            "INSERT INTO oauth_clients (id, name, redirect_uris) VALUES (:id, :name, :uris)"
        )->execute([':id' => $id, ':name' => $name, ':uris' => json_encode($redirectUris)]);
        return $id;
    }

    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM oauth_clients WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['redirect_uris'] = json_decode((string) $row['redirect_uris'], true) ?? [];
        return $row;
    }
}
