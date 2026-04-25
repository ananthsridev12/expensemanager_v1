<?php

if (!function_exists('formatCurrency')) {
    function formatCurrency($value): string
    {
        $amount = is_numeric($value) ? (float) $value : 0;
        return '&#8377; ' . number_format($amount, 2);
    }
}

if (!function_exists('whatsappLink')) {
    function whatsappLink(string $mobile, string $message): string
    {
        $digits = preg_replace('/[^0-9]/', '', $mobile);
        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }
        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
    }
}
