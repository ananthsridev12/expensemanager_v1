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
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Unlock</title>';
        echo '<style>body{font-family:Arial, sans-serif;background:#f7f7f7;margin:0;padding:0;display:flex;align-items:center;justify-content:center;height:100vh}';
        echo '.card{background:#fff;padding:24px;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.08);width:320px}';
        echo 'h1{font-size:18px;margin:0 0 12px}label{display:block;margin:10px 0 6px;font-size:13px}';
        echo 'input{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:16px}';
        echo 'button{margin-top:12px;width:100%;padding:10px;border:0;border-radius:8px;background:#2f7bff;color:#fff;font-size:15px;cursor:pointer}';
        echo '.error{color:#c0392b;font-size:13px;margin-top:8px}</style></head><body>';
        echo '<div class="card"><h1>Enter PIN</h1><form method="post">';
        echo '<input type="hidden" name="form" value="pin_unlock">';
        echo '<label>PIN</label><input type="password" name="pin" inputmode="numeric" autocomplete="off" required>';
        if ($pinError !== '') {
            echo '<div class="error">' . htmlspecialchars($pinError) . '</div>';
        }
        echo '<button type="submit">Unlock</button></form></div></body></html>';
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
