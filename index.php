<?php

require_once __DIR__ . '/autoload.php';

session_start();

$pinConfig = require __DIR__ . '/config/pin.php';
$pinHash = trim((string) ($pinConfig['pin_hash'] ?? ''));
$pinTtl = (int) ($pinConfig['session_ttl'] ?? 0);

if ($pinHash !== '') {
    $selfPath = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/';
    if (isset($_GET['action']) && $_GET['action'] === 'lock') {
        unset($_SESSION['pin_unlocked_at']);
        header('Location: ' . $selfPath);
        exit;
    }

    $unlockedAt = isset($_SESSION['pin_unlocked_at']) ? (int) $_SESSION['pin_unlocked_at'] : 0;
    $isExpired = $pinTtl > 0 && $unlockedAt > 0 && (time() - $unlockedAt) > $pinTtl;
    if ($isExpired) {
        unset($_SESSION['pin_unlocked_at']);
    }

    $hasAccess = isset($_SESSION['pin_unlocked_at']) && $_SESSION['pin_unlocked_at'] > 0;
    $pinError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'pin_unlock') {
        $pinInput = trim((string) ($_POST['pin'] ?? ''));
        if ($pinInput === '' || !password_verify($pinInput, $pinHash)) {
            $pinError = 'Invalid PIN.';
        } else {
            $_SESSION['pin_unlocked_at'] = time();
            header('Location: ' . $selfPath);
            exit;
        }
    }

    if (!$hasAccess) {
        $pinErrorHtml = $pinError !== '' ? '<p class="pin-error">' . htmlspecialchars($pinError) . '</p>' : '';
        echo <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0f1a2e">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="manifest" href="public/manifest.json">
<title>PersonFin — Unlock</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Inter', system-ui, sans-serif;
    background: #060b16;
    color: #e5ecff;
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
  }
  .pin-wrap {
    width: 100%;
    max-width: 360px;
    background: #0f1a2e;
    border-radius: 18px;
    padding: 2.2rem 2rem;
    box-shadow: 0 16px 48px rgba(0,0,0,0.5);
    border: 1px solid rgba(120,150,210,0.12);
  }
  .pin-logo {
    font-size: 2.4rem;
    text-align: center;
    margin-bottom: 0.4rem;
  }
  .pin-title {
    font-size: 1.1rem;
    font-weight: 600;
    text-align: center;
    color: #e5ecff;
    margin-bottom: 0.25rem;
  }
  .pin-sub {
    font-size: 0.8rem;
    color: #7a94c4;
    text-align: center;
    margin-bottom: 1.8rem;
  }
  label {
    display: block;
    font-size: 0.78rem;
    color: #7a94c4;
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-bottom: 0.5rem;
  }
  input[type=password] {
    width: 100%;
    background: #060b16;
    border: 1px solid rgba(120,150,210,0.2);
    border-radius: 10px;
    padding: 0.85rem 1rem;
    font-size: 1.5rem;
    letter-spacing: 0.4em;
    color: #e5ecff;
    text-align: center;
    outline: none;
    transition: border-color .2s;
    -webkit-text-security: disc;
  }
  input[type=password]:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
  }
  .pin-error {
    color: #f43f5e;
    font-size: 0.82rem;
    text-align: center;
    margin-top: 0.75rem;
  }
  button {
    margin-top: 1.2rem;
    width: 100%;
    padding: 0.85rem;
    background: #3b82f6;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .18s;
  }
  button:hover { background: #2563eb; }
  button:active { background: #1d4ed8; }
</style>
</head>
<body>
<div class="pin-wrap">
  <div class="pin-logo">₹</div>
  <p class="pin-title">PersonFin</p>
  <p class="pin-sub">Enter your PIN to continue</p>
  <form method="post" autocomplete="off">
    <input type="hidden" name="form" value="pin_unlock">
    <label for="pin">PIN</label>
    <input type="password" id="pin" name="pin" inputmode="numeric" autofocus required>
    {$pinErrorHtml}
    <button type="submit">Unlock</button>
  </form>
</div>
<script>if('serviceWorker' in navigator) navigator.serviceWorker.register('/public/sw.js').catch(()=>{});</script>
</body>
</html>
HTML;
        exit;
    }
}

use Controllers\AccountController;
use Controllers\AnalyticsController;
use Controllers\CategoryController;
use Controllers\ContactController;
use Controllers\CreditCardController;
use Controllers\DashboardController;
use Controllers\InvestmentController;
use Controllers\LoanController;
use Controllers\LendingController;
use Controllers\ReminderController;
use Controllers\RentalController;
use Controllers\SipController;
use Controllers\TransactionController;

$moduleInput = filter_input(INPUT_GET, 'module', FILTER_DEFAULT);
$module = is_string($moduleInput) ? preg_replace('/[^a-z_]/i', '', $moduleInput) : 'dashboard';
$module = $module !== '' ? $module : 'dashboard';

switch ($module) {
    case 'accounts':
        $controller = new AccountController();
        echo $controller->index();
        break;
    case 'analytics':
        $controller = new AnalyticsController();
        echo $controller->index();
        break;
    case 'categories':
        $controller = new CategoryController();
        echo $controller->index();
        break;
    case 'contacts':
        $controller = new ContactController();
        echo $controller->index();
        break;
    case 'transactions':
        $controller = new TransactionController();
        echo $controller->index();
        break;
    case 'credit_cards':
        $controller = new CreditCardController();
        echo $controller->index();
        break;
    case 'reminders':
        $controller = new ReminderController();
        echo $controller->index();
        break;
    case 'loans':
        $controller = new LoanController();
        echo $controller->index();
        break;
    case 'lending':
        $controller = new LendingController();
        echo $controller->index();
        break;
    case 'investments':
        $controller = new InvestmentController();
        echo $controller->index();
        break;
    case 'sip':
        $controller = new SipController();
        echo $controller->index();
        break;
    case 'rental':
        $controller = new RentalController();
        echo $controller->index();
        break;
    case 'dashboard':
    default:
        $controller = new DashboardController();
        echo $controller->index();
        break;
}
