<?php

if (!function_exists('formatCurrency')) {
    function formatCurrency($value): string
    {
        $amount = is_numeric($value) ? (float) $value : 0;
        return '&#8377; ' . number_format($amount, 2);
    }
}
