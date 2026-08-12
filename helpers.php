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

/**
 * Group a flat accounts array (from Account::getList / getAllWithBalances) into
 * labelled segments for <optgroup> rendering.
 * Returns: [['label'=>'Savings', 'accounts'=>[...]], ...]
 */
if (!function_exists('groupAccountsForSelect')) {
    function groupAccountsForSelect(array $accounts): array
    {
        $order = [
            'savings'     => 'Savings',
            'current'     => 'Current',
            'credit_card' => 'Credit Cards',
            'cash'        => 'Cash',
            'wallet'      => 'Wallets',
            'other'       => 'Other',
        ];
        $buckets = [];
        foreach ($accounts as $acct) {
            $sysKey   = $acct['account_type_system_key'] ?? null;
            $typeId   = $acct['account_type_id']         ?? null;
            $isCustom = ($sysKey === null || $sysKey === '') && !empty($typeId);
            $gKey     = $isCustom ? 'custom_' . (int) $typeId : ($acct['account_type'] ?? 'other');
            $buckets[$gKey][] = $acct;
        }
        $groups = [];
        foreach ($order as $typeKey => $typeLabel) {
            if (!empty($buckets[$typeKey])) {
                $groups[] = ['label' => $typeLabel, 'accounts' => $buckets[$typeKey]];
            }
        }
        foreach ($buckets as $gKey => $accts) {
            if (!isset($order[$gKey])) {
                $first = $accts[0];
                $label = !empty($first['account_type_name'])
                    ? $first['account_type_name']
                    : ucfirst(str_replace('_', ' ', $gKey));
                $groups[] = ['label' => $label, 'accounts' => $accts];
            }
        }
        return $groups;
    }
}
