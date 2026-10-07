<?php

namespace Models;

use Config\Database;
use PDO;

class OAuthCode extends BaseModel
{
    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    public function create(
        string  $clientId,
        string  $redirectUri,
        ?string $codeChallenge,
        string  $method = 'S256'
    ): string {
        $code      = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 600);
        $this->db->prepare(
            "INSERT INTO oauth_codes
               (code, client_id, redirect_uri, code_challenge, code_challenge_method, expires_at)
             VALUES (:code, :cid, :ruri, :cc, :ccm, :exp)"
        )->execute([
            ':code' => $code, ':cid' => $clientId, ':ruri' => $redirectUri,
            ':cc'   => $codeChallenge, ':ccm' => $method, ':exp' => $expiresAt,
        ]);
        return $code;
    }

    public function consume(
        string  $code,
        string  $clientId,
        string  $redirectUri,
        ?string $verifier
    ): bool {
        $stmt = $this->db->prepare(
            "SELECT * FROM oauth_codes
              WHERE code = :code AND client_id = :cid AND used = 0
              LIMIT 1"
        );
        $stmt->execute([':code' => $code, ':cid' => $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // Check expiry in PHP to avoid MySQL timezone mismatch (Problem #7)
        if (!$row || $row['redirect_uri'] !== $redirectUri) return false;
        if (strtotime((string) $row['expires_at']) < time()) return false;

        if ($row['code_challenge'] !== null) {
            if ($verifier === null) return false;
            $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            if (!hash_equals((string) $row['code_challenge'], $computed)) return false;
        }

        $this->db->prepare("UPDATE oauth_codes SET used = 1 WHERE code = :code")
                  ->execute([':code' => $code]);
        return true;
    }
}
