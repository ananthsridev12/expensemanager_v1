<?php

declare(strict_types=1);

class ApiUnauthorizedException extends RuntimeException
{
}

class ApiAuth
{
    private PDO $pdo;
    private ApiJwt $jwt;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $secret = getenv('API_JWT_SECRET') ?: hash('sha256', __DIR__ . '|' . php_uname() . '|' . PHP_VERSION);
        $this->jwt = new ApiJwt($secret);
    }

    public function loginWithPin(string $pin, string $deviceName = ''): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, pin_hash FROM api_users WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return ['success' => false];
        }

        if (!password_verify($pin, (string) ($user['pin_hash'] ?? ''))) {
            return ['success' => false];
        }

        $token = $this->jwt->issue([
            'sub' => (int) $user['id'],
            'name' => (string) $user['name'],
            'device' => $deviceName,
        ], 3600);

        $this->storeSession((int) $user['id'], $token, $deviceName, 3600);

        return [
            'success' => true,
            'data' => [
                'access_token' => $token,
                'expires_in' => 3600,
                'user' => [
                    'id' => (int) $user['id'],
                    'name' => (string) $user['name'],
                ],
            ],
        ];
    }

    public function requireAuth(): array
    {
        $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (stripos($authorization, 'Bearer ') !== 0) {
            throw new ApiUnauthorizedException('Missing bearer token.');
        }

        $token = trim(substr($authorization, 7));
        if ($token === '') {
            throw new ApiUnauthorizedException('Empty bearer token.');
        }

        $claims = $this->jwt->verify($token);
        if (!$claims) {
            throw new ApiUnauthorizedException('Invalid or expired token.');
        }

        return [
            'id' => (int) ($claims['sub'] ?? 0),
            'name' => (string) ($claims['name'] ?? ''),
        ];
    }

    private function storeSession(int $userId, string $token, string $deviceName, int $ttlSeconds): void
    {
        $tableExists = $this->pdo->query("SHOW TABLES LIKE 'api_sessions'")->fetchColumn();
        if (!$tableExists) {
            return;
        }

        $tokenHash = hash('sha256', $token);
        $expiry = date('Y-m-d H:i:s', time() + $ttlSeconds);
        $stmt = $this->pdo->prepare(
            'INSERT INTO api_sessions (api_user_id, token_hash, device_name, expires_at)
             VALUES (:api_user_id, :token_hash, :device_name, :expires_at)'
        );
        $stmt->execute([
            ':api_user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':device_name' => $deviceName !== '' ? $deviceName : null,
            ':expires_at' => $expiry,
        ]);
    }
}
