<?php
/**
 * Daily report configuration.
 * Fill in your credentials before enabling.
 */
return [

    // ── Email ────────────────────────────────────────────────────────────────
    'email_enabled' => false,
    'email_to'      => 'you@example.com',        // recipient
    'email_from'    => 'reports@yourdomain.com',  // sender (must be valid on your host)
    'email_name'    => 'Easi7 Finance',

    // ── WhatsApp via CallMeBot (free, personal use) ───────────────────────────
    // Setup steps:
    //   1. Save this number in your phone contacts: +34 644 59 21 83  (CallMeBot)
    //   2. Send them a WhatsApp message: "I allow callmebot to send me messages"
    //   3. You will receive an API key via WhatsApp — paste it below.
    'whatsapp_enabled' => false,
    'whatsapp_phone'   => '91XXXXXXXXXX',  // your number with country code, no + or spaces
    'whatsapp_apikey'  => '',

    // ── Database ─────────────────────────────────────────────────────────────
    // Same credentials as your main app DB.
    'db_host'    => 'localhost',
    'db_name'    => 'de2shrnx_expensemanager',
    'db_user'    => '',
    'db_pass'    => '',
    'db_charset' => 'utf8mb4',

];
