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

        $html    = $this->buildHtml($periodLabel, $startDate, $endDate, $totalExpense, $totalIncome, $expCount, $incCount, $categories, $topTx);
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
        return ['type' => 'error', 'msg' => 'Failed to send. Check SMTP credentials and server error log.'];

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
        array  $topTx
    ): string {
        $net     = $totalIncome - $totalExpense;
        $netCol  = $net >= 0 ? '#22c55e' : '#f43f5e';
        $netSign = $net >= 0 ? '+' : '-';

        // Pre-compute formatted values for safe use in heredoc
        $fExpense  = $this->fmt($totalExpense);
        $fIncome   = $this->fmt($totalIncome);
        $fNet      = $netSign . $this->fmt(abs($net));

        // Category rows (built via concatenation — no closures in strings)
        $catRows = '';
        foreach ($categories as $i => $cat) {
            $pct    = $totalExpense > 0 ? round((float) $cat['total'] / $totalExpense * 100, 1) : 0;
            $barW   = min(100, $pct);
            $barCol = $pct >= 40 ? '#f43f5e' : ($pct >= 20 ? '#f97316' : '#6366f1');
            $bgRow  = $i % 2 === 0 ? '#0b1120' : '#0f1a2e';
            $name   = htmlspecialchars($cat['category_name']);
            $amt    = $this->fmt((float) $cat['total']);
            $cnt    = (int) $cat['cnt'];
            $catRows .= '<tr style="background:' . $bgRow . ';">'
                . '<td style="padding:8px 12px;">' . $name . '</td>'
                . '<td style="padding:8px 12px;text-align:right;font-weight:600;">' . $amt . '</td>'
                . '<td style="padding:8px 12px;text-align:right;color:#94a3b8;">' . $pct . '%</td>'
                . '<td style="padding:8px 12px;">'
                .   '<div style="background:#1e293b;border-radius:4px;height:6px;width:120px;">'
                .     '<div style="background:' . $barCol . ';border-radius:4px;height:6px;width:' . $barW . '%;"></div>'
                .   '</div>'
                . '</td>'
                . '<td style="padding:8px 12px;text-align:right;color:#64748b;">' . $cnt . '</td>'
                . '</tr>';
        }

        // Top transaction rows
        $txRows = '';
        foreach ($topTx as $tx) {
            $txDate = date('d M', strtotime($tx['transaction_date']));
            $txDesc = htmlspecialchars($tx['notes'] ?? '—');
            $txCat  = htmlspecialchars($tx['category_name']);
            $txAmt  = $this->fmt((float) $tx['amount']);
            $txRows .= '<tr>'
                . '<td style="padding:6px 12px;color:#94a3b8;font-size:0.82rem;">' . $txDate . '</td>'
                . '<td style="padding:6px 12px;">' . $txDesc . '</td>'
                . '<td style="padding:6px 12px;color:#94a3b8;font-size:0.82rem;">' . $txCat . '</td>'
                . '<td style="padding:6px 12px;text-align:right;font-weight:600;color:#f43f5e;">' . $txAmt . '</td>'
                . '</tr>';
        }

        $topTxSection = $txRows !== '' ? '
        <h3 style="color:#94a3b8;font-size:0.75rem;text-transform:uppercase;letter-spacing:.05em;margin:20px 0 8px;">Top transactions</h3>
        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
            <thead><tr style="color:#475569;font-size:0.72rem;text-transform:uppercase;">
                <th style="padding:6px 12px;text-align:left;border-bottom:1px solid #1e293b;">Date</th>
                <th style="padding:6px 12px;text-align:left;border-bottom:1px solid #1e293b;">Description</th>
                <th style="padding:6px 12px;text-align:left;border-bottom:1px solid #1e293b;">Category</th>
                <th style="padding:6px 12px;text-align:right;border-bottom:1px solid #1e293b;">Amount</th>
            </tr></thead>
            <tbody>' . $txRows . '</tbody>
        </table>' : '';

        $incomeBlock = $totalIncome > 0
            ? '<div style="padding:10px 16px;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);border-radius:8px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;">'
            .   '<span style="color:#86efac;font-size:0.85rem;">Income received</span>'
            .   '<span style="color:#22c55e;font-weight:700;font-size:1rem;">' . $fIncome . '</span>'
            . '</div>'
            : '';

        $noExpense = $totalExpense == 0
            ? '<p style="color:#94a3b8;font-style:italic;text-align:center;padding:16px 0;">No expenses in this period.</p>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="background:#060b16;font-family:Inter,Arial,sans-serif;color:#e5ecff;padding:24px 12px;">
<div style="max-width:580px;margin:0 auto;background:#0f1a2e;border-radius:14px;overflow:hidden;border:1px solid #1e293b;box-shadow:0 8px 32px rgba(0,0,0,0.5);">

    <div style="background:linear-gradient(135deg,#1e3a5f 0%,#1e1b4b 100%);padding:24px 28px;">
        <div style="font-size:1.5rem;font-weight:800;color:#fff;letter-spacing:-0.02em;">Expense Report</div>
        <div style="color:#93c5fd;font-size:0.88rem;margin-top:4px;">{$periodLabel}</div>
        <div style="color:#475569;font-size:0.75rem;margin-top:2px;">{$startDate} to {$endDate}</div>
    </div>

    <div style="padding:24px 28px;">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:20px;">
            <div style="background:#0b1120;border-radius:10px;padding:14px 16px;border:1px solid #1e293b;">
                <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Total Spent</div>
                <div style="font-size:1.1rem;font-weight:700;color:#f43f5e;">{$fExpense}</div>
                <div style="font-size:0.72rem;color:#475569;margin-top:2px;">{$expCount} transactions</div>
            </div>
            <div style="background:#0b1120;border-radius:10px;padding:14px 16px;border:1px solid #1e293b;">
                <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Income</div>
                <div style="font-size:1.1rem;font-weight:700;color:#22c55e;">{$fIncome}</div>
                <div style="font-size:0.72rem;color:#475569;margin-top:2px;">{$incCount} transactions</div>
            </div>
            <div style="background:#0b1120;border-radius:10px;padding:14px 16px;border:1px solid #1e293b;">
                <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Net</div>
                <div style="font-size:1.1rem;font-weight:700;color:{$netCol};">{$fNet}</div>
                <div style="font-size:0.72rem;color:#475569;margin-top:2px;">&nbsp;</div>
            </div>
        </div>

        {$noExpense}
        {$incomeBlock}

        <h3 style="color:#94a3b8;font-size:0.75rem;text-transform:uppercase;letter-spacing:.05em;margin:0 0 8px;">Expenses by category</h3>
        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;border-radius:8px;overflow:hidden;">
            <thead><tr style="color:#475569;font-size:0.72rem;text-transform:uppercase;">
                <th style="padding:8px 12px;text-align:left;border-bottom:1px solid #1e293b;">Category</th>
                <th style="padding:8px 12px;text-align:right;border-bottom:1px solid #1e293b;">Amount</th>
                <th style="padding:8px 12px;text-align:right;border-bottom:1px solid #1e293b;">%</th>
                <th style="padding:8px 12px;border-bottom:1px solid #1e293b;"></th>
                <th style="padding:8px 12px;text-align:right;border-bottom:1px solid #1e293b;">Txn</th>
            </tr></thead>
            <tbody>{$catRows}</tbody>
        </table>

        {$topTxSection}
    </div>

    <div style="padding:14px 28px;background:#070d1a;border-top:1px solid #0f1a2e;text-align:center;color:#334155;font-size:0.72rem;">
        Easi7 Finance &middot; personalfin.easi7.in
    </div>
</div>
</body>
</html>
HTML;
    }
}
