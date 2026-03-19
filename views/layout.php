<?php
if (!function_exists('formatCurrency')) {
    function formatCurrency($value): string
    {
        return '&#8377; ' . number_format((float) $value, 2);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personal Finance Manager</title>
    <link rel="stylesheet" href="public/css/style.css?v=<?= filemtime(__DIR__ . '/../public/css/style.css') ?>">
</head>
<body>
    <?= $content ?? '' ?>
    <script src="public/js/main.js?v=<?= filemtime(__DIR__ . '/../public/js/main.js') ?>"></script>
</body>
</html>
