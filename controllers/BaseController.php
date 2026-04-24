<?php

namespace Controllers;

use Config\Database;
use Models\Mailer;

class BaseController
{
    protected Database $database;

    public function __construct()
    {
        $this->database = new Database();
    }

    protected function loadReportConfig(): array
    {
        $path = __DIR__ . '/../config/report.php';
        return file_exists($path) ? (require $path) : [];
    }

    protected function smtpIsReady(): bool
    {
        $cfg = $this->loadReportConfig();
        return !empty($cfg['smtp_host']) && !empty($cfg['smtp_user']) && !empty($cfg['smtp_pass']);
    }

    protected function buildMailer(): ?Mailer
    {
        $cfg = $this->loadReportConfig();
        if (empty($cfg['smtp_host']) || empty($cfg['smtp_user']) || empty($cfg['smtp_pass'])) {
            return null;
        }
        return new Mailer(
            $cfg['smtp_host'],
            (int) ($cfg['smtp_port'] ?? 465),
            $cfg['smtp_user'],
            $cfg['smtp_pass'],
            $cfg['smtp_from'] ?? $cfg['smtp_user'],
            $cfg['smtp_name'] ?? 'Easi7 Finance'
        );
    }

    protected function notificationEmail(string $title, string $recipientName, string $bodyText, array $details = []): string
    {
        $detailsHtml = '';
        if (!empty($details)) {
            $detailsHtml = '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin-top:16px;">';
            $i = 0;
            foreach ($details as $label => $value) {
                $bg = $i % 2 === 0 ? '#0b1120' : '#0f1a2e';
                $detailsHtml .= '<tr style="background:' . $bg . ';">'
                    . '<td style="padding:8px 12px;color:#94a3b8;font-size:0.85rem;">' . htmlspecialchars($label) . '</td>'
                    . '<td style="padding:8px 12px;text-align:right;font-weight:600;color:#e2e8f0;white-space:nowrap;">' . htmlspecialchars($value) . '</td>'
                    . '</tr>';
                $i++;
            }
            $detailsHtml .= '</table>';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
            . '<body style="margin:0;padding:0;background:#060b16;font-family:Arial,sans-serif;color:#e5ecff;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#060b16;padding:24px 12px;"><tr><td align="center">'
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background:#0f1a2e;border-radius:12px;border:1px solid #1e293b;">'
            . '<tr><td style="background:#1e3a5f;padding:20px 24px;border-radius:12px 12px 0 0;">'
            . '<div style="font-size:1.15rem;font-weight:700;color:#fff;">' . htmlspecialchars($title) . '</div>'
            . '<div style="color:#93c5fd;font-size:0.8rem;margin-top:3px;">Easi7 Finance</div>'
            . '</td></tr>'
            . '<tr><td style="padding:24px;">'
            . '<p style="margin:0 0 14px;font-weight:600;color:#e2e8f0;">Dear ' . htmlspecialchars($recipientName) . ',</p>'
            . '<p style="margin:0;color:#cbd5e1;line-height:1.6;">' . htmlspecialchars($bodyText) . '</p>'
            . $detailsHtml
            . '</td></tr>'
            . '<tr><td style="padding:12px 24px;background:#070d1a;border-top:1px solid #0f1a2e;text-align:center;color:#334155;font-size:0.72rem;border-radius:0 0 12px 12px;">'
            . 'Easi7 Finance &middot; personalfin.easi7.in'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    protected function render(string $viewPath, array $params = []): string
    {
        extract($params, EXTR_SKIP);
        ob_start();
        include __DIR__ . '/../views/' . $viewPath;
        $content = ob_get_clean();

        ob_start();
        include __DIR__ . '/../views/layout.php';
        return ob_get_clean();
    }

    /** Render a view without the layout wrapper — for AJAX partial responses. */
    protected function renderPartial(string $viewPath, array $params = []): string
    {
        extract($params, EXTR_SKIP);
        ob_start();
        include __DIR__ . '/../views/' . $viewPath;
        return ob_get_clean();
    }
}
