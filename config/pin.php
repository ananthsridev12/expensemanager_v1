<?php

$config = [
    'pin_hash' => '',
    'session_ttl' => 0,
];

$override = __DIR__ . '/pin.override.php';
if (file_exists($override)) {
    $data = require $override;
    if (is_array($data)) {
        $config = array_merge($config, $data);
    }
}

return $config;
