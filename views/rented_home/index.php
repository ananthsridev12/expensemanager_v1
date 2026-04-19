<?php
$activeModule   = 'rented_home';
$homes          = $homes          ?? [];
$activeHomes    = $activeHomes    ?? [];
$recentExpenses = $recentExpenses ?? [];
$accounts       = $accounts       ?? [];
$summary        = $summary        ?? ['total_homes' => 0, 'active_homes' => 0, 'monthly_committed' => 0, 'advance_committed' => 0];
$editHome       = $editHome       ?? null;

// Build grouped account list
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

$expenseLabels = [
    'advance'     => 'Advance / Deposit',
    'rent'        => 'Monthly Rent',
    'maintenance' => 'Maintenance',
    'electricity' => 'Electricity Bill',
    'other'       => 'Other',
];
$expenseBadgeColors = [
    'advance'     => '#6366f1',
    'rent'        => '#3b82f6',
    'maintenance' => '#f97316',
    'electricity' => '#eab308',
    'other'       => '#64748b',
];

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>My Rented Home</h1>
        <p>Track houses you rent — advance, monthly rent, maintenance and electricity bills.</p>
    </header>

    <section class="summary-cards">
        <article class="card">
            <h3>Active homes</h3>
            <p><?= $summary['active_homes'] ?></p>
            <small>of <?= $summary['total_homes'] ?> total</small>
        </article>
        <article class="card card--red">
            <h3>Monthly committed</h3>
            <p><?= formatCurrency($summary['monthly_committed']) ?></p>
            <small>Rent across active homes</small>
        </article>
        <article class="card card--orange">
            <h3>Total advance given</h3>
            <p><?= formatCurrency($summary['advance_committed']) ?></p>
            <small>Expected on active homes</small>
        </article>
    </section>

    <!-- Add / Edit home -->
    <section class="module-panel">
        <h2><?= $editHome ? 'Edit home' : 'Add a rented home' ?></h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="<?= $editHome ? 'rented_home_update' : 'rented_home' ?>">
            <?php if ($editHome): ?>
                <input type="hidden" name="id" value="<?= (int) $editHome['id'] ?>">
            <?php endif; ?>
            <label>
                Home label (e.g. Flat 2B, HSR Layout)
                <input type="text" name="label" required placeholder="e.g. Flat 2B, HSR Layout"
                       value="<?= htmlspecialchars($editHome['label'] ?? '') ?>">
            </label>
            <label>
                Landlord name
                <input type="text" name="landlord_name" placeholder="e.g. Mr. Ramesh"
                       value="<?= htmlspecialchars($editHome['landlord_name'] ?? '') ?>">
            </label>
            <label>
                Monthly rent (₹)
                <input type="number" name="monthly_rent" step="0.01" min="0"
                       value="<?= htmlspecialchars($editHome['monthly_rent'] ?? '0') ?>">
            </label>
            <label>
                Expected advance / deposit (₹)
                <input type="number" name="advance_amount" step="0.01" min="0"
                       value="<?= htmlspecialchars($editHome['advance_amount'] ?? '0') ?>">
            </label>
            <label>
                Expected maintenance (₹/month)
                <input type="number" name="maintenance_amount" step="0.01" min="0"
                       value="<?= htmlspecialchars($editHome['maintenance_amount'] ?? '0') ?>">
            </label>
            <label>
                Move-in date
                <input type="date" name="start_date"
                       value="<?= htmlspecialchars($editHome['start_date'] ?? '') ?>">
            </label>
            <label>
                Status
                <select name="status">
                    <option value="active"   <?= ($editHome['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="vacated"  <?= ($editHome['status'] ?? '') === 'vacated'  ? 'selected' : '' ?>>Vacated</option>
                </select>
            </label>
            <label style="grid-column:1/-1;">
                Address
                <textarea name="address" rows="2" placeholder="Full address"><?= htmlspecialchars($editHome['address'] ?? '') ?></textarea>
            </label>
            <label style="grid-column:1/-1;">
                Notes
                <textarea name="notes" rows="2"><?= htmlspecialchars($editHome['notes'] ?? '') ?></textarea>
            </label>
            <button type="submit"><?= $editHome ? 'Update home' : 'Save home' ?></button>
            <?php if ($editHome): ?>
                <a class="secondary" href="?module=rented_home">Cancel</a>
            <?php endif; ?>
        </form>
    </section>

    <!-- Homes list -->
    <?php if (!empty($homes)): ?>
    <section class="module-panel">
        <h2>Your homes</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Home</th>
                        <th>Landlord</th>
                        <th>Monthly rent</th>
                        <th>Advance</th>
                        <th>Maintenance</th>
                        <th>Since</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($homes as $home): ?>
                    <tr>
                        <td><?= htmlspecialchars($home['label']) ?></td>
                        <td class="muted"><?= htmlspecialchars($home['landlord_name'] ?? '—') ?></td>
                        <td><?= formatCurrency((float)$home['monthly_rent']) ?></td>
                        <td><?= formatCurrency((float)$home['advance_amount']) ?></td>
                        <td><?= formatCurrency((float)$home['maintenance_amount']) ?></td>
                        <td class="muted"><?= $home['start_date'] ? date('M Y', strtotime($home['start_date'])) : '—' ?></td>
                        <td>
                            <span style="font-size:0.78rem;padding:0.15rem 0.5rem;border-radius:20px;background:<?= $home['status'] === 'active' ? 'rgba(34,197,94,0.15)' : 'rgba(100,116,139,0.15)' ?>;color:<?= $home['status'] === 'active' ? '#22c55e' : '#64748b' ?>;">
                                <?= ucfirst($home['status']) ?>
                            </span>
                        </td>
                        <td><a class="secondary" href="?module=rented_home&edit=<?= (int) $home['id'] ?>">Edit</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <!-- Record expense -->
    <section class="module-panel">
        <h2>Record a payment</h2>
        <?php if (empty($activeHomes)): ?>
            <p class="muted">Add a home above to start recording payments.</p>
        <?php else: ?>
        <form method="post" class="module-form" id="rented-expense-form">
            <input type="hidden" name="form" value="rented_home_expense">
            <label>
                Home
                <select name="home_id" id="rh-home-select" required>
                    <option value="">Select home</option>
                    <?php foreach ($activeHomes as $home): ?>
                        <option value="<?= (int) $home['id'] ?>"
                                data-rent="<?= (float) $home['monthly_rent'] ?>"
                                data-maintenance="<?= (float) $home['maintenance_amount'] ?>"
                                data-advance="<?= (float) $home['advance_amount'] ?>">
                            <?= htmlspecialchars($home['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Payment type
                <select name="expense_type" id="rh-type-select" required>
                    <?php foreach ($expenseLabels as $val => $lbl): ?>
                        <option value="<?= $val ?>"><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Amount (₹)
                <input type="number" name="amount" id="rh-amount" step="0.01" min="0.01" required>
            </label>
            <label>
                Date
                <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label id="rh-period-wrap">
                Period (month)
                <input type="month" name="period_month" value="<?= date('Y-m') ?>">
                <small class="muted">Which month this rent / electricity bill covers</small>
            </label>
            <label>
                Paid from account
                <select name="account_token" required>
                    <option value="">Select account</option>
                    <?php foreach ($acctGroups as $grp): ?>
                        <optgroup label="<?= htmlspecialchars($grp['label']) ?>">
                            <?php foreach ($grp['accounts'] as $acct): ?>
                                <option value="<?= $acct['account_type'] . ':' . $acct['id'] ?>"
                                        <?= !empty($acct['is_default']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($acct['bank_name'] . ' - ' . $acct['account_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
            <label style="grid-column:1/-1;">
                Notes
                <input type="text" name="notes" placeholder="Optional note">
            </label>
            <button type="submit">Record payment</button>
        </form>
        <?php endif; ?>
    </section>

    <!-- Recent payments -->
    <?php if (!empty($recentExpenses)): ?>
    <section class="module-panel">
        <h2>Recent payments</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Home</th>
                        <th>Type</th>
                        <th>Period</th>
                        <th>Amount</th>
                        <th>Account</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentExpenses as $exp): ?>
                    <tr>
                        <td><?= htmlspecialchars($exp['expense_date']) ?></td>
                        <td><?= htmlspecialchars($exp['home_label'] ?? '—') ?></td>
                        <td>
                            <span style="font-size:0.78rem;padding:0.15rem 0.5rem;border-radius:20px;background:<?= $expenseBadgeColors[$exp['expense_type']] ?? '#64748b' ?>22;color:<?= $expenseBadgeColors[$exp['expense_type']] ?? '#64748b' ?>;">
                                <?= $expenseLabels[$exp['expense_type']] ?? ucfirst($exp['expense_type']) ?>
                            </span>
                        </td>
                        <td class="muted"><?= $exp['period_month'] ? date('M Y', strtotime($exp['period_month'])) : '—' ?></td>
                        <td><?= formatCurrency((float)$exp['amount']) ?></td>
                        <td class="muted"><?= $exp['account_id'] ? htmlspecialchars(($exp['bank_name'] ?? '') . ' ' . ($exp['account_name'] ?? '')) : '—' ?></td>
                        <td class="muted"><?= htmlspecialchars($exp['notes'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
</main>

<script>
(function () {
    const homeSelect   = document.getElementById('rh-home-select');
    const typeSelect   = document.getElementById('rh-type-select');
    const amountInput  = document.getElementById('rh-amount');
    const periodWrap   = document.getElementById('rh-period-wrap');

    // Pre-fill amount based on home + type selection
    function prefillAmount() {
        const opt = homeSelect.options[homeSelect.selectedIndex];
        const type = typeSelect ? typeSelect.value : '';
        if (!opt || !opt.value) return;
        if (type === 'rent')        amountInput.value = opt.dataset.rent || '';
        else if (type === 'maintenance') amountInput.value = opt.dataset.maintenance || '';
        else if (type === 'advance')     amountInput.value = opt.dataset.advance || '';
        else amountInput.value = '';
    }

    // Show/hide period month for rent and electricity
    function togglePeriod() {
        const type = typeSelect ? typeSelect.value : '';
        if (periodWrap) periodWrap.style.display = (type === 'rent' || type === 'electricity') ? '' : 'none';
    }

    if (homeSelect) homeSelect.addEventListener('change', prefillAmount);
    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            prefillAmount();
            togglePeriod();
        });
    }

    togglePeriod();
})();
</script>
