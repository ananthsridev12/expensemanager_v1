<?php

declare(strict_types=1);

class ApiJwt
{
    private string $secret;

    public function __construct(string $secret)
    {
        if ($secret === '') {
            throw new RuntimeException('API_JWT_SECRET is missing.');
        }
        $this->secret = $secret;
    }

    public function issue(array $claims, int $ttlSeconds = 3600): string
    {
        $now = time();
        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
            'jti' => bin2hex(random_bytes(12)),
        ]);

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->secret, true);

        return $encodedHeader . '.' . $encodedPayload . '.' . $this->base64UrlEncode($signature);
    }

    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, $this->secret, true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $decodedPayload = json_decode($this->base64UrlDecode($payload), true);
        if (!is_array($decodedPayload)) {
            return null;
        }

        $exp = (int) ($decodedPayload['exp'] ?? 0);
        if ($exp <= time()) {
            return null;
        }

        return $decodedPayload;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $pad = 4 - (strlen($value) % 4);
        if ($pad < 4) {
            $value .= str_repeat('=', $pad);
        }
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}
