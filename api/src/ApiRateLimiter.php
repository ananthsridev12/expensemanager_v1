<?php

declare(strict_types=1);

class ApiRateLimiter
{
    private string $dir;

    public function __construct(string $dir)
    {
        $this->dir = $dir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    public function allow(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) ?: 'default';
        $file = $this->dir . DIRECTORY_SEPARATOR . $safeKey . '.json';
        $now = time();
        $bucket = ['start' => $now, 'count' => 0];

        if (is_file($file)) {
            $raw = file_get_contents($file);
            $decoded = json_decode($raw ?: '', true);
            if (is_array($decoded)) {
                $bucket = array_merge($bucket, $decoded);
            }
        }

        if (($now - (int) $bucket['start']) >= $windowSeconds) {
            $bucket = ['start' => $now, 'count' => 0];
        }

        $bucket['count'] = (int) $bucket['count'] + 1;
        file_put_contents($file, json_encode($bucket));

        return (int) $bucket['count'] <= $maxAttempts;
    }
}
