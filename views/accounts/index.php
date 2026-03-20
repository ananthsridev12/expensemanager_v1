<?php
$activeModule = 'accounts';
$accountSummary = $summary ?? ['count' => 0, 'total_balance' => 0.0];
$accounts = $accounts ?? [];
$accountTypes = $accountTypes ?? [];
$editAccount = $editAccount ?? null;
$lockAccountType = $editAccount && ($editAccount['account_type'] ?? '') === 'credit_card';

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>Accounts</h1>
        <p>Ledger-driven overview of your bank accounts and linked wallets.</p>
    </header>

    <section class="summary-cards">
        <article class="card">
            <h3>Total accounts</h3>
            <p><?= number_format($accountSummary['count'], 0) ?></p>
        </article>
        <article class="card">
            <h3>Ledger balance</h3>
            <p><?= formatCurrency($accountSummary['total_balance']) ?></p>
        </article>
    </section>

    <section class="module-panel">
        <h2><?= $editAccount ? 'Edit account' : 'Add a new account' ?></h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="<?= $editAccount ? 'account_update' : 'account' ?>">
            <?php if ($editAccount): ?>
                <input type="hidden" name="account_id" value="<?= (int) $editAccount['id'] ?>">
            <?php endif; ?>
            <label>
                Account type
                <select name="account_type" id="account-type" required <?= $lockAccountType ? 'disabled' : '' ?>>
                    <optgroup label="System types">
                        <option value="savings" <?= ($editAccount['account_type'] ?? '') === 'savings' ? 'selected' : '' ?>>Savings</option>
                        <option value="current" <?= ($editAccount['account_type'] ?? '') === 'current' ? 'selected' : '' ?>>Current</option>
                        <option value="credit_card" <?= ($editAccount['account_type'] ?? '') === 'credit_card' ? 'selected' : '' ?>>Credit card</option>
                        <option value="cash" <?= ($editAccount['account_type'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="wallet" <?= ($editAccount['account_type'] ?? '') === 'wallet' ? 'selected' : '' ?>>Wallet</option>
                        <option value="other" <?= ($editAccount['account_type'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                    </optgroup>
                    <?php if (!empty($accountTypes)): ?>
                        <?php
                        $customTypes = array_filter(
                            $accountTypes,
                            static fn (array $row): bool => empty($row['system_key'])
                        );
                        ?>
                        <?php if (!empty($customTypes)): ?>
                            <optgroup label="Custom types">
                                <?php foreach ($customTypes as $type): ?>
                                    <option value="<?= 'custom:' . (int) $type['id'] ?>"
                                        data-template="<?= htmlspecialchars($type['template'] ?? 'other') ?>"
                                        <?= ($editAccount['account_type_id'] ?? null) == $type['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    <?php endif; ?>
                    <option value="new">+ Add new type</option>
                </select>
            </label>
            <?php if ($lockAccountType): ?>
                <input type="hidden" name="account_type" value="credit_card">
            <?php endif; ?>
            <div id="new-account-type-wrap" style="display: none;" class="inline-sub-form">
                <label>
                    Custom type name
                    <input type="text" name="new_account_type" placeholder="Example: Travel Card">
                </label>
                <label>
                    Based on template
                    <select name="new_account_type_template" id="new-account-type-template">
                        <option value="savings">Savings account</option>
                        <option value="current">Current account</option>
                        <option value="credit_card">Credit card (rewards, limit, billing cycle)</option>
                        <option value="cash">Cash</option>
                        <option value="wallet">Wallet</option>
                        <option value="other">Other (basic)</option>
                    </select>
                </label>
            </div>
            <label>
                Bank name
                <input type="text" name="bank_name" value="<?= htmlspecialchars($editAccount['bank_name'] ?? '') ?>" required>
            </label>
            <label>
                Account name
                <input type="text" name="account_name" value="<?= htmlspecialchars($editAccount['account_name'] ?? '') ?>" required>
            </label>
            <div id="bank-fields" class="module-form">
                <label>
                    Account number
                    <input type="text" name="account_number" value="<?= htmlspecialchars($editAccount['account_number'] ?? '') ?>">
                </label>
                <label>
                    IFSC
                    <input type="text" name="ifsc" value="<?= htmlspecialchars($editAccount['ifsc'] ?? '') ?>">
                </label>
                <label>
                    Opening balance
                    <input type="number" name="opening_balance" step="0.01" min="0" value="<?= htmlspecialchars((string) ($editAccount['opening_balance'] ?? '0.00')) ?>">
                </label>
            </div>
            <div id="credit-card-fields" class="module-form" style="display: none;">
                <label>
                    Card name
                    <input type="text" name="card_name" value="<?= htmlspecialchars($editAccount['card_name'] ?? '') ?>">
                </label>
                <label>
                    Credit limit
                    <input type="number" name="credit_limit" step="0.01" min="0" value="<?= htmlspecialchars((string) ($editAccount['credit_limit'] ?? '0.00')) ?>">
                </label>
                <label>
                    Billing date (day)
                    <input type="number" name="billing_date" min="1" max="28" value="<?= htmlspecialchars((string) ($editAccount['billing_date'] ?? '1')) ?>">
                </label>
                <label>
                    Due date (day)
                    <input type="number" name="due_date" min="1" max="28" value="<?= htmlspecialchars((string) ($editAccount['due_date'] ?? '1')) ?>">
                </label>
                <label>
                    Outstanding balance
                    <input type="number" name="outstanding_balance" step="0.01" min="0" value="<?= htmlspecialchars((string) ($editAccount['outstanding_balance'] ?? '0.00')) ?>">
                </label>
                <label>
                    Outstanding principal
                    <input type="number" name="outstanding_principal" step="0.01" min="0" value="<?= htmlspecialchars((string) ($editAccount['outstanding_principal'] ?? '0.00')) ?>">
                </label>
                <label>
                    Interest rate (% p.a.)
                    <input type="number" name="interest_rate" step="0.01" min="0" value="<?= htmlspecialchars((string) ($editAccount['interest_rate'] ?? '0')) ?>">
                </label>
                <label>
                    Tenure months
                    <input type="number" name="tenure_months" min="0" value="<?= htmlspecialchars((string) ($editAccount['tenure_months'] ?? '0')) ?>">
                </label>
                <label>
                    Processing fee
                    <input type="number" name="processing_fee" step="0.01" min="0" value="<?= htmlspecialchars((string) ($editAccount['processing_fee'] ?? '0.00')) ?>">
                </label>
                <label>
                    GST rate (%)
                    <input type="number" name="gst_rate" step="0.01" min="0" value="<?= htmlspecialchars((string) ($editAccount['gst_rate'] ?? '18.00')) ?>">
                </label>
                <label>
                    EMI amount (optional)
                    <input type="number" name="emi_amount" step="0.01" min="0" value="<?= htmlspecialchars((string) ($editAccount['emi_amount'] ?? '0.00')) ?>">
                </label>
                <label>
                    EMI start date
                    <input type="date" name="emi_start_date" value="<?= htmlspecialchars((string) ($editAccount['emi_start_date'] ?? '')) ?>">
                </label>
                <label>
                    Reward points balance
                    <input type="number" name="points_balance" step="0.01" min="0" value="<?= htmlspecialchars((string) ($editAccount['points_balance'] ?? '0.00')) ?>">
                </label>
                <label>
                    Fuel surcharge rate (%)
                    <input type="number" name="fuel_surcharge_rate" step="0.01" min="0" max="10" value="<?= htmlspecialchars((string) ($editAccount['fuel_surcharge_rate'] ?? '1.00')) ?>">
                </label>
                <label>
                    Min. spend for surcharge refund (₹)
                    <input type="number" name="fuel_surcharge_min_refund" step="0.01" min="0" value="<?= htmlspecialchars((string) ($editAccount['fuel_surcharge_min_refund'] ?? '400.00')) ?>">
                </label>
            </div>
            <?php if ($editAccount): ?>
            <label style="flex-direction:row;align-items:center;gap:0.5rem;">
                <input type="checkbox" name="is_default" value="1" <?= !empty($editAccount['is_default']) ? 'checked' : '' ?>>
                Set as default account (pre-selected when adding transactions)
            </label>
            <?php endif; ?>
            <button type="submit"><?= $editAccount ? 'Update account' : 'Save account' ?></button>
            <?php if ($editAccount): ?>
                <a class="secondary" href="?module=accounts">Cancel</a>
            <?php endif; ?>
        </form>
    </section>

    <?php
    $typeOrder = ['savings' => 'Savings', 'current' => 'Current', 'credit_card' => 'Credit Cards', 'cash' => 'Cash', 'wallet' => 'Wallets', 'other' => 'Other'];
    $grouped = [];
    foreach ($accounts as $account) {
        $type = $account['account_type'] ?? 'other';
        $grouped[$type][] = $account;
    }
    // Sort groups by defined order, then any custom types at the end
    $orderedGroups = [];
    foreach ($typeOrder as $typeKey => $typeLabel) {
        if (!empty($grouped[$typeKey])) {
            $orderedGroups[$typeKey] = ['label' => $typeLabel, 'accounts' => $grouped[$typeKey]];
        }
    }
    foreach ($grouped as $typeKey => $accs) {
        if (!isset($typeOrder[$typeKey])) {
            $orderedGroups[$typeKey] = ['label' => ucfirst(str_replace('_', ' ', $typeKey)), 'accounts' => $accs];
        }
    }
    ?>

    <?php if (empty($orderedGroups)): ?>
    <section class="module-panel">
        <p class="muted">No accounts added yet.</p>
    </section>
    <?php endif; ?>

    <?php foreach ($orderedGroups as $typeKey => $group): ?>
    <section class="module-panel">
        <h2><?= htmlspecialchars($group['label']) ?></h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Bank / Issuer</th>
                        <th>Name</th>
                        <?php if ($typeKey === 'credit_card'): ?>
                            <th>Outstanding</th>
                            <th>Limit</th>
                            <th>Available</th>
                        <?php else: ?>
                            <th>Balance</th>
                        <?php endif; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($group['accounts'] as $account): ?>
                        <tr>
                            <td><?= htmlspecialchars($account['bank_name']) ?></td>
                            <td>
                                <?= htmlspecialchars($account['account_name']) ?>
                                <?php if (!empty($account['is_default'])): ?>
                                    <span class="pill card--green" style="font-size:0.7rem;margin-left:0.3rem;">Default</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($typeKey === 'credit_card'): ?>
                                <?php
                                $outstanding = (float) ($account['live_cc_outstanding'] ?? $account['outstanding_balance'] ?? 0);
                                $limit       = (float) ($account['credit_limit'] ?? 0);
                                $available   = max(0, $limit - $outstanding);
                                ?>
                                <td><?= formatCurrency($outstanding) ?></td>
                                <td><?= formatCurrency($limit) ?></td>
                                <td><?= formatCurrency($available) ?></td>
                            <?php else: ?>
                                <td><?= formatCurrency((float) ($account['balance'] ?? 0)) ?></td>
                            <?php endif; ?>
                            <td>
                                <a class="secondary" href="?module=accounts&edit=<?= (int) $account['id'] ?>">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($typeKey === 'credit_card' && count($group['accounts']) > 1): ?>
                <tfoot>
                    <?php
                    $totalOutstanding = array_sum(array_map(fn($a) => (float)($a['live_cc_outstanding'] ?? $a['outstanding_balance'] ?? 0), $group['accounts']));
                    $totalLimit       = array_sum(array_map(fn($a) => (float)($a['credit_limit'] ?? 0), $group['accounts']));
                    $totalAvailable   = max(0, $totalLimit - $totalOutstanding);
                    ?>
                    <tr>
                        <td colspan="2"><strong>Total</strong></td>
                        <td><strong><?= formatCurrency($totalOutstanding) ?></strong></td>
                        <td><strong><?= formatCurrency($totalLimit) ?></strong></td>
                        <td><strong><?= formatCurrency($totalAvailable) ?></strong></td>
                    </tr>
                </tfoot>
                <?php elseif ($typeKey !== 'credit_card' && count($group['accounts']) > 1): ?>
                <tfoot>
                    <?php $totalBalance = array_sum(array_map(fn($a) => (float)($a['balance'] ?? 0), $group['accounts'])); ?>
                    <tr>
                        <td colspan="2"><strong>Total</strong></td>
                        <td><strong><?= formatCurrency($totalBalance) ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </section>
    <?php endforeach; ?>

    <?php
    $fuelSurchargeReport = $fuelSurchargeReport ?? [];
    $hasFuelData = !empty($fuelSurchargeReport);
    ?>
    <?php if ($hasFuelData): ?>
    <section class="module-panel">
        <h2>Fuel surcharge tracker</h2>
        <p class="muted" style="margin-bottom:1rem;">Surcharge = spend × rate. GST (18%) is charged on surcharge but <strong>not refunded</strong>. Transactions above the minimum spend qualify for surcharge refund.</p>
        <?php foreach ($fuelSurchargeReport as $cardReport): ?>
            <h3 style="margin:1rem 0 0.5rem;"><?= htmlspecialchars($cardReport['bank_name'] . ' — ' . $cardReport['card_name']) ?> <small class="muted">(<?= $cardReport['surcharge_rate'] ?>% · min ₹<?= number_format($cardReport['min_refund'], 2) ?> for refund)</small></h3>
            <div class="summary-cards" style="margin-bottom:1rem;">
                <article class="card">
                    <h3>Total surcharge charged</h3>
                    <p><?= formatCurrency($cardReport['total_surcharge'] + $cardReport['total_gst']) ?></p>
                    <small>Surcharge <?= formatCurrency($cardReport['total_surcharge']) ?> + GST <?= formatCurrency($cardReport['total_gst']) ?></small>
                </article>
                <article class="card card--green">
                    <h3>Refundable surcharge</h3>
                    <p><?= formatCurrency($cardReport['total_refundable']) ?></p>
                    <small>On eligible transactions</small>
                </article>
                <article class="card card--red">
                    <h3>Net surcharge cost</h3>
                    <p><?= formatCurrency($cardReport['net_cost']) ?></p>
                    <small>GST not refunded</small>
                </article>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Spend</th>
                            <th>Surcharge</th>
                            <th>GST on surcharge</th>
                            <th>Total charged</th>
                            <th>Refund?</th>
                            <th>Net cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cardReport['transactions'] as $tx): ?>
                            <tr>
                                <td><?= htmlspecialchars($tx['date']) ?></td>
                                <td><?= htmlspecialchars($tx['category']) ?></td>
                                <td><?= formatCurrency($tx['amount']) ?></td>
                                <td><?= formatCurrency($tx['surcharge']) ?></td>
                                <td><?= formatCurrency($tx['gst']) ?></td>
                                <td><?= formatCurrency($tx['total_charged']) ?></td>
                                <td>
                                    <?php if ($tx['refundable']): ?>
                                        <span class="pill card--green">Yes <?= formatCurrency($tx['refund_amount']) ?></span>
                                    <?php else: ?>
                                        <span class="pill">No</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatCurrency($tx['net_cost']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <script>
        (function () {
            const typeSelect = document.getElementById('account-type');
            const bankFields = document.getElementById('bank-fields');
            const cardFields = document.getElementById('credit-card-fields');
            const newTypeWrap = document.getElementById('new-account-type-wrap');
            const newTypeTemplateSelect = document.getElementById('new-account-type-template');

            function getEffectiveTemplate() {
                const val = typeSelect.value;
                if (val === 'credit_card') return 'credit_card';
                if (val === 'new') return newTypeTemplateSelect ? newTypeTemplateSelect.value : 'other';
                if (val.startsWith('custom:')) {
                    const opt = typeSelect.options[typeSelect.selectedIndex];
                    return opt.dataset.template || 'other';
                }
                return val; // savings, current, cash, wallet, other
            }

            function toggleAccountFields() {
                const isNew = typeSelect.value === 'new';
                newTypeWrap.classList.toggle('visible', isNew);

                const template = getEffectiveTemplate();
                const isCreditCard = template === 'credit_card';
                bankFields.style.display = isCreditCard ? 'none' : 'grid';
                cardFields.style.display = isCreditCard ? 'grid' : 'none';
            }

            typeSelect.addEventListener('change', toggleAccountFields);
            if (newTypeTemplateSelect) {
                newTypeTemplateSelect.addEventListener('change', toggleAccountFields);
            }
            toggleAccountFields();
        })();
    </script>
</main>
