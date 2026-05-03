<?php
$activeModule = 'all_transactions';
$accounts = $accounts ?? [];
$categories = $categories ?? [];
$filters = $filters ?? [];
$transactions = $transactions ?? [];
$totalsByType = $totalsByType ?? [];

include __DIR__ . '/../partials/nav.php';

$filterQuery = array_filter([
    'module' => 'all_transactions',
    'account_id' => $filters['account_id'] ?? null,
    'category_id' => $filters['category_id'] ?? null,
    'subcategory_id' => $filters['subcategory_id'] ?? null,
    'start_date' => $filters['start_date'] ?? null,
    'end_date' => $filters['end_date'] ?? null,
], static fn ($value): bool => $value !== null && $value !== '');
$exportQuery = http_build_query(array_merge($filterQuery, ['action' => 'export']));
?>
<main class="module-content">
    <header class="module-header">
        <h1>All Transactions</h1>
        <p>Search, filter and export the full ledger. <a href="?module=transactions">+ Add transaction</a></p>
    </header>

    <section class="summary-cards">
        <?php foreach (['income', 'expense', 'transfer'] as $type): ?>
            <article class="card">
                <h3><?= ucfirst($type) ?></h3>
                <p><?= formatCurrency($totalsByType[$type] ?? 0.00) ?></p>
                <small>Ledger total</small>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="module-panel">
        <h2>Filters</h2>
        <form method="get" class="module-form">
            <input type="hidden" name="module" value="all_transactions">
            <label>
                Account
                <select name="account_id">
                    <option value="">All accounts</option>
                    <?php foreach ($accounts as $account): ?>
                        <?php $accountType = $account['account_type'] ?? 'bank'; ?>
                        <option value="<?= (int) $account['id'] ?>" <?= ($filters['account_id'] ?? null) == $account['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($account['bank_name'] . ' - ' . $account['account_name'] . ' (' . $accountType . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Category
                <select name="category_id" id="filter-category-select">
                    <option value="">All categories</option>
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
            <a class="secondary" href="?module=all_transactions">Reset</a>
            <a class="secondary" href="?<?= htmlspecialchars($exportQuery) ?>">Export CSV</a>
        </form>
    </section>

    <section class="module-panel">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:0.75rem;">
            <h2 style="margin:0;">
                Transactions
                <small class="muted" style="font-size:0.75rem;font-weight:400;margin-left:0.5rem;"><?= count($transactions) ?> result(s)</small>
            </h2>
            <a href="?module=transactions" class="secondary" style="font-size:0.875rem;padding:0.4rem 1rem;">+ Add transaction</a>
        </div>
        <?php if (empty($transactions)): ?>
            <p class="muted">No transactions found for the selected filters.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Account</th>
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
                        <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td><?= htmlspecialchars($txn['transaction_date']) ?></td>
                                <td><?= htmlspecialchars($txn['account_display'] ?? '-') ?></td>
                                <td>
                                    <?php
                                        $isRefund = ($txn['reference_type'] ?? '') === 'refund';
                                        $pillClass = $isRefund ? 'yellow' : ($txn['transaction_type'] === 'income' ? 'green' : ($txn['transaction_type'] === 'expense' ? 'red' : 'blue'));
                                        $pillLabel = $isRefund ? 'Refund' : ucfirst($txn['transaction_type']);
                                    ?>
                                    <span class="pill pill--<?= $pillClass ?>"><?= htmlspecialchars($pillLabel) ?></span>
                                </td>
                                <td><?= formatCurrency((float) $txn['amount']) ?></td>
                                <td><?= htmlspecialchars($txn['payment_method_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($txn['contact_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($txn['purchase_source_name'] ?? '-') ?></td>
                                <td>
                                    <?= htmlspecialchars($txn['category_name'] ?? 'Uncategorized') ?>
                                    <?php if (!empty($txn['subcategory_name'])): ?>
                                        <small class="muted">-> <?= htmlspecialchars($txn['subcategory_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($txn['notes'] ?? '') ?></td>
                                <td style="white-space:nowrap;">
                                    <a class="secondary" href="?module=transactions&edit=<?= (int) $txn['id'] ?>">Edit</a>
                                    <?php if ($txn['transaction_type'] === 'expense'): ?>
                                        <button type="button" class="secondary refund-btn"
                                            style="font-size:0.75rem;padding:0.2rem 0.6rem;margin-left:0.25rem;"
                                            data-id="<?= (int) $txn['id'] ?>"
                                            data-amount="<?= (float) $txn['amount'] ?>"
                                            data-label="<?= htmlspecialchars(($txn['category_name'] ?? 'Expense') . ' · ' . number_format((float) $txn['amount'], 2)) ?>"
                                            data-account="<?= htmlspecialchars($txn['account_display'] ?? '') ?>">
                                            Refund
                                        </button>
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
            const filterCategorySelect = document.getElementById('filter-category-select');
            const filterSubcategorySelect = document.getElementById('filter-subcategory-select');
            const preselectedSub = <?= json_encode($filters['subcategory_id'] ?? '') ?>;

            const storedFilterOptions = Array.from(
                filterSubcategorySelect.querySelectorAll('option[data-category]')
            ).map(option => ({
                value: option.value,
                label: option.innerHTML,
                category: option.dataset.category,
            }));

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
                if (preselectedSub) {
                    filterSubcategorySelect.value = preselectedSub;
                }
            }

            filterCategorySelect.addEventListener('change', refreshFilterSubcategories);
            refreshFilterSubcategories();
        })();
    </script>

    <div id="refund-modal" style="display:none;position:fixed;inset:0;z-index:1000;
         background:rgba(0,0,0,0.55);align-items:center;justify-content:center;">
        <div style="background:var(--panel);border-radius:var(--radius);padding:1.5rem;
                    max-width:380px;width:100%;box-shadow:var(--shadow);">
            <h3 style="margin:0 0 0.25rem;">Refund</h3>
            <p id="refund-desc" style="color:var(--muted);font-size:0.88rem;margin:0 0 1rem;"></p>
            <div style="display:flex;gap:0.75rem;margin-bottom:1rem;flex-wrap:wrap;">
                <button type="button" id="refund-full-btn" class="secondary" style="flex:1;">Full amount</button>
                <input type="number" id="refund-custom-amt" step="0.01" min="0.01"
                       placeholder="Custom amount" style="flex:1;min-width:120px;">
            </div>
            <div style="display:flex;gap:0.5rem;">
                <button type="button" id="refund-confirm-btn">Confirm Refund</button>
                <button type="button" id="refund-cancel-btn" class="secondary">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var modal      = document.getElementById('refund-modal');
            var desc       = document.getElementById('refund-desc');
            var fullBtn    = document.getElementById('refund-full-btn');
            var customAmt  = document.getElementById('refund-custom-amt');
            var confirmBtn = document.getElementById('refund-confirm-btn');
            var cancelBtn  = document.getElementById('refund-cancel-btn');
            var currentId  = null, currentAmt = 0;

            document.querySelectorAll('.refund-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    currentId  = btn.dataset.id;
                    currentAmt = parseFloat(btn.dataset.amount);
                    desc.textContent = btn.dataset.label + (btn.dataset.account ? ' · ' + btn.dataset.account : '');
                    customAmt.value  = '';
                    fullBtn.textContent = 'Full amount (' + currentAmt.toFixed(2) + ')';
                    modal.style.display = 'flex';
                });
            });

            fullBtn.addEventListener('click', function () { customAmt.value = currentAmt; });
            cancelBtn.addEventListener('click', function () { modal.style.display = 'none'; });
            modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });

            confirmBtn.addEventListener('click', function () {
                var amt = parseFloat(customAmt.value);
                if (!amt || amt <= 0) { alert('Enter a refund amount.'); return; }
                var fd = new FormData();
                fd.append('form', 'refund');
                fd.append('refund_of_id', currentId);
                fd.append('amount', amt);
                fetch('?module=all_transactions', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) { modal.style.display = 'none'; location.reload(); }
                        else { alert(data.error || 'Failed to record refund.'); }
                    });
            });
        })();
    </script>
</main>
