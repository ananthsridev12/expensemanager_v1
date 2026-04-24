<?php
/**
 * Daily report configuration.
 *
 * SMTP setup (cPanel shared hosting):
 *   1. cPanel → Email Accounts → create an email like reports@yourdomain.com
 *   2. cPanel → Email Accounts → Connect Devices → note the SMTP host/port
 *      Typically:  host = mail.yourdomain.com  port = 465 (SSL)
 *   3. Fill in smtp_user + smtp_pass below with that email's credentials
 *   4. Set smtp_from to the same email address (required by most hosts)
 */
return [

    // ── SMTP ─────────────────────────────────────────────────────────────────
    'smtp_host' => '',                      // e.g. mail.yourdomain.com
    'smtp_port' => 465,                     // 465 = SSL  |  587 = STARTTLS
    'smtp_user' => '',                      // full email: reports@yourdomain.com
    'smtp_pass' => '',                      // email account password
    'smtp_from' => '',                      // same as smtp_user (most hosts require this)
    'smtp_name' => 'Easi7 Finance',         // display name in inbox

    // ── WhatsApp (future) ─────────────────────────────────────────────────────
    'whatsapp_enabled' => false,
    'whatsapp_phone'   => '',
    'whatsapp_apikey'  => '',

    // ── Database (for cron/daily_report.php only) ─────────────────────────────
    'db_host'    => 'localhost',
    'db_name'    => 'de2shrnx_expensemanager',
    'db_user'    => '',
    'db_pass'    => '',
    'db_charset' => 'utf8mb4',

];
