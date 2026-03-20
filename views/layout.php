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

    <!-- Favicon -->
    <link rel="icon" href="public/icons/icon.svg" type="image/svg+xml">

    <!-- PWA -->
    <link rel="manifest" href="public/manifest.json">
    <meta name="theme-color" content="#0f1a2e">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PersonFin">
    <link rel="apple-touch-icon" href="public/icons/icon.svg">

    <link rel="stylesheet" href="public/css/style.css?v=<?= filemtime(__DIR__ . '/../public/css/style.css') ?>">
    <script>(function(){var f=localStorage.getItem('em-font');if(f&&f!=='normal')document.documentElement.setAttribute('data-font',f);})();</script>
</head>
<body>
    <?= $content ?? '' ?>
    <script src="public/js/main.js?v=<?= filemtime(__DIR__ . '/../public/js/main.js') ?>"></script>
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/public/sw.js').catch(() => {});
    }
    </script>
</body>
</html>
