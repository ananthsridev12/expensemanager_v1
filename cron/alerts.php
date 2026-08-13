<?php
/**
 * Budget & credit-card due-date alert emails.
 *
 * HOW TO SET UP IN cPANEL:
 *   cPanel → Cron Jobs → Add New Cron Job
 *   Schedule : 0 9 * * *   (runs every day at 9:00 AM server time)
 *   Command  : /usr/bin/php /home1/de2shrnx/personalfin.easi7.in/cron/alerts.php >> /dev/null 2>&1
 *
 * Fill in config/report.php before enabling.
 *
 * What it does:
 *   1. Budget alerts  — sends email when a category hits 80 % or 100 % of its
 *      monthly budget. Each threshold fires at most once per category per month
 *      (deduped via alert_log).
 *   2. CC due-date alerts — sends email 3 days before each credit card's
 *      payment due date. Fires at most once per card per due-date cycle
 *      (deduped via alert_log).
 */

declare(strict_types=1);

$cfg = require __DIR__ . '/../config/report.php';

// ── DB connection ────────────────────────────────────────────────────────────
try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $cfg['db_host'],
        $cfg['db_name'],
        $cfg['db_charset']
    );
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log('[Alerts] DB connection failed: ' . $e->getMessage());
    exit(1);
}

// ── Mailer ───────────────────────────────────────────────────────────────────
$mailerAvailable = $cfg['smtp_host'] !== '' && $cfg['smtp_user'] !== '';
$toEmail         = $cfg['smtp_user']; // send to the configured account

if (!$mailerAvailable) {
    error_log('[Alerts] SMTP not configured — skipping.');
    exit(0);
}

// Lazy-load the Mailer class without pulling in the full app autoloader so this
// script stays self-contained and executable outside the web root.
require_once __DIR__ . '/../models/Mailer.php';

$mailer = new Models\Mailer(
    $cfg['smtp_host'],
    (int) $cfg['smtp_port'],
    $cfg['smtp_user'],
    $cfg['smtp_pass'],
    $cfg['smtp_from'],
    $cfg['smtp_name']
);

$today = new DateTimeImmutable('today');
$month = (int) $today->format('n');
$year  = (int) $today->format('Y');
$ym    = $today->format('Y-m');

// ── Helper: attempt to log alert; returns true if this is a new (unsent) alert
$tryLog = static function (string $alertType, string $refKey) use ($pdo): bool {
    try {
        $pdo->prepare(
            "INSERT IGNORE INTO alert_log (alert_type, ref_key) VALUES (:type, :key)"
        )->execute([':type' => $alertType, ':key' => $refKey]);
        return $pdo->lastInsertId() > 0;
    } catch (PDOException $e) {
        error_log('[Alerts] alert_log insert failed: ' . $e->getMessage());
        return false;
    }
};

$fmt = static fn(float $v): string => '₹' . number_format($v, 2, '.', ',');

// ════════════════════════════════════════════════════════════════════════════
// 1. BUDGET ALERTS
// ════════════════════════════════════════════════════════════════════════════

