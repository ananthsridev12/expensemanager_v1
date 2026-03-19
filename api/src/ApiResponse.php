<?php

declare(strict_types=1);

class ApiResponse
{
    public function success($data, array $meta = []): void
    {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'error' => null,
            'meta' => $meta,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function error(int $status, string $code, string $message): void
    {
        http_response_code($status);
        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'meta' => [
                'timestamp' => date('c'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
