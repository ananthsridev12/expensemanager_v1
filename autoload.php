<?php

require_once __DIR__ . '/helpers.php';

spl_autoload_register(function (string $class): void {
    $prefixes = [
        'Config\\' => 'config/',
        'Models\\' => 'models/',
        'Controllers\\' => 'controllers/',
    ];

    foreach ($prefixes as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $relativeClass = substr($class, strlen($prefix));
            $relativePath = str_replace('\\', '/', $relativeClass) . '.php';
            $path = __DIR__ . '/' . $dir . $relativePath;
            if (file_exists($path)) {
                require_once $path;
                return;
            }

            // Linux shared hosting is case-sensitive; support lowercased file names too.
            $fallbackPath = __DIR__ . '/' . $dir . strtolower($relativePath);
            if (file_exists($fallbackPath)) {
                require_once $fallbackPath;
            }
            return;
        }
    }
});