$budgetStmt = $pdo->prepare("
    SELECT
        c.id   AS category_id,
        c.name AS category_name,
        COALESCE(b.amount, 0)       AS budget_amount,
        COALESCE(SUM(t.amount), 0)  AS spent
    FROM categories c
    LEFT JOIN budgets b
        ON  b.category_id    = c.id
        AND b.subcategory_id = 0
        AND b.month          = :month
        AND b.year           = :year
    LEFT JOIN transactions t
        ON  t.category_id             = c.id
        AND t.transaction_type        = 'expense'
        AND MONTH(t.transaction_date) = :month2
        AND YEAR(t.transaction_date)  = :year2
    WHERE c.type = 'expense'
    GROUP BY c.id, c.name, b.amount
    HAVING budget_amount > 0
    ORDER BY c.name ASC
");
$budgetStmt->execute([
    ':month'  => $month, ':year'  => $year,
    ':month2' => $month, ':year2' => $year,
]);
$budgetRows = $budgetStmt->fetchAll();

foreach ($budgetRows as $row) {
    $budgeted = (float) $row['budget_amount'];
    $spent    = (float) $row['spent'];
    $catId    = (int)   $row['category_id'];
    $catName  = $row['category_name'];
    $pct      = $budgeted > 0 ? ($spent / $budgeted * 100) : 0;

    // 100 % threshold (check first — don't send both on the same run)
    if ($pct >= 100) {
        $refKey = "budget:100:cat_{$catId}:{$ym}";
        if ($tryLog('budget_100', $refKey)) {
            $subject = "[Budget Alert] {$catName} budget fully used ({$ym})";
            $html = buildBudgetEmail($catName, $budgeted, $spent, 100, $ym, $fmt);
            $sent = $mailer->send($toEmail, $subject, $html);
            if (!$sent) {
                error_log('[Alerts] budget_100 email failed for cat ' . $catId . ': ' . $mailer->getLastError());
            }
        }
        continue; // don't also send the 80 % alert for the same category this run
    }

    // 80 % threshold
    if ($pct >= 80) {
        $refKey = "budget:80:cat_{$catId}:{$ym}";
        if ($tryLog('budget_80', $refKey)) {
            $subject = "[Budget Alert] {$catName} at " . round($pct, 0) . "% of budget ({$ym})";
            $html = buildBudgetEmail($catName, $budgeted, $spent, (int) round($pct), $ym, $fmt);
            $sent = $mailer->send($toEmail, $subject, $html);
            if (!$sent) {
                error_log('[Alerts] budget_80 email failed for cat ' . $catId . ': ' . $mailer->getLastError());
            }
        }
    }
}

// ════════════════════════════════════════════════════════════════════════════
// 2. CREDIT CARD DUE DATE ALERTS
// ════════════════════════════════════════════════════════════════════════════

$ccStmt = $pdo->query("
    SELECT cc.id, cc.card_name, cc.bank_name, cc.due_date AS due_day,
           a.account_name
      FROM credit_cards cc
      JOIN accounts a ON a.id = cc.account_id
     WHERE a.is_active = 1
       AND cc.due_date IS NOT NULL
       AND cc.due_date BETWEEN 1 AND 31
");
$cards = $ccStmt->fetchAll();

foreach ($cards as $card) {
    $dueDay  = (int) $card['due_day'];
    $cardId  = (int) $card['id'];
    $cardLabel = trim(($card['bank_name'] ? $card['bank_name'] . ' ' : '') . $card['card_name']);

    // Compute next due date (this month if still upcoming, else next month)
    $dueThisMonth = DateTimeImmutable::createFromFormat(
        'Y-n-j',
        $year . '-' . $month . '-' . min($dueDay, (int) $today->format('t'))
    );

    if ($dueThisMonth < $today) {
        // Compute next month's due date
        $nextMonthFirst = $today->modify('first day of next month');
        $daysInNext     = (int) $nextMonthFirst->format('t');
        $dueDate = DateTimeImmutable::createFromFormat(
            'Y-n-j',
            $nextMonthFirst->format('Y') . '-' . $nextMonthFirst->format('n') . '-' . min($dueDay, $daysInNext)
        );
    } else {
        $dueDate = $dueThisMonth;
    }

    $daysUntilDue = (int) $today->diff($dueDate)->days;
    if ($today > $dueDate) {
        $daysUntilDue = -$daysUntilDue;
    }

    if ($daysUntilDue === 3) {
        $dueDateStr = $dueDate->format('Y-m-d');
        $refKey = "cc_due:{$cardId}:{$dueDateStr}";
        if ($tryLog('cc_due', $refKey)) {
            $subject = "[CC Alert] {$cardLabel} payment due in 3 days ({$dueDate->format('d M Y')})";
            $html = buildCcDueEmail($cardLabel, $dueDate->format('d M Y'), $fmt);
            $sent = $mailer->send($toEmail, $subject, $html);
            if (!$sent) {
                error_log('[Alerts] cc_due email failed for card ' . $cardId . ': ' . $mailer->getLastError());
            }
        }
    }
}

// ════════════════════════════════════════════════════════════════════════════
// Email builders
// ════════════════════════════════════════════════════════════════════════════

function buildBudgetEmail(string $catName, float $budgeted, float $spent, int $pct, string $ym, callable $fmt): string
{
    $remaining  = max(0, $budgeted - $spent);
    $barColor   = $pct >= 100 ? '#f43f5e' : '#f59e0b';
    $barWidth   = min(100, $pct);
    $statusLine = $pct >= 100
        ? "<span style='color:#f43f5e;font-weight:700;'>Budget exceeded!</span>"
        : "<span style='color:#f59e0b;font-weight:700;'>{$pct}% used — {$fmt($remaining)} remaining</span>";

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#060b16;font-family:Inter,Arial,sans-serif;color:#e5ecff;">
<div style="max-width:480px;margin:24px auto;background:#0f1a2e;border-radius:12px;overflow:hidden;border:1px solid #1e293b;">
    <div style="background:linear-gradient(135deg,#1e3a5f,#1e1b4b);padding:20px 24px;">
        <div style="font-size:1.3rem;font-weight:700;color:#fff;">Budget Alert</div>
        <div style="color:#93c5fd;font-size:0.88rem;margin-top:4px;">{$ym}</div>
    </div>
    <div style="padding:20px 24px;">
        <p style="font-size:1rem;margin:0 0 16px;"><strong>{$catName}</strong> has reached <strong>{$pct}%</strong> of its monthly budget.</p>
        <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:0.9rem;">
            <tr>
                <td style="padding:6px 0;color:#94a3b8;">Budgeted</td>
                <td style="padding:6px 0;text-align:right;font-weight:600;">{$fmt($budgeted)}</td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#94a3b8;">Spent</td>
                <td style="padding:6px 0;text-align:right;font-weight:600;color:#f43f5e;">{$fmt($spent)}</td>
            </tr>
        </table>
        <div style="background:#0b1120;border-radius:6px;height:10px;overflow:hidden;margin-bottom:8px;">
            <div style="background:{$barColor};height:100%;width:{$barWidth}%;border-radius:6px;"></div>
        </div>
        <div style="font-size:0.85rem;">{$statusLine}</div>
    </div>
    <div style="padding:12px 24px;background:#070d1a;color:#475569;font-size:0.75rem;text-align:center;">
        Easi7 Finance · budget alert
    </div>
</div>
</body>
</html>
HTML;
}

function buildCcDueEmail(string $cardLabel, string $dueDateLabel, callable $fmt): string
{
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#060b16;font-family:Inter,Arial,sans-serif;color:#e5ecff;">
<div style="max-width:480px;margin:24px auto;background:#0f1a2e;border-radius:12px;overflow:hidden;border:1px solid #1e293b;">
    <div style="background:linear-gradient(135deg,#1e3a5f,#1e1b4b);padding:20px 24px;">
        <div style="font-size:1.3rem;font-weight:700;color:#fff;">Credit Card Due Date</div>
        <div style="color:#93c5fd;font-size:0.88rem;margin-top:4px;">Payment reminder</div>
    </div>
    <div style="padding:20px 24px;">
        <p style="font-size:1rem;margin:0 0 12px;">Your <strong>{$cardLabel}</strong> payment is due in <strong>3 days</strong>.</p>
        <div style="background:#0b1120;border-radius:8px;padding:14px 16px;margin-top:8px;">
            <div style="font-size:0.78rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Due Date</div>
            <div style="font-size:1.2rem;font-weight:700;color:#f59e0b;">{$dueDateLabel}</div>
        </div>
        <p style="margin:16px 0 0;font-size:0.85rem;color:#94a3b8;">Log in to your finance app to record the payment before the due date to avoid late fees.</p>
    </div>
    <div style="padding:12px 24px;background:#070d1a;color:#475569;font-size:0.75rem;text-align:center;">
        Easi7 Finance · credit card alert
    </div>
</div>
</body>
</html>
HTML;
}
