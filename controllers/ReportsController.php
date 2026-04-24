<?php

namespace Controllers;

use Models\Mailer;

class ReportsController extends BaseController
{
    public function index(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $cfg = $this->loadConfig();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'send_report') {
            $_SESSION['report_flash'] = $this->handleSend($cfg);
            header('Location: ?module=reports');
            exit;
        }

        $flash = null;
        if (!empty($_SESSION['report_flash'])) {
            $flash = $_SESSION['report_flash'];
            unset($_SESSION['report_flash']);
        }

        $smtpReady = !empty($cfg['smtp_host']) && !empty($cfg['smtp_user']) && !empty($cfg['smtp_pass']);

        return $this->render('reports/index.php', [
            'flash'     => $flash,
            'smtpReady' => $smtpReady,
            'smtpHost'  => $cfg['smtp_host'] ?? '',
            'smtpUser'  => $cfg['smtp_user'] ?? '',
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function loadConfig(): array
    {
        $path = __DIR__ . '/../config/report.php';
        return file_exists($path) ? (require $path) : [];
    }

    private function handleSend(array $cfg): array
    {
        $email  = trim($_POST['email']  ?? '');
        $period = trim($_POST['period'] ?? 'yesterday');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['type' => 'error', 'msg' => 'Please enter a valid email address.'];
        }

        if (empty($cfg['smtp_host']) || empty($cfg['smtp_user']) || empty($cfg['smtp_pass'])) {
            return ['type' => 'error', 'msg' => 'SMTP is not configured. Fill in config/report.php first.'];
        }

        [$startDate, $endDate, $periodLabel] = $this->resolvePeriod($period);

        try {
        $pdo = $this->database->connect();

        $totalsStmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN transaction_type='expense' THEN amount ELSE 0 END),0) AS total_expense,
                COALESCE(SUM(CASE WHEN transaction_type='income'  THEN amount ELSE 0 END),0) AS total_income,
                COUNT(CASE WHEN transaction_type='expense' THEN 1 END)                       AS expense_count,
                COUNT(CASE WHEN transaction_type='income'  THEN 1 END)                       AS income_count
            FROM transactions
            WHERE DATE(transaction_date) BETWEEN :s AND :e
        ");
        $totalsStmt->execute([':s' => $startDate, ':e' => $endDate]);
        $totals = $totalsStmt->fetch(\PDO::FETCH_ASSOC);

        $totalExpense = (float) $totals['total_expense'];
        $totalIncome  = (float) $totals['total_income'];
        $expCount     = (int)   $totals['expense_count'];
        $incCount     = (int)   $totals['income_count'];

        $catStmt = $pdo->prepare("
            SELECT
                COALESCE(c.name,'Uncategorized') AS category_name,
                SUM(t.amount)                    AS total,
                COUNT(*)                         AS cnt
            FROM transactions t
            LEFT JOIN categories c ON c.id = t.category_id
            WHERE DATE(t.transaction_date) BETWEEN :s AND :e
              AND t.transaction_type = 'expense'
              AND (t.category_id IS NULL OR t.category_id NOT IN (
                  SELECT id FROM categories WHERE exclude_from_analytics = 1
              ))
            GROUP BY t.category_id
            ORDER BY total DESC
            LIMIT 10
        ");
        $catStmt->execute([':s' => $startDate, ':e' => $endDate]);
        $categories = $catStmt->fetchAll(\PDO::FETCH_ASSOC);

        $txStmt = $pdo->prepare("
            SELECT t.transaction_date, t.notes, t.amount,
                   COALESCE(c.name,'Uncategorized') AS category_name
            FROM transactions t
            LEFT JOIN categories c ON c.id = t.category_id
            WHERE DATE(t.transaction_date) BETWEEN :s AND :e
              AND t.transaction_type = 'expense'
            ORDER BY t.amount DESC
            LIMIT 5
        ");
        $txStmt->execute([':s' => $startDate, ':e' => $endDate]);
        $topTx = $txStmt->fetchAll(\PDO::FETCH_ASSOC);

        $accStmt = $pdo->query("
            SELECT
                a.bank_name,
                a.account_name,
                a.account_type,
                COALESCE(
                    a.opening_balance + SUM(CASE
                        WHEN t.transaction_type = 'income'  THEN  t.amount
                        WHEN t.transaction_type = 'expense' THEN -t.amount
                        ELSE 0
                    END),
                    a.opening_balance
                ) AS balance
            FROM accounts a
            LEFT JOIN transactions t ON t.account_id = a.id
            WHERE a.account_type != 'credit_card'
            GROUP BY a.id
            ORDER BY balance DESC
        ");
        $accounts = $accStmt->fetchAll(\PDO::FETCH_ASSOC);

        $html    = $this->buildHtml($periodLabel, $startDate, $endDate, $totalExpense, $totalIncome, $expCount, $incCount, $categories, $topTx, $accounts);
        $subject = 'Expense Report: ' . $periodLabel . ' — Rs.' . number_format($totalExpense, 2);

        $mailer = new Mailer(
            $cfg['smtp_host'],
            (int) ($cfg['smtp_port'] ?? 465),
            $cfg['smtp_user'],
            $cfg['smtp_pass'],
            $cfg['smtp_from'] ?? $cfg['smtp_user'],
            $cfg['smtp_name'] ?? 'Easi7 Finance'
        );

        $ok = $mailer->send($email, $subject, $html);

        if ($ok) {
            return ['type' => 'success', 'msg' => 'Report for ' . $periodLabel . ' sent to ' . $email . '.'];
        }
        return ['type' => 'error', 'msg' => $mailer->getLastError() ?: 'Send failed — check server error log.'];

        } catch (\Throwable $e) {
            error_log('[Reports] ' . $e->getMessage());
            return ['type' => 'error', 'msg' => 'Error: ' . $e->getMessage()];
        }
    }

    private function resolvePeriod(string $period): array
    {
        switch ($period) {
            case 'yesterday':
                $d = date('Y-m-d', strtotime('-1 day'));
                return [$d, $d, 'Yesterday (' . date('d M Y', strtotime($d)) . ')'];
            case 'last7':
                return [date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime('-1 day')), 'Last 7 days'];
            case 'thismonth':
                return [date('Y-m-01'), date('Y-m-d'), 'This month (' . date('F Y') . ')'];
            case 'lastmonth':
                $s = date('Y-m-01', strtotime('first day of last month'));
                $e = date('Y-m-t',  strtotime('last day of last month'));
                return [$s, $e, 'Last month (' . date('F Y', strtotime($s)) . ')'];
            case 'custom':
                $s = trim($_POST['custom_start'] ?? date('Y-m-01'));
                $e = trim($_POST['custom_end']   ?? date('Y-m-d'));
                if (!$this->isValidDate($s)) $s = date('Y-m-01');
                if (!$this->isValidDate($e)) $e = date('Y-m-d');
                if ($s > $e) [$s, $e] = [$e, $s];
                $label = $s === $e
                    ? date('d M Y', strtotime($s))
                    : date('d M Y', strtotime($s)) . ' to ' . date('d M Y', strtotime($e));
                return [$s, $e, $label];
            default:
                $d = date('Y-m-d', strtotime('-1 day'));
                return [$d, $d, 'Yesterday'];
        }
    }

    private function isValidDate(string $d): bool
    {
        $p = date_create_from_format('Y-m-d', $d);
        return $p !== false && $p->format('Y-m-d') === $d;
    }

    private function fmt(float $v): string
    {
        return 'Rs.' . number_format($v, 2, '.', ',');
    }

    private function buildHtml(
        string $periodLabel,
        string $startDate,
        string $endDate,
        float  $totalExpense,
        float  $totalIncome,
        int    $expCount,
        int    $incCount,
        array  $categories,
        array  $topTx,
        array  $accounts = []
    ): string {
        $net     = $totalIncome - $totalExpense;
        $netCol  = $net >= 0 ? '#22c55e' : '#f43f5e';
        $netSign = $net >= 0 ? '+' : '-';

        $fExpense = $this->fmt($totalExpense);
        $fIncome  = $this->fmt($totalIncome);
        $fNet     = $netSign . $this->fmt(abs($net));

        // Category rows — 2-column layout: [name + bar + meta] | [amount]
        $catRows = '';
        foreach ($categories as $i => $cat) {
            $pct    = $totalExpense > 0 ? round((float) $cat['total'] / $totalExpense * 100, 1) : 0;
            $barCol = $pct >= 40 ? '#f43f5e' : ($pct >= 20 ? '#f97316' : '#6366f1');
            $bgRow  = $i % 2 === 0 ? '#0b1120' : '#0f1a2e';
            $name   = htmlspecialchars($cat['category_name']);
            $amt    = $this->fmt((float) $cat['total']);
            $cnt    = (int) $cat['cnt'];
            $txnLbl = $cnt . ' txn' . ($cnt !== 1 ? 's' : '');
            // Fixed-pixel bar (outer=160px, inner scales with pct)
            $barPx  = (int) round($pct / 100 * 160);
            $barHtml = '<div style="background:#1e293b;border-radius:4px;height:5px;width:160px;margin:5px 0 3px;">'
                     . '<div style="background:' . $barCol . ';border-radius:4px;height:5px;width:' . $barPx . 'px;"></div>'
                     . '</div>';
            $catRows .= '<tr style="background:' . $bgRow . ';">'
                . '<td style="padding:10px 12px;word-break:break-word;">'
                .   '<div style="font-weight:600;color:#e2e8f0;">' . $name . '</div>'
                .   $barHtml
                .   '<div style="font-size:0.72rem;color:#94a3b8;">' . $pct . '% &middot; ' . $txnLbl . '</div>'
                . '</td>'
                . '<td style="padding:10px 12px;text-align:right;font-weight:600;color:#e2e8f0;white-space:nowrap;vertical-align:top;">' . $amt . '</td>'
                . '</tr>';
        }

        // Top transaction rows — 3 columns: date | notes + category subtext | amount
        $txRows = '';
        foreach ($topTx as $tx) {
            $txDate = htmlspecialchars(date('d M', strtotime($tx['transaction_date'])));
            $txDesc = htmlspecialchars($tx['notes'] ?? '—');
            $txCat  = htmlspecialchars($tx['category_name']);
            $txAmt  = $this->fmt((float) $tx['amount']);
            $txRows .= '<tr>'
                . '<td style="padding:8px 10px;color:#94a3b8;font-size:0.8rem;white-space:nowrap;vertical-align:top;">' . $txDate . '</td>'
                . '<td style="padding:8px 10px;word-break:break-word;vertical-align:top;">'
                .   '<div>' . $txDesc . '</div>'
                .   '<div style="font-size:0.75rem;color:#64748b;margin-top:2px;">' . $txCat . '</div>'
                . '</td>'
                . '<td style="padding:8px 10px;text-align:right;font-weight:600;color:#f43f5e;white-space:nowrap;vertical-align:top;">' . $txAmt . '</td>'
                . '</tr>';
        }

        $topTxSection = $txRows !== ''
            ? '<div style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin:20px 0 8px;">Top transactions</div>'
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-size:0.85rem;">'
            .   '<thead><tr style="border-bottom:1px solid #1e293b;">'
            .     '<th style="padding:6px 10px;text-align:left;color:#475569;font-size:0.72rem;font-weight:600;white-space:nowrap;">Date</th>'
            .     '<th style="padding:6px 10px;text-align:left;color:#475569;font-size:0.72rem;font-weight:600;">Notes</th>'
            .     '<th style="padding:6px 10px;text-align:right;color:#475569;font-size:0.72rem;font-weight:600;white-space:nowrap;">Amount</th>'
            .   '</tr></thead>'
            .   '<tbody>' . $txRows . '</tbody>'
            . '</table>'
            : '';

        // Income block — table row instead of flexbox
        $incomeBlock = $totalIncome > 0
            ? '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0a1f12;border:1px solid #166534;border-radius:8px;margin-bottom:12px;">'
            .   '<tr>'
            .     '<td style="padding:10px 16px;color:#86efac;font-size:0.85rem;">Income received</td>'
            .     '<td style="padding:10px 16px;text-align:right;color:#22c55e;font-weight:700;font-size:1rem;white-space:nowrap;">' . $fIncome . '</td>'
            .   '</tr>'
            . '</table>'
            : '';

        $noExpense = $totalExpense == 0
            ? '<p style="color:#94a3b8;font-style:italic;text-align:center;padding:16px 0;">No expenses in this period.</p>'
            : '';

        // Account balances section
        $accRows = '';
        $totalAcc = 0.0;
        foreach ($accounts as $acc) {
            $bal       = (float) $acc['balance'];
            $totalAcc += $bal;
            $accName   = htmlspecialchars(trim($acc['bank_name'] . ' ' . $acc['account_name']));
            $typeLabel = ucfirst(str_replace('_', ' ', $acc['account_type'] ?? ''));
            $fBal      = $this->fmt($bal);
            $balCol    = $bal < 0 ? '#f43f5e' : '#e2e8f0';
            $accRows  .= '<tr>'
                . '<td style="padding:8px 12px;word-break:break-word;">'
                .   '<div style="color:#e2e8f0;">' . $accName . '</div>'
                .   '<div style="font-size:0.72rem;color:#64748b;margin-top:1px;">' . $typeLabel . '</div>'
                . '</td>'
                . '<td style="padding:8px 12px;text-align:right;font-weight:600;color:' . $balCol . ';white-space:nowrap;vertical-align:top;">' . $fBal . '</td>'
                . '</tr>';
        }
        $fTotalAcc    = $this->fmt($totalAcc);
        $totalAccCol  = $totalAcc < 0 ? '#f43f5e' : '#22c55e';
        $accountsSection = $accRows !== ''
            ? '<div style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin:20px 0 8px;">Account balances</div>'
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-size:0.85rem;">'
            .   '<thead><tr style="border-bottom:1px solid #1e293b;">'
            .     '<th style="padding:6px 12px;text-align:left;color:#475569;font-size:0.72rem;font-weight:600;">Account</th>'
            .     '<th style="padding:6px 12px;text-align:right;color:#475569;font-size:0.72rem;font-weight:600;white-space:nowrap;">Balance</th>'
            .   '</tr></thead>'
            .   '<tbody>' . $accRows . '</tbody>'
            .   '<tfoot><tr style="border-top:1px solid #1e293b;">'
            .     '<td style="padding:8px 12px;font-weight:600;color:#94a3b8;font-size:0.8rem;">Total</td>'
            .     '<td style="padding:8px 12px;text-align:right;font-weight:700;color:' . $totalAccCol . ';white-space:nowrap;">' . $fTotalAcc . '</td>'
            .   '</tr></tfoot>'
            . '</table>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#060b16;font-family:Arial,sans-serif;color:#e5ecff;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#060b16;padding:24px 12px;">
<tr><td align="center">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#0f1a2e;border-radius:14px;border:1px solid #1e293b;">

    <!-- Header -->
    <tr>
        <td style="background:#1e3a5f;padding:24px 28px;border-radius:14px 14px 0 0;">
            <div style="font-size:1.3rem;font-weight:800;color:#fff;">Expense Report</div>
            <div style="color:#93c5fd;font-size:0.88rem;margin-top:4px;">{$periodLabel}</div>
            <div style="color:#475569;font-size:0.75rem;margin-top:2px;">{$startDate} to {$endDate}</div>
        </td>
    </tr>

    <!-- Body -->
    <tr><td style="padding:24px 20px;">

        <!-- Summary cards — 3-column table (no CSS grid) -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
        <tr>
            <td width="33%" valign="top" style="padding-right:4px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0b1120;border-radius:8px;border:1px solid #1e293b;">
                <tr><td style="padding:12px 14px;">
                    <div style="font-size:0.68rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Spent</div>
                    <div style="font-size:0.95rem;font-weight:700;color:#f43f5e;">{$fExpense}</div>
                    <div style="font-size:0.68rem;color:#475569;margin-top:2px;">{$expCount} txns</div>
                </td></tr>
                </table>
            </td>
            <td width="33%" valign="top" style="padding:0 2px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0b1120;border-radius:8px;border:1px solid #1e293b;">
                <tr><td style="padding:12px 14px;">
                    <div style="font-size:0.68rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Income</div>
                    <div style="font-size:0.95rem;font-weight:700;color:#22c55e;">{$fIncome}</div>
                    <div style="font-size:0.68rem;color:#475569;margin-top:2px;">{$incCount} txns</div>
                </td></tr>
                </table>
            </td>
            <td width="34%" valign="top" style="padding-left:4px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0b1120;border-radius:8px;border:1px solid #1e293b;">
                <tr><td style="padding:12px 14px;">
                    <div style="font-size:0.68rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Net</div>
                    <div style="font-size:0.95rem;font-weight:700;color:{$netCol};">{$fNet}</div>
                    <div style="font-size:0.68rem;color:#475569;margin-top:2px;">&nbsp;</div>
                </td></tr>
                </table>
            </td>
        </tr>
        </table>

        {$noExpense}
        {$incomeBlock}

        <!-- Expenses by category -->
        <div style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin:0 0 8px;">Expenses by category</div>
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-size:0.85rem;">
            <thead>
                <tr style="border-bottom:1px solid #1e293b;">
                    <th style="padding:6px 12px;text-align:left;color:#475569;font-size:0.72rem;font-weight:600;">Category</th>
                    <th style="padding:6px 12px;text-align:right;color:#475569;font-size:0.72rem;font-weight:600;white-space:nowrap;">Amount</th>
                </tr>
            </thead>
            <tbody>{$catRows}</tbody>
        </table>

        {$topTxSection}
        {$accountsSection}

    </td></tr>

    <!-- Footer -->
    <tr>
        <td style="padding:14px 28px;background:#070d1a;border-top:1px solid #0f1a2e;text-align:center;color:#334155;font-size:0.72rem;border-radius:0 0 14px 14px;">
            Easi7 Finance &middot; personalfin.easi7.in
        </td>
    </tr>

</table>

</td></tr>
</table>
</body>
</html>
HTML;
    }
}
