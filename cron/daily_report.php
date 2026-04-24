<?php
/**
 * Daily expense report — email + WhatsApp snapshot of yesterday's spending.
 *
 * HOW TO SET UP IN cPANEL:
 *   cPanel → Cron Jobs → Add New Cron Job
 *   Schedule : 0 8 * * *   (runs every day at 8:00 AM server time)
 *   Command  : /usr/bin/php /home1/de2shrnx/personalfin.easi7.in/cron/daily_report.php >> /dev/null 2>&1
 *
 * Fill in config/report.php before enabling.
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
    error_log('[DailyReport] DB connection failed: ' . $e->getMessage());
    exit(1);
}

// ── Date range: yesterday ────────────────────────────────────────────────────
$yesterday  = date('Y-m-d', strtotime('-1 day'));
$dayLabel   = date('D, d M Y', strtotime($yesterday));

// ── Query: totals ────────────────────────────────────────────────────────────
$totals = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS total_expense,
        COALESCE(SUM(CASE WHEN transaction_type = 'income'  THEN amount ELSE 0 END), 0) AS total_income,
        COUNT(CASE WHEN transaction_type = 'expense' THEN 1 END)                        AS expense_count
    FROM transactions
    WHERE DATE(transaction_date) = :d
");
$totals->execute([':d' => $yesterday]);
$row = $totals->fetch();
$totalExpense  = (float) $row['total_expense'];
$totalIncome   = (float) $row['total_income'];
$expenseCount  = (int)   $row['expense_count'];

// ── Query: expense by category (top 8) ───────────────────────────────────────
$catStmt = $pdo->prepare("
    SELECT
        COALESCE(c.name, 'Uncategorized') AS category_name,
        SUM(t.amount)                     AS total,
        COUNT(*)                          AS cnt
    FROM transactions t
    LEFT JOIN categories c ON c.id = t.category_id
    WHERE DATE(t.transaction_date) = :d
      AND t.transaction_type = 'expense'
      AND (t.category_id IS NULL OR t.category_id NOT IN (SELECT id FROM categories WHERE exclude_from_analytics = 1))
    GROUP BY t.category_id
    ORDER BY total DESC
    LIMIT 8
");
$catStmt->execute([':d' => $yesterday]);
$categories = $catStmt->fetchAll();

// ── Format helpers ────────────────────────────────────────────────────────────
$fmt  = fn(float $v): string => '₹' . number_format($v, 2, '.', ',');
$fmtS = fn(float $v): string => '₹' . number_format($v, 0, '.', ',');  // short, no decimals

// ── Build text message (for WhatsApp — keep concise) ─────────────────────────
$lines = [];
$lines[] = "📊 *Daily Spend — {$dayLabel}*";
$lines[] = '';
$lines[] = "💸 Spent   : *{$fmt($totalExpense)}* ({$expenseCount} txn)";
if ($totalIncome > 0) {
    $lines[] = "💰 Income  : *{$fmt($totalIncome)}*";
}
$lines[] = '';
if (!empty($categories)) {
    $lines[] = '*By category:*';
    foreach ($categories as $cat) {
        $lines[] = "  • {$cat['category_name']}: {$fmtS((float)$cat['total'])} ({$cat['cnt']} txn)";
    }
}
if ($totalExpense == 0) {
    $lines[] = '_No expenses recorded yesterday._';
}
$textMessage = implode("\n", $lines);

// ── Build HTML email ──────────────────────────────────────────────────────────
$catRows = '';
foreach ($categories as $cat) {
    $pct     = $totalExpense > 0 ? round((float)$cat['total'] / $totalExpense * 100, 1) : 0;
    $catRows .= "<tr>
        <td style='padding:6px 10px;border-bottom:1px solid #1e293b;'>{$cat['category_name']}</td>
        <td style='padding:6px 10px;border-bottom:1px solid #1e293b;text-align:right;font-weight:600;'>{$fmt((float)$cat['total'])}</td>
        <td style='padding:6px 10px;border-bottom:1px solid #1e293b;text-align:right;color:#94a3b8;'>{$pct}%</td>
        <td style='padding:6px 10px;border-bottom:1px solid #1e293b;text-align:right;color:#64748b;'>{$cat['cnt']}</td>
    </tr>";
}

$incomeRow = $totalIncome > 0
    ? "<tr><td style='padding:6px 10px;color:#22c55e;'>Income</td><td style='padding:6px 10px;text-align:right;font-weight:700;color:#22c55e;'>{$fmt($totalIncome)}</td><td colspan='2'></td></tr>"
    : '';

$noDataMsg = $totalExpense == 0
    ? "<p style='color:#94a3b8;font-style:italic;'>No expenses recorded yesterday.</p>"
    : '';

$htmlEmail = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#060b16;font-family:Inter,Arial,sans-serif;color:#e5ecff;">
<div style="max-width:520px;margin:24px auto;background:#0f1a2e;border-radius:12px;overflow:hidden;border:1px solid #1e293b;">
    <div style="background:linear-gradient(135deg,#1e3a5f,#1e1b4b);padding:20px 24px;">
        <div style="font-size:1.4rem;font-weight:700;color:#fff;">📊 Daily Spend Report</div>
        <div style="color:#93c5fd;font-size:0.9rem;margin-top:4px;">{$dayLabel}</div>
    </div>
    <div style="padding:20px 24px;">
        <table style="width:100%;border-collapse:collapse;margin-bottom:16px;">
            <tr>
                <td style="padding:10px 12px;background:#0b1120;border-radius:8px 0 0 8px;font-size:0.8rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">Total Spent</td>
                <td style="padding:10px 12px;background:#0b1120;border-radius:0 8px 8px 0;text-align:right;font-size:1.3rem;font-weight:700;color:#f43f5e;">{$fmt($totalExpense)}</td>
            </tr>
        </table>
        <div style="font-size:0.75rem;color:#94a3b8;margin-bottom:4px;">{$expenseCount} expense transaction(s)</div>
        {$noDataMsg}
        {$incomeRow}
        <table style="width:100%;border-collapse:collapse;margin-top:16px;font-size:0.88rem;">
            <thead>
                <tr style="color:#64748b;font-size:0.75rem;text-transform:uppercase;letter-spacing:.04em;">
                    <th style="padding:6px 10px;text-align:left;border-bottom:1px solid #1e293b;">Category</th>
                    <th style="padding:6px 10px;text-align:right;border-bottom:1px solid #1e293b;">Amount</th>
                    <th style="padding:6px 10px;text-align:right;border-bottom:1px solid #1e293b;">%</th>
                    <th style="padding:6px 10px;text-align:right;border-bottom:1px solid #1e293b;">Txn</th>
                </tr>
            </thead>
            <tbody>{$catRows}</tbody>
        </table>
    </div>
    <div style="padding:12px 24px;background:#070d1a;color:#475569;font-size:0.75rem;text-align:center;">
        Easi7 Finance · automatic daily report
    </div>
</div>
</body>
</html>
HTML;

// ── Send email ────────────────────────────────────────────────────────────────
if ($cfg['email_enabled'] && $cfg['email_to'] !== '') {
    $subject = "Daily Spend {$dayLabel} — {$fmt($totalExpense)}";
    $headers = implode("\r\n", [
        "From: {$cfg['email_name']} <{$cfg['email_from']}>",
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: Easi7Finance/1.0',
    ]);
    $sent = mail($cfg['email_to'], $subject, $htmlEmail, $headers);
    if (!$sent) {
        error_log('[DailyReport] mail() failed for ' . $cfg['email_to']);
    }
}

// ── Send WhatsApp via CallMeBot ───────────────────────────────────────────────
if ($cfg['whatsapp_enabled'] && $cfg['whatsapp_phone'] !== '' && $cfg['whatsapp_apikey'] !== '') {
    $url = 'https://api.callmebot.com/whatsapp.php?' . http_build_query([
        'phone'  => $cfg['whatsapp_phone'],
        'text'   => $textMessage,
        'apikey' => $cfg['whatsapp_apikey'],
    ]);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) {
            error_log('[DailyReport] WhatsApp send failed, HTTP ' . $code . ': ' . $resp);
        }
    } else {
        $ctx  = stream_context_create(['http' => ['timeout' => 15]]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            error_log('[DailyReport] WhatsApp file_get_contents failed');
        }
    }
}
