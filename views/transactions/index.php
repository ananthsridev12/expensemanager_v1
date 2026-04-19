<?php
$activeModule = 'transactions';
$accounts = $accounts ?? [];
$loans = $loans ?? [];
$categories = $categories ?? [];
$recentTransactions = $recentTransactions ?? [];
$totalsByType = $totalsByType ?? [];
$imported = $imported ?? null;
$failed = $failed ?? null;
$paymentMethods = $paymentMethods ?? [];
$purchaseChildren = $purchaseChildren ?? [];
$creditCards = $creditCards ?? [];
$filters = $filters ?? [];
$editTransaction = $editTransaction ?? null;

// Build grouped account list (used in Filters, From account, To account, Redeem points)
$acctTypeOrder = ['savings' => 'Savings', 'current' => 'Current', 'credit_card' => 'Credit Cards', 'cash' => 'Cash', 'wallet' => 'Wallets', 'other' => 'Other'];
$acctGrouped = [];
foreach ($accounts as $acct) {
    $sysKey   = $acct['account_type_system_key'] ?? null;
    $typeId   = $acct['account_type_id'] ?? null;
    $isCustom = ($sysKey === null || $sysKey === '') && !empty($typeId);
    $gKey     = $isCustom ? 'custom_' . (int) $typeId : ($acct['account_type'] ?? 'other');
    $acctGrouped[$gKey][] = $acct;
}
$acctGroups = [];
foreach ($acctTypeOrder as $typeKey => $typeLabel) {
    if (!empty($acctGrouped[$typeKey])) {
        $acctGroups[] = ['label' => $typeLabel, 'accounts' => $acctGrouped[$typeKey]];
    }
}
foreach ($acctGrouped as $gKey => $accts) {
    if (!isset($acctTypeOrder[$gKey])) {
        $first = $accts[0];
        $label = !empty($first['account_type_name']) ? $first['account_type_name'] : ucfirst(str_replace('_', ' ', $gKey));
        $acctGroups[] = ['label' => $label, 'accounts' => $accts];
    }
}

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>Transactions</h1>
        <p>Every money movement hits the master ledger with immutable history.</p>
    </header>

    <?php if ($imported !== null || $failed !== null): ?>
        <section class="module-panel">
            <strong>Import result:</strong>
            <span class="muted">Imported <?= (int) ($imported ?? 0) ?> row(s), failed <?= (int) ($failed ?? 0) ?> row(s).</span>
        </section>
    <?php endif; ?>

    <section class="summary-cards">
        <?php foreach (['income', 'expense', 'transfer'] as $type): ?>
            <article class="card">
                <h3><?= ucfirst($type) ?></h3>
                <p><?= formatCurrency($totalsByType[$type] ?? 0.00) ?></p>
                <small>Ledger total</small>
            </article>
        <?php endforeach; ?>
    </section>

    <?php
    $filterQuery = array_filter([
        'module' => 'transactions',
        'account_id' => $filters['account_id'] ?? null,
        'category_id' => $filters['category_id'] ?? null,
        'subcategory_id' => $filters['subcategory_id'] ?? null,
        'start_date' => $filters['start_date'] ?? null,
        'end_date' => $filters['end_date'] ?? null,
    ], static fn ($value): bool => $value !== null && $value !== '');
    $exportQuery = http_build_query(array_merge($filterQuery, ['action' => 'export']));
    ?>

    <section class="module-panel">
        <h2>Filters</h2>
        <form method="get" class="module-form">
            <input type="hidden" name="module" value="transactions">
            <label>
                Account
                <select name="account_id">
                    <option value="">All accounts</option>
                    <?php foreach ($acctGroups as $grp): ?>
                        <optgroup label="<?= htmlspecialchars($grp['label']) ?>">
                            <?php foreach ($grp['accounts'] as $account): ?>
                                <option value="<?= (int) $account['id'] ?>" <?= ($filters['account_id'] ?? null) == $account['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($account['bank_name'] . ' - ' . $account['account_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Category
                <select name="category_id" id="filter-category-select">
                    <option value="">All categories</option>
                    <option value="uncategorized" <?= ($filters['category_id'] ?? null) === 'uncategorized' ? 'selected' : '' ?>>— Uncategorized</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= ($filters['category_id'] ?? null) == $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Subcategory
                <select name="subcategory_id" id="filter-subcategory-select">
                    <option value="">All subcategories</option>
                    <option value="unspecified" <?= ($filters['subcategory_id'] ?? null) === 'unspecified' ? 'selected' : '' ?>>— Unspecified</option>
                    <?php foreach ($categories as $category): ?>
                        <?php foreach ($category['subcategories'] as $sub): ?>
                            <option value="<?= $sub['id'] ?>" data-category="<?= $category['id'] ?>" <?= ($filters['subcategory_id'] ?? null) == $sub['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name'] . ' - ' . $sub['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Start date
                <input type="date" name="start_date" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>">
            </label>
            <label>
                End date
                <input type="date" name="end_date" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>">
            </label>
            <button type="submit">Apply filters</button>
            <a class="secondary" href="?module=transactions">Reset</a>
            <a class="secondary" href="?<?= htmlspecialchars($exportQuery) ?>">Export CSV</a>
        </form>
    </section>

    <?php if ($editTransaction): ?>
    <section class="module-panel">
        <h2>Edit transaction</h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="transaction_update">
            <input type="hidden" name="id" value="<?= (int) $editTransaction['id'] ?>">
            <label>
                Date
                <input type="date" name="transaction_date" value="<?= htmlspecialchars($editTransaction['transaction_date'] ?? '') ?>" required>
            </label>
            <label>
                Amount
                <input type="number" name="amount" step="0.01" min="0" required value="<?= htmlspecialchars($editTransaction['amount'] ?? '') ?>">
            </label>
            <label>
                Category
                <select name="category_id" id="edit-category-select">
                    <option value="">Uncategorized</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= ($editTransaction['category_id'] ?? null) == $category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name']) ?> (<?= $category['type'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Subcategory
                <select name="subcategory_id" id="edit-subcategory-select">
                    <option value="">None</option>
                    <?php foreach ($categories as $category): ?>
                        <?php foreach ($category['subcategories'] as $sub): ?>
                            <option value="<?= $sub['id'] ?>" data-category="<?= $category['id'] ?>" <?= ($editTransaction['subcategory_id'] ?? null) == $sub['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name'] . ' - ' . $sub['name']) ?></option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Payment method
                <select name="payment_method_id">
                    <option value="">Select method</option>
                    <?php foreach ($paymentMethods as $method): ?>
                        <option value="<?= (int) $method['id'] ?>" <?= ($editTransaction['payment_method_id'] ?? null) == $method['id'] ? 'selected' : '' ?>><?= htmlspecialchars($method['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Notes
                <textarea name="notes" rows="2"><?= htmlspecialchars($editTransaction['notes'] ?? '') ?></textarea>
            </label>
            <button type="submit">Update transaction</button>
            <a class="secondary" href="?module=transactions">Cancel</a>
        </form>
    </section>
    <?php endif; ?>

    <section class="module-panel">
        <h2>Add transaction</h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="transaction">
            <label>
                Date
                <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>
                From account
                <select name="account_id" id="from-account-select" required>
                    <?php foreach ($acctGroups as $grp): ?>
                        <optgroup label="<?= htmlspecialchars($grp['label']) ?>">
                            <?php foreach ($grp['accounts'] as $account): ?>
                                <?php
                                $isDefault = !empty($account['is_default']);
                                $aLabel = $account['bank_name'] . ' - ' . $account['account_name'];
                                ?>
                                <option value="<?= $account['account_type'] . ':' . $account['id'] ?>"
                                    data-type="<?= htmlspecialchars($account['account_type']) ?>"
                                    data-account-id="<?= (int) $account['id'] ?>"
                                    <?= $isDefault ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($aLabel) ?><?= $isDefault ? ' ★' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                    <?php if (!empty($loans)): ?>
                        <optgroup label="Loans">
                            <?php foreach ($loans as $loan): ?>
                                <option value="loan:<?= (int) $loan['id'] ?>" data-type="loan" data-account-id="">
                                    <?= htmlspecialchars($loan['loan_name'] ?? 'Loan #' . $loan['id']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </label>
            <div style="display:flex;justify-content:flex-end;align-items:center;gap:0.5rem;grid-column:1/-1;">
                <button type="button" class="secondary" id="set-default-btn" style="font-size:0.8rem;padding:0.25rem 0.6rem;" title="Pre-select this account every time">★ Set as default</button>
                <span id="set-default-msg" class="muted" style="font-size:0.8rem;margin-left:0.5rem;align-self:center;display:none;">Saved</span>
            </div>
            <label>
                Transaction type
                <select name="transaction_type" id="transaction-type">
                    <option value="income">Income</option>
                    <option value="expense" selected>Expense</option>
                    <option value="transfer">Transfer</option>
                </select>
            </label>
            <label>
                Amount
                <input type="number" name="amount" step="0.01" min="0" required>
            </label>
            <label>
                Group spend?
                <select name="group_spend" id="group-spend-toggle">
                    <option value="no" selected>No</option>
                    <option value="yes">Yes (split)</option>
                </select>
            </label>
            <label id="group-share-wrap" style="display: none;">
                Your share
                <input type="number" name="group_share_amount" step="0.01" min="0" placeholder="Example: 150">
                <small class="muted">The remainder is tracked as receivable in Lending.</small>
            </label>
            <label>
                Payment method
                <select name="payment_method_id" id="payment-method-select">
                    <option value="">Select method</option>
                    <?php foreach ($paymentMethods as $method): ?>
                        <option value="<?= (int) $method['id'] ?>"><?= htmlspecialchars($method['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="other">Other (add new)</option>
                </select>
            </label>
            <label id="new-payment-method-wrap" style="display: none;">
                New payment method
                <input type="text" name="new_payment_method" placeholder="Example: UPI Lite">
            </label>
            <label>
                Category
                <select name="category_id" id="category-select">
                    <option value="">Uncategorized</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?> (<?= $category['type'] ?>)</option>
                    <?php endforeach; ?>
                    <option value="new_category">+ New category</option>
                </select>
            </label>
            <div id="new-category-wrap" class="inline-sub-form">
                <label>
                    Category name
                    <input type="text" name="new_category_name" placeholder="e.g. Groceries">
                </label>
                <label>
                    Type
                    <select name="new_category_type">
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                        <option value="transfer">Transfer</option>
                    </select>
                </label>
            </div>
            <label>
                Subcategory
                <select name="subcategory_id" id="subcategory-select">
                    <option value="">None</option>
                    <?php foreach ($categories as $category): ?>
                        <?php foreach ($category['subcategories'] as $sub): ?>
                            <option value="<?= $sub['id'] ?>" data-category="<?= $category['id'] ?>"><?= htmlspecialchars($category['name'] . ' - ' . $sub['name']) ?></option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <option value="new_subcategory">+ New subcategory</option>
                </select>
            </label>
            <label id="new-subcategory-wrap" style="display:none;grid-column:1/-1;">
                Subcategory name
                <input type="text" name="new_subcategory_name" placeholder="e.g. Vegetables">
            </label>
            <label>
                To whom (Contact)
                <input type="text" id="transaction-contact-search" placeholder="Type name/mobile/email" autocomplete="off">
                <input type="hidden" name="contact_id" id="transaction-contact-id">
                <small class="muted">For group spend, this contact is used for the receivable entry.</small>
            </label>
            <div class="module-placeholder" id="transaction-contact-results">
                <small class="muted">Start typing to search contacts.</small>
            </div>
            <label>
                Purchased from
                <select name="purchase_source_id" id="purchase-source-select">
                    <option value="">Select source</option>
                    <?php foreach ($purchaseChildren as $source): ?>
                        <option value="<?= (int) $source['id'] ?>" data-parent="<?= (int) $source['parent_id'] ?>">
                            <?= htmlspecialchars($source['name']) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="other">Other source (add new)</option>
                </select>
            </label>
            <label id="new-purchase-source-wrap" style="display: none;">
                New purchase source
                <input type="text" name="new_purchase_source" placeholder="Example: Local Tea Stall">
            </label>
            <label id="emi-toggle-wrap" style="display: none;">
                EMI purchase?
                <select name="is_emi_purchase" id="is-emi-purchase">
                    <option value="no" selected>No</option>
                    <option value="yes">Yes</option>
                </select>
            </label>
            <div id="emi-fields" style="display: none;">
                <div class="module-form">
                    <label>
                        EMI name
                        <input type="text" name="emi_name" placeholder="Phone EMI">
                    </label>
                    <label>
                        Interest rate (% p.a.)
                        <input type="number" name="interest_rate" step="0.01" min="0" value="0">
                    </label>
                    <label>
                        Total EMIs
                        <input type="number" name="total_emis" min="1" value="1">
                    </label>
                    <label>
                        EMI start date
                        <input type="date" name="emi_date">
                    </label>
                    <label>
                        Processing fee
                        <input type="number" name="processing_fee" step="0.01" min="0" value="0">
                    </label>
                    <label>
                        GST rate (%)
                        <input type="number" name="gst_rate" step="0.01" min="0" value="18">
                    </label>
                </div>
            </div>
            <div id="transfer-options" style="display: none;">
                <div class="module-form">
                    <label>
                        Transfer to
                        <select name="transfer_target" id="transfer-target">
                            <option value="account">Account / Loan</option>
                            <option value="lending">Lending (lend / repayment)</option>
                            <option value="rental">Rental (record rent)</option>
                            <option value="investment">Investment</option>
                        </select>
                    </label>
                </div>

                <!-- Account / Loan sub-panel -->
                <div class="module-form" id="transfer-account-panel">
                    <label>
                        To account
                        <select name="transfer_to_account_id">
                            <option value="">Select target account</option>
                            <?php foreach ($acctGroups as $grp): ?>
                                <optgroup label="<?= htmlspecialchars($grp['label']) ?>">
                                    <?php foreach ($grp['accounts'] as $account): ?>
                                        <option value="<?= $account['account_type'] . ':' . $account['id'] ?>">
                                            <?= htmlspecialchars($account['bank_name'] . ' - ' . $account['account_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                            <?php if (!empty($loans)): ?>
                                <optgroup label="Loans">
                                    <?php foreach ($loans as $loan): ?>
                                        <option value="loan:<?= (int) $loan['id'] ?>"><?= htmlspecialchars('Loan: ' . ($loan['loan_name'] ?? 'Loan #' . $loan['id'])) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                    </label>
                </div>

                <!-- Lending sub-panel -->
                <div id="transfer-lending-panel" style="display: none;">
                    <div class="module-form">
                        <label>
                            Mode
                            <select name="lending_mode" id="lending-mode">
                                <option value="new">New lending record</option>
                                <option value="topup">Top-up existing record</option>
                                <option value="repayment">Repayment from contact</option>
                            </select>
                        </label>
                    </div>
                    <div class="module-form" id="lending-new-fields">
                        <small class="muted">Contact, amount, and date are taken from the fields above.</small>
                        <label>
                            Interest rate (% p.a.)
                            <input type="number" name="lending_interest_rate" step="0.01" min="0" value="0">
                        </label>
                        <label>
                            Due date (optional)
                            <input type="date" name="lending_due_date">
                        </label>
                    </div>
                    <div class="module-form" id="lending-repayment-fields" style="display: none;">
                        <small class="muted">Amount is taken from the field above.</small>
                        <label>
                            Lending record
                            <select name="lending_record_id">
                                <option value="">Select lending record</option>
                                <?php foreach ($openLendingRecords as $record): ?>
                                    <option value="<?= (int) $record['id'] ?>">
                                        <?= htmlspecialchars($record['contact_name']) ?> — Outstanding: <?= formatCurrency((float) $record['outstanding_amount']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="module-form" id="lending-topup-fields" style="display: none;">
                        <small class="muted">Adds the amount above to the selected record's principal.</small>
                        <label>
                            Lending record
                            <select name="lending_record_id">
                                <option value="">Select lending record</option>
                                <?php foreach ($openLendingRecords as $record): ?>
                                    <option value="<?= (int) $record['id'] ?>">
                                        <?= htmlspecialchars($record['contact_name']) ?> — Outstanding: <?= formatCurrency((float) $record['outstanding_amount']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="module-form">
                        <label>
                            Notes
                            <textarea name="lending_notes" rows="2"></textarea>
                        </label>
                    </div>
                </div>

                <!-- Rental sub-panel -->
                <div id="transfer-rental-panel" style="display: none;">
                    <div class="module-form">
                        <label>
                            Mode
                            <select name="rental_mode" id="rental-mode">
                                <option value="existing">Existing contract</option>
                                <option value="new_contract">New contract</option>
                            </select>
                        </label>
                    </div>
                    <div class="module-form" id="rental-existing-fields">
                        <label>
                            Contract
                            <select name="rental_contract_id">
                                <option value="">Select contract</option>
                                <?php foreach ($rentalContracts as $contract): ?>
                                    <option value="<?= (int) $contract['id'] ?>">
                                        <?= htmlspecialchars(($contract['property_name'] ?? 'Property') . ' — ' . ($contract['tenant_name'] ?? 'Tenant')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="module-form" id="rental-new-fields" style="display: none;">
                        <label>
                            Property
                            <select name="rental_property_id">
                                <option value="">Select property</option>
                                <?php foreach ($rentalProperties as $property): ?>
                                    <option value="<?= (int) $property['id'] ?>"><?= htmlspecialchars($property['property_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Tenant
                            <select name="rental_tenant_id">
                                <option value="">Select tenant</option>
                                <?php foreach ($rentalTenants as $tenant): ?>
                                    <option value="<?= (int) $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Contract start date
                            <input type="date" name="rental_contract_start" value="<?= date('Y-m-d') ?>">
                        </label>
                        <label>
                            Contract end date
                            <input type="date" name="rental_contract_end">
                        </label>
                        <label>
                            Monthly rent amount
                            <input type="number" name="rental_contract_rent" step="0.01" min="0">
                        </label>
                        <label>
                            Security deposit
                            <input type="number" name="rental_contract_deposit" step="0.01" min="0" value="0">
                        </label>
                    </div>
                    <div class="module-form">
                        <small class="muted">Amount is taken from the field above.</small>
                        <label>
                            Rent month
                            <input type="month" name="rental_rent_month" value="<?= date('Y-m') ?>">
                        </label>
                        <label>
                            Due date
                            <input type="date" name="rental_due_date" value="<?= date('Y-m-d') ?>">
                        </label>
                        <label>
                            Payment status
                            <select name="rental_status">
                                <option value="paid">Paid</option>
                                <option value="partial">Partial</option>
                                <option value="pending">Pending</option>
                            </select>
                        </label>
                        <label>
                            Notes
                            <textarea name="rental_notes" rows="2"></textarea>
                        </label>
                    </div>
                </div>

                <!-- Investment sub-panel -->
                <div id="transfer-investment-panel" style="display: none;">
                    <div class="module-form">
                        <label>
                            Mode
                            <select name="investment_mode" id="investment-mode">
                                <option value="existing">Existing investment</option>
                                <option value="new">New investment</option>
                            </select>
                        </label>
                    </div>
                    <div class="module-form" id="investment-existing-fields">
                        <label>
                            Investment
                            <select name="investment_id">
                                <option value="">Select investment</option>
                                <?php foreach ($investments as $inv): ?>
                                    <option value="<?= (int) $inv['id'] ?>"><?= htmlspecialchars($inv['name'] . ' (' . $inv['type'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="module-form" id="investment-new-fields" style="display: none;">
                        <label>
                            Type
                            <select name="investment_type">
                                <option value="mutual_fund">Mutual Fund</option>
                                <option value="equity">Equity / Stocks</option>
                                <option value="fd">Fixed Deposit (FD)</option>
                                <option value="rd">Recurring Deposit (RD)</option>
                                <option value="nps">NPS</option>
                                <option value="ppf">PPF</option>
                                <option value="other">Other</option>
                            </select>
                        </label>
                        <label>
                            Investment name
                            <input type="text" name="investment_name" placeholder="e.g. HDFC Top 100 Fund">
                        </label>
                        <label>
                            Investment notes (optional)
                            <input type="text" name="investment_notes" placeholder="Optional">
                        </label>
                    </div>
                    <div class="module-form">
                        <small class="muted">Amount and date are taken from the fields above.</small>
                        <label>
                            Transaction type
                            <select name="investment_tx_type">
                                <option value="buy">Buy / Deposit</option>
                                <option value="sell">Sell / Withdraw</option>
                                <option value="dividend">Dividend / Interest</option>
                            </select>
                        </label>
                        <label>
                            Units (optional)
                            <input type="number" name="investment_units" step="0.0001" min="0">
                        </label>
                        <label>
                            Notes
                            <textarea name="investment_tx_notes" rows="2"></textarea>
                        </label>
                    </div>
                </div>
            </div>
            <label>
                Reference type
                <input type="text" name="reference_type">
            </label>
            <label>
                Reference ID
                <input type="number" name="reference_id" min="0">
            </label>
            <label>
                Notes
                <textarea name="notes" rows="2"></textarea>
            </label>
            <button type="submit">Record transaction</button>
        </form>
    </section>

    <section class="module-panel">
        <h2>Redeem credit card points</h2>
        <?php if (empty($creditCards)): ?>
            <p class="muted">No credit cards found. Add a credit card account to track points.</p>
        <?php else: ?>
            <form method="post" class="module-form">
                <input type="hidden" name="form" value="reward_redemption">
                <label>
                    Redemption date
                    <input type="date" name="redemption_date" value="<?= date('Y-m-d') ?>" required>
                </label>
                <label>
                    Credit card
                    <select name="credit_card_id" id="reward-card-select" required>
                        <?php foreach ($creditCards as $card): ?>
                            <option value="<?= (int) $card['id'] ?>" data-points="<?= (float) ($card['points_balance'] ?? 0) ?>">
                                <?= htmlspecialchars(($card['bank_name'] ?? '') . ' - ' . ($card['card_name'] ?? 'Card')) ?>
                                (Points: <?= number_format((float) ($card['points_balance'] ?? 0), 2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Points redeemed
                    <input type="number" name="points_redeemed" id="reward-points" step="0.01" min="0" required>
                </label>
                <label>
                    Rate per point
                    <input type="number" name="rate_per_point" id="reward-rate" step="0.0001" min="0" value="0.25" required>
                </label>
                <label>
                    Cash value
                    <input type="number" name="cash_value" id="reward-cash" step="0.01" min="0" readonly>
                </label>
                <label>
                    Deposit to account
                    <select name="deposit_account_id" required>
                        <option value="">Select account</option>
                        <?php foreach ($acctGroups as $grp): ?>
                            <?php
                            // Exclude credit card accounts — points can't deposit to a CC
                            $depositAccounts = array_filter($grp['accounts'], fn($a) => ($a['account_type'] ?? '') !== 'credit_card');
                            if (empty($depositAccounts)) continue;
                            ?>
                            <optgroup label="<?= htmlspecialchars($grp['label']) ?>">
                                <?php foreach ($depositAccounts as $account): ?>
                                    <option value="<?= $account['account_type'] . ':' . $account['id'] ?>">
                                        <?= htmlspecialchars($account['bank_name'] . ' - ' . $account['account_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Category (optional)
                    <select name="category_id" id="reward-category-select">
                        <option value="">Uncategorized</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?> (<?= $category['type'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Subcategory (optional)
                    <select name="subcategory_id" id="reward-subcategory-select">
                        <option value="">None</option>
                        <?php foreach ($categories as $category): ?>
                            <?php foreach ($category['subcategories'] as $sub): ?>
                                <option value="<?= $sub['id'] ?>" data-category="<?= $category['id'] ?>"><?= htmlspecialchars($category['name'] . ' - ' . $sub['name']) ?></option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Notes
                    <textarea name="notes" rows="2"></textarea>
                </label>
                <button type="submit">Redeem points</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Import transactions (CSV)</h2>
        <p class="muted">
            Upload CSV with header columns:
            <code>transaction_date,account_token,transaction_type,amount,category_id,subcategory_id,payment_method_id,payment_method_name,contact_id,purchase_source_id,purchase_source_name,notes,transfer_to_account_token</code>.
            Use account token format like <code>savings:1</code>, <code>credit_card:3</code>, <code>loan:2</code>.
        </p>
        <p class="muted">
            For transfer rows, fill <code>transfer_to_account_token</code>. You can also use account_id/account_type columns instead of account_token.
        </p>
        <p class="muted">
            Download sample:
            <a href="public/templates/transactions_import_template.csv" target="_blank" rel="noopener">transactions_import_template.csv</a>
        </p>
        <form method="post" enctype="multipart/form-data" class="module-form">
            <input type="hidden" name="form" value="transaction_import">
            <label>
                CSV file
                <input type="file" name="transaction_file" accept=".csv,text/csv" required>
            </label>
            <button type="submit">Import CSV</button>
        </form>
    </section>

    <section class="module-panel">
        <h2>Transactions</h2>
        <?php if (empty($recentTransactions)): ?>
            <p class="muted">No transactions found for the selected filters.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Bank</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>To whom</th>
                            <th>Purchased from</th>
                            <th>Category</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTransactions as $txn): ?>
                            <tr>
                                <td><?= htmlspecialchars($txn['transaction_date']) ?></td>
                                <td><?= htmlspecialchars($txn['account_display'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(ucfirst($txn['transaction_type'])) ?></td>
                                <td><?= formatCurrency((float) $txn['amount']) ?></td>
                                <td><?= htmlspecialchars($txn['payment_method_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($txn['contact_name'] ?? '-') ?></td>
                                <td>
                                    <?= htmlspecialchars($txn['purchase_source_name'] ?? '-') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($txn['category_name'] ?? 'Uncategorized') ?>
                                    <?php if (!empty($txn['subcategory_name'])): ?>
                                        <small class="muted">-> <?= htmlspecialchars($txn['subcategory_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($txn['notes'] ?? '') ?></td>
                                <td style="white-space:nowrap;">
                                    <?php if (in_array($txn['reference_type'] ?? '', ['fuel_surcharge', 'fuel_surcharge_refund'], true)): ?>
                                        <span class="pill card--orange" style="font-size:0.7rem;">Auto</span>
                                    <?php else: ?>
                                        <a class="secondary" href="?module=transactions&edit=<?= (int) $txn['id'] ?>">Edit</a>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this transaction and any linked surcharge entries?')">
                                            <input type="hidden" name="form" value="transaction_delete">
                                            <input type="hidden" name="id" value="<?= (int) $txn['id'] ?>">
                                            <button type="submit" class="secondary" style="color:var(--red);">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <script>
        (function () {
            const typeSelect = document.getElementById('transaction-type');
            const accountSelect = document.getElementById('from-account-select');
            const setDefaultBtn = document.getElementById('set-default-btn');
            const setDefaultMsg = document.getElementById('set-default-msg');

            function syncDefaultAccountBtn() {
                const opt = accountSelect.options[accountSelect.selectedIndex];
                const accountId = opt ? opt.dataset.accountId : '';
                if (setDefaultBtn) setDefaultBtn.disabled = !accountId;
            }

            if (setDefaultBtn) {
                setDefaultBtn.addEventListener('click', function () {
                    const opt = accountSelect.options[accountSelect.selectedIndex];
                    const accountId = opt ? opt.dataset.accountId : '';
                    if (!accountId) return;

                    const fd = new FormData();
                    fd.append('form', 'set_default_account');
                    fd.append('account_id', accountId);
                    fetch('?module=transactions', { method: 'POST', body: fd }).then(() => {
                        // Update ★ in all options
                        Array.from(accountSelect.options).forEach(o => {
                            o.textContent = o.textContent.replace(/\s★$/, '');
                        });
                        opt.textContent = opt.textContent.trimEnd() + ' ★';
                        if (setDefaultMsg) {
                            setDefaultMsg.style.display = 'inline';
                            setTimeout(() => { setDefaultMsg.style.display = 'none'; }, 2000);
                        }
                    });
                });
            }

            accountSelect.addEventListener('change', syncDefaultAccountBtn);
            syncDefaultAccountBtn();
            const transferPanel = document.getElementById('transfer-options');
            const transferTargetSelect = document.getElementById('transfer-target');
            const transferAccountPanel = document.getElementById('transfer-account-panel');
            const transferLendingPanel = document.getElementById('transfer-lending-panel');
            const transferRentalPanel = document.getElementById('transfer-rental-panel');
            const transferInvestmentPanel = document.getElementById('transfer-investment-panel');
            const lendingModeSelect = document.getElementById('lending-mode');
            const lendingNewFields = document.getElementById('lending-new-fields');
            const lendingRepaymentFields = document.getElementById('lending-repayment-fields');
            const rentalModeSelect = document.getElementById('rental-mode');
            const rentalExistingFields = document.getElementById('rental-existing-fields');
            const rentalNewFields = document.getElementById('rental-new-fields');
            const investmentModeSelect = document.getElementById('investment-mode');
            const investmentExistingFields = document.getElementById('investment-existing-fields');
            const investmentNewFields = document.getElementById('investment-new-fields');
            const emiToggleWrap = document.getElementById('emi-toggle-wrap');
            const emiToggleSelect = document.getElementById('is-emi-purchase');
            const emiFields = document.getElementById('emi-fields');
            const categorySelect = document.getElementById('category-select');
            const subcategorySelect = document.getElementById('subcategory-select');
            const newCategoryWrap = document.getElementById('new-category-wrap');
            const newSubcategoryWrap = document.getElementById('new-subcategory-wrap');
            const paymentMethodSelect = document.getElementById('payment-method-select');
            const newPaymentMethodWrap = document.getElementById('new-payment-method-wrap');
            const purchaseSourceSelect = document.getElementById('purchase-source-select');
            const newPurchaseSourceWrap = document.getElementById('new-purchase-source-wrap');
            const groupSpendToggle = document.getElementById('group-spend-toggle');
            const groupShareWrap = document.getElementById('group-share-wrap');
            const contactSearchInput = document.getElementById('transaction-contact-search');
            const contactIdInput = document.getElementById('transaction-contact-id');
            const contactResultsWrap = document.getElementById('transaction-contact-results');
            const filterCategorySelect = document.getElementById('filter-category-select');
            const filterSubcategorySelect = document.getElementById('filter-subcategory-select');
            const rewardCategorySelect = document.getElementById('reward-category-select');
            const rewardSubcategorySelect = document.getElementById('reward-subcategory-select');
            const rewardPointsInput = document.getElementById('reward-points');
            const rewardRateInput = document.getElementById('reward-rate');
            const rewardCashInput = document.getElementById('reward-cash');

            const storedOptions = Array.from(subcategorySelect.querySelectorAll('option[data-category]')).map(option => ({
                value: option.value,
                label: option.innerHTML,
                category: option.dataset.category,
            }));
            const storedFilterOptions = Array.from(filterSubcategorySelect.querySelectorAll('option[data-category]')).map(option => ({
                value: option.value,
                label: option.innerHTML,
                category: option.dataset.category,
            }));
            const storedRewardOptions = rewardSubcategorySelect
                ? Array.from(rewardSubcategorySelect.querySelectorAll('option[data-category]')).map(option => ({
                    value: option.value,
                    label: option.innerHTML,
                    category: option.dataset.category,
                }))
                : [];

            function toggleTransferFields() {
                const isTransfer = typeSelect.value === 'transfer';
                transferPanel.style.display = isTransfer ? 'block' : 'none';
                if (isTransfer) {
                    toggleTransferTarget();
                }
            }

            function toggleTransferTarget() {
                const target = transferTargetSelect ? transferTargetSelect.value : 'account';
                transferAccountPanel.style.display = target === 'account' ? 'grid' : 'none';
                transferLendingPanel.style.display = target === 'lending' ? 'block' : 'none';
                transferRentalPanel.style.display = target === 'rental' ? 'block' : 'none';
                transferInvestmentPanel.style.display = target === 'investment' ? 'block' : 'none';
            }

            const lendingTopupFields = document.getElementById('lending-topup-fields');

            function toggleLendingMode() {
                const mode = lendingModeSelect ? lendingModeSelect.value : 'new';
                lendingNewFields.style.display        = mode === 'new'       ? 'grid' : 'none';
                lendingRepaymentFields.style.display  = mode === 'repayment' ? 'grid' : 'none';
                if (lendingTopupFields) lendingTopupFields.style.display = mode === 'topup' ? 'grid' : 'none';
            }

            function toggleRentalMode() {
                const mode = rentalModeSelect ? rentalModeSelect.value : 'existing';
                rentalExistingFields.style.display = mode === 'existing' ? 'grid' : 'none';
                rentalNewFields.style.display = mode === 'new_contract' ? 'grid' : 'none';
            }

            function toggleInvestmentMode() {
                const mode = investmentModeSelect ? investmentModeSelect.value : 'existing';
                investmentExistingFields.style.display = mode === 'existing' ? 'grid' : 'none';
                investmentNewFields.style.display = mode === 'new' ? 'grid' : 'none';
            }

            function toggleEmiFields() {
                const selectedOption = accountSelect.options[accountSelect.selectedIndex];
                const isCard = selectedOption && selectedOption.dataset.type === 'credit_card';
                const isExpense = typeSelect.value === 'expense';
                const eligible = isCard && isExpense;
                emiToggleWrap.style.display = eligible ? 'flex' : 'none';

                if (!eligible) {
                    emiToggleSelect.value = 'no';
                    emiFields.style.display = 'none';
                    return;
                }

                emiFields.style.display = emiToggleSelect.value === 'yes' ? 'block' : 'none';
            }

            function toggleCategoryOther() {
                const isNew = categorySelect.value === 'new_category';
                newCategoryWrap.classList.toggle('visible', isNew);
                categorySelect.name = isNew ? '' : 'category_id';
                refreshSubcategories();
            }

            function toggleSubcategoryOther() {
                const isNew = subcategorySelect.value === 'new_subcategory';
                newSubcategoryWrap.style.display = isNew ? 'flex' : 'none';
                subcategorySelect.name = isNew ? '' : 'subcategory_id';
            }

            function refreshSubcategories() {
                const selectedCategory = categorySelect.value;
                const isNewCategory = selectedCategory === 'new_category';
                subcategorySelect.innerHTML = '<option value="">None</option>';

                if (!isNewCategory) {
                    storedOptions.forEach(item => {
                        if (!selectedCategory || item.category === selectedCategory) {
                            const option = document.createElement('option');
                            option.value = item.value;
                            option.innerHTML = item.label;
                            option.dataset.category = item.category;
                            subcategorySelect.appendChild(option);
                        }
                    });
                }

                const newOpt = document.createElement('option');
                newOpt.value = 'new_subcategory';
                newOpt.textContent = '+ New subcategory';
                subcategorySelect.appendChild(newOpt);

                // Reset subcategory new wrap if category changed
                newSubcategoryWrap.style.display = 'none';
                subcategorySelect.name = 'subcategory_id';
            }

            function refreshFilterSubcategories() {
                const selectedCategory = filterCategorySelect.value;
                filterSubcategorySelect.innerHTML = '<option value="">All subcategories</option>';

                storedFilterOptions.forEach(item => {
                    if (!selectedCategory || item.category === selectedCategory) {
                        const option = document.createElement('option');
                        option.value = item.value;
                        option.innerHTML = item.label;
                        option.dataset.category = item.category;
                        filterSubcategorySelect.appendChild(option);
                    }
                });
            }

            function refreshRewardSubcategories() {
                if (!rewardCategorySelect || !rewardSubcategorySelect) {
                    return;
                }
                const selectedCategory = rewardCategorySelect.value;
                rewardSubcategorySelect.innerHTML = '<option value="">None</option>';

                storedRewardOptions.forEach(item => {
                    if (!selectedCategory || item.category === selectedCategory) {
                        const option = document.createElement('option');
                        option.value = item.value;
                        option.innerHTML = item.label;
                        option.dataset.category = item.category;
                        rewardSubcategorySelect.appendChild(option);
                    }
                });
            }

            function updateRewardCash() {
                if (!rewardPointsInput || !rewardRateInput || !rewardCashInput) {
                    return;
                }
                const points = parseFloat(rewardPointsInput.value || '0');
                const rate = parseFloat(rewardRateInput.value || '0');
                const cash = points * rate;
                rewardCashInput.value = cash > 0 ? cash.toFixed(2) : '';
            }

            function togglePaymentMethodOther() {
                newPaymentMethodWrap.style.display = paymentMethodSelect.value === 'other' ? 'flex' : 'none';
                if (paymentMethodSelect.value === 'other') {
                    paymentMethodSelect.name = '';
                    newPaymentMethodWrap.querySelector('input').focus();
                    return;
                }
                paymentMethodSelect.name = 'payment_method_id';
            }

            function togglePurchaseOther() {
                newPurchaseSourceWrap.style.display = purchaseSourceSelect.value === 'other' ? 'flex' : 'none';
                if (purchaseSourceSelect.value === 'other') {
                    purchaseSourceSelect.name = '';
                } else {
                    purchaseSourceSelect.name = 'purchase_source_id';
                }
            }

            function toggleGroupSpendFields() {
                const isExpense = typeSelect.value === 'expense';
                if (!isExpense) {
                    groupSpendToggle.value = 'no';
                    groupShareWrap.style.display = 'none';
                    return;
                }
                groupShareWrap.style.display = groupSpendToggle.value === 'yes' ? 'flex' : 'none';
            }

            function renderContactResultsFor(items, searchInput, idInput, resultsWrap) {
                if (!items.length) {
                    resultsWrap.innerHTML = '<small class="muted">No contacts found.</small>';
                    return;
                }
                resultsWrap.innerHTML = '';
                items.forEach(item => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'secondary';
                    button.style.marginRight = '0.5rem';
                    button.style.marginBottom = '0.5rem';
                    button.textContent = item.name + (item.mobile ? ' - ' + item.mobile : '');
                    button.addEventListener('click', function () {
                        idInput.value = item.id;
                        searchInput.value = item.name + (item.mobile ? ' - ' + item.mobile : '');
                        resultsWrap.innerHTML = '<small class="muted">Selected: ' + button.textContent + '</small>';
                    });
                    resultsWrap.appendChild(button);
                });
            }

            async function searchContactsFor(query, searchInput, idInput, resultsWrap) {
                const response = await fetch('?module=transactions&action=contact_search&q=' + encodeURIComponent(query));
                if (!response.ok) { return; }
                const data = await response.json();
                renderContactResultsFor(Array.isArray(data) ? data : [], searchInput, idInput, resultsWrap);
            }

            if (transferTargetSelect) {
                transferTargetSelect.addEventListener('change', toggleTransferTarget);
            }
            if (lendingModeSelect) {
                lendingModeSelect.addEventListener('change', toggleLendingMode);
            }
            if (rentalModeSelect) {
                rentalModeSelect.addEventListener('change', toggleRentalMode);
            }
            if (investmentModeSelect) {
                investmentModeSelect.addEventListener('change', toggleInvestmentMode);
            }
            typeSelect.addEventListener('change', toggleTransferFields);
            typeSelect.addEventListener('change', toggleEmiFields);
            typeSelect.addEventListener('change', toggleGroupSpendFields);
            accountSelect.addEventListener('change', toggleEmiFields);
            emiToggleSelect.addEventListener('change', toggleEmiFields);
            categorySelect.addEventListener('change', toggleCategoryOther);
            subcategorySelect.addEventListener('change', toggleSubcategoryOther);
            filterCategorySelect.addEventListener('change', refreshFilterSubcategories);
            if (rewardCategorySelect) {
                rewardCategorySelect.addEventListener('change', refreshRewardSubcategories);
            }
            paymentMethodSelect.addEventListener('change', togglePaymentMethodOther);
            purchaseSourceSelect.addEventListener('change', togglePurchaseOther);
            groupSpendToggle.addEventListener('change', toggleGroupSpendFields);
            if (rewardPointsInput) {
                rewardPointsInput.addEventListener('input', updateRewardCash);
            }
            if (rewardRateInput) {
                rewardRateInput.addEventListener('input', updateRewardCash);
            }
            contactSearchInput.addEventListener('input', function () {
                const query = contactSearchInput.value.trim();
                if (query.length < 2) {
                    contactIdInput.value = '';
                    contactResultsWrap.innerHTML = '<small class="muted">Start typing to search contacts.</small>';
                    return;
                }
                searchContactsFor(query, contactSearchInput, contactIdInput, contactResultsWrap);
            });

            toggleTransferFields();
            toggleTransferTarget();
            toggleLendingMode();
            toggleRentalMode();
            toggleInvestmentMode();
            toggleEmiFields();
            toggleGroupSpendFields();
            toggleCategoryOther();
            toggleSubcategoryOther();
            refreshFilterSubcategories();
            refreshRewardSubcategories();
            togglePaymentMethodOther();
            togglePurchaseOther();
            updateRewardCash();
        })();
    </script>
</main>
