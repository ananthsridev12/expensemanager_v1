<?php

namespace Models;

use Config\Database;
use PDO;

class OAuthToken extends BaseModel
{
    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    public function issue(string $clientId, string $label = ''): string
    {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $this->db->prepare(
            "INSERT INTO api_tokens (token_hash, client_id, label) VALUES (:hash, :cid, :label)"
        )->execute([':hash' => $hash, ':cid' => $clientId, ':label' => $label]);
        return $token;
    }

    public function verify(string $token): bool
    {
        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare("SELECT id FROM api_tokens WHERE token_hash = :hash");
        $stmt->execute([':hash' => $hash]);
        if (!$stmt->fetchColumn()) return false;
        $this->db->prepare(
            "UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = :hash"
        )->execute([':hash' => $hash]);
        return true;
    }

    public function revoke(string $token): void
    {
        $hash = hash('sha256', $token);
        $this->db->prepare("DELETE FROM api_tokens WHERE token_hash = :hash")
                  ->execute([':hash' => $hash]);
    }
}
