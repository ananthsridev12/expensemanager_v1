<?php
$activeModule = 'dashboard';
$summary = $summary ?? ['accounts' => ['count' => 0, 'total_balance' => 0], 'categories' => 0, 'transactions' => 0, 'reminders' => 0, 'loans' => ['count' => 0, 'principal' => 0], 'credit_cards' => ['count' => 0, 'total_limit' => 0, 'total_outstanding' => 0], 'lending' => ['count' => 0, 'outstanding' => 0], 'investments' => ['count' => 0], 'rentals' => ['contracts' => 0]];
$accounts = $accounts ?? [];
$creditCards = $creditCards ?? [];
$creditCardStatements = $creditCardStatements ?? [];
$creditCardEmiPlans = $creditCardEmiPlans ?? [];
$creditCardEmiSchedule = $creditCardEmiSchedule ?? [];
$categories = $categories ?? [];
$totalsByType = $totalsByType ?? [];
$recentTransactions = $recentTransactions ?? [];
$upcomingReminders = $upcomingReminders ?? [];
$upcomingEmis = $upcomingEmis ?? [];
$monthComparison = $monthComparison ?? [];
$sparkline = $sparkline ?? [];

$fmtPct = function (?float $pct, bool $invertColor = false): string {
    if ($pct === null) return '';
    $up = $pct >= 0;
    $color = $invertColor ? ($up ? 'var(--red)' : 'var(--green)') : ($up ? 'var(--green)' : 'var(--red)');
    $arrow = $up ? '▲' : '▼';
    return ' <span style="font-size:0.78rem;color:' . $color . '">' . $arrow . ' ' . abs($pct) . '%</span>';
};

include __DIR__ . '/partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>Personal Finance Manager</h1>
        <p>Ledger-driven system ready to grow with bank accounts, loans, investments, and rental income.</p>
    </header>

    <section class="summary-cards">
        <article class="card card--green">
            <h3>Bank balance</h3>
            <p><?= formatCurrency($summary['accounts']['total_balance']) ?></p>
            <small><?= $summary['accounts']['count'] ?> accounts</small>
        </article>
        <article class="card card--red">
            <h3>Loan outstanding</h3>
            <p><?= formatCurrency($summary['loans']['outstanding']) ?></p>
            <small><?= $summary['loans']['count'] ?> loans</small>
        </article>
        <article class="card card--orange">
            <h3>Credit cards</h3>
            <p><?= formatCurrency($summary['credit_cards']['total_outstanding']) ?></p>
            <small>Limit <?= formatCurrency($summary['credit_cards']['total_limit']) ?></small>
        </article>
        <article class="card card--cyan">
            <h3>Lending out</h3>
            <p><?= formatCurrency($summary['lending']['outstanding']) ?></p>
            <small><?= $summary['lending']['count'] ?> records</small>
        </article>
        <article class="card card--purple">
            <h3>Investments</h3>
            <p><?= $summary['investments']['count'] ?> items</p>
            <small>Tracked portfolios</small>
        </article>
        <article class="card card--yellow">
            <h3>Rental</h3>
            <p><?= $summary['rentals']['contracts'] ?> contracts</p>
            <small><?= $summary['rentals']['properties'] ?> properties</small>
        </article>
        <article class="card">
            <h3>Transactions</h3>
            <p><?= number_format($summary['transactions']) ?></p>
            <small>Ledger entries</small>
        </article>
        <article class="card">
            <h3>Reminders</h3>
            <p><?= $summary['reminders'] ?></p>
            <small>Upcoming bills/EMIs</small>
        </article>
    </section>

    <!-- This month at a glance -->
    <?php if (!empty($monthComparison)): ?>
    <section class="module-panel">
        <h2>This month at a glance</h2>
        <div class="summary-cards" style="margin-bottom:1rem;">
            <article class="card card--green">
                <h3>Income <?= $fmtPct($monthComparison['income_pct'] ?? null) ?></h3>
                <p><?= formatCurrency((float)($monthComparison['this_income'] ?? 0)) ?></p>
                <small>Last month <?= formatCurrency((float)($monthComparison['last_income'] ?? 0)) ?></small>
            </article>
            <article class="card card--red">
                <h3>Expense <?= $fmtPct($monthComparison['expense_pct'] ?? null, true) ?></h3>
                <p><?= formatCurrency((float)($monthComparison['this_expense'] ?? 0)) ?></p>
                <small>Last month <?= formatCurrency((float)($monthComparison['last_expense'] ?? 0)) ?></small>
            </article>
            <?php $net = (float)($monthComparison['this_net'] ?? 0); ?>
            <article class="card <?= $net >= 0 ? 'card--cyan' : 'card--orange' ?>">
                <h3>Net cashflow</h3>
                <p><?= formatCurrency($net) ?></p>
                <small>Income minus expense</small>
            </article>
        </div>
        <?php if (!empty($sparkline)): ?>
        <div style="max-width:520px;">
            <p style="font-size:0.78rem;color:var(--muted);margin-bottom:0.4rem;text-transform:uppercase;letter-spacing:.05em;">6-month income vs expense</p>
            <canvas id="dashboard-sparkline" height="80"></canvas>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        (function () {
            const rows = <?= json_encode($sparkline, JSON_UNESCAPED_UNICODE) ?>;
            new Chart(document.getElementById('dashboard-sparkline'), {
                type: 'bar',
                data: {
                    labels: rows.map(r => r.period),
                    datasets: [
                        { label: 'Income',  data: rows.map(r => Number(r.income)),  backgroundColor: 'rgba(34,197,94,0.7)',  borderRadius: 3 },
                        { label: 'Expense', data: rows.map(r => Number(r.expense)), backgroundColor: 'rgba(244,63,94,0.7)', borderRadius: 3 }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { labels: { color: '#94a3b8', boxWidth: 10, font: { size: 11 } } },
                        tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 }) } }
                    },
                    scales: {
                        x: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.05)' } },
                        y: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.05)' } }
                    }
                }
            });
        })();
        </script>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php
    $dashTypeOrder = ['savings' => 'Savings', 'current' => 'Current', 'credit_card' => 'Credit Cards', 'cash' => 'Cash', 'wallet' => 'Wallets', 'other' => 'Other'];
    $dashGrouped = [];
    foreach ($accounts as $account) {
        $sysKey   = $account['account_type_system_key'] ?? null;
        $typeId   = $account['account_type_id'] ?? null;
        $isCustom = ($sysKey === null || $sysKey === '') && !empty($typeId);
        $groupKey = $isCustom ? 'custom_' . (int) $typeId : ($account['account_type'] ?? 'other');
        $dashGrouped[$groupKey][] = $account;
    }
    $dashOrdered = [];
    foreach ($dashTypeOrder as $typeKey => $typeLabel) {
        if (!empty($dashGrouped[$typeKey])) {
            $dashOrdered[$typeKey] = ['label' => $typeLabel, 'template' => $typeKey, 'accounts' => $dashGrouped[$typeKey]];
        }
    }
    foreach ($dashGrouped as $groupKey => $accs) {
        if (!isset($dashOrdered[$groupKey])) {
            $first    = $accs[0];
            $label    = !empty($first['account_type_name']) ? $first['account_type_name'] : ucfirst(str_replace('_', ' ', $groupKey));
            $template = $first['account_type'] ?? 'other';
            $dashOrdered[$groupKey] = ['label' => $label, 'template' => $template, 'accounts' => $accs];
        }
    }
    ?>

    <?php foreach ($dashOrdered as $typeKey => $group): ?>
    <section class="module-panel">
        <h2><?= htmlspecialchars($group['label']) ?></h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Bank</th>
                        <th>Name</th>
                        <?php if ($group['template'] === 'credit_card'): ?>
                            <th>Outstanding</th>
                            <th>Limit</th>
                            <th>Available</th>
                        <?php else: ?>
                            <th>Balance</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($group['accounts'] as $account): ?>
                        <tr>
                            <td><?= htmlspecialchars($account['bank_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($account['account_name'] ?? '—') ?></td>
                            <?php if ($group['template'] === 'credit_card'): ?>
                                <?php
                                $ccOut  = (float) ($account['live_cc_outstanding'] ?? $account['outstanding_balance'] ?? 0);
                                $ccLim  = (float) ($account['credit_limit'] ?? 0);
                                $ccAvail = max(0, $ccLim - $ccOut);
                                ?>
                                <td><?= formatCurrency($ccOut) ?></td>
                                <td><?= formatCurrency($ccLim) ?></td>
                                <td><?= formatCurrency($ccAvail) ?></td>
                            <?php else: ?>
                                <td><?= formatCurrency((float) ($account['balance'] ?? $account['opening_balance'] ?? 0)) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (count($group['accounts']) > 1): ?>
                <tfoot>
                    <?php if ($group['template'] === 'credit_card'): ?>
                        <?php
                        $tOut   = array_sum(array_map(fn($a) => (float)($a['live_cc_outstanding'] ?? $a['outstanding_balance'] ?? 0), $group['accounts']));
                        $tLim   = array_sum(array_map(fn($a) => (float)($a['credit_limit'] ?? 0), $group['accounts']));
                        $tAvail = max(0, $tLim - $tOut);
                        ?>
                        <tr>
                            <td colspan="2"><strong>Total</strong></td>
                            <td><strong><?= formatCurrency($tOut) ?></strong></td>
                            <td><strong><?= formatCurrency($tLim) ?></strong></td>
                            <td><strong><?= formatCurrency($tAvail) ?></strong></td>
                        </tr>
                    <?php else: ?>
                        <?php $tBal = array_sum(array_map(fn($a) => (float)($a['balance'] ?? $a['opening_balance'] ?? 0), $group['accounts'])); ?>
                        <tr>
                            <td colspan="2"><strong>Total</strong></td>
                            <td><strong><?= formatCurrency($tBal) ?></strong></td>
                        </tr>
                    <?php endif; ?>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </section>
    <?php endforeach; ?>

    <section class="module-panel">
        <h2>Record a transaction</h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="transaction">
            <label>
                Date
                <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>
                From account
                <select name="account_id" id="from-account" required>
                    <?php foreach ($accounts as $account): ?>
                        <?php if (($account['account_type'] ?? '') === 'credit_card') { continue; } ?>
                        <option value="<?= htmlspecialchars(($account['account_type'] ?? 'savings') . ':' . $account['id']) ?>" data-type="<?= htmlspecialchars($account['account_type'] ?? 'savings') ?>"><?= htmlspecialchars($account['bank_name'] . ' - ' . $account['account_name']) ?></option>
                    <?php endforeach; ?>
                    <?php foreach ($creditCards as $card): ?>
                        <?php if (empty($card['account_id'])) { continue; } ?>
                        <option value="credit_card:<?= (int) $card['account_id'] ?>" data-type="credit_card"><?= htmlspecialchars($card['bank_name'] . ' - ' . $card['card_name'] . ' (Card)') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
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
                Category
                <select name="category_id" id="category-select">
                    <option value="">Uncategorized</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name'] . ' (' . $category['type'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Subcategory
                <select name="subcategory_id" id="subcategory-select">
                    <option value="">None</option>
                    <?php foreach ($categories as $category): ?>
                        <?php foreach ($category['subcategories'] as $sub): ?>
                            <option value="<?= $sub['id'] ?>" data-category="<?= $category['id'] ?>"><?= htmlspecialchars($category['name'] . ' — ' . $sub['name']) ?></option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </label>
            <div id="transfer-panel" style="display: none;">
                <label>
                    To account
                    <select name="transfer_account_id">
                        <option value="">Select account</option>
                        <?php foreach ($accounts as $account): ?>
                            <?php if (($account['account_type'] ?? '') === 'credit_card') { continue; } ?>
                            <option value="<?= htmlspecialchars(($account['account_type'] ?? 'savings') . ':' . $account['id']) ?>"><?= htmlspecialchars($account['bank_name'] . ' - ' . $account['account_name']) ?></option>
                        <?php endforeach; ?>
                        <?php foreach ($creditCards as $card): ?>
                            <?php if (empty($card['account_id'])) { continue; } ?>
                            <option value="credit_card:<?= (int) $card['account_id'] ?>"><?= htmlspecialchars($card['bank_name'] . ' - ' . $card['card_name'] . ' (Card)') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>
                Notes
                <textarea name="notes" rows="2"></textarea>
            </label>
            <button type="submit">Save transaction</button>
        </form>
    </section>
    <section class="module-panel">
        <h2>EMI plan tracker</h2>
        <?php if (empty($creditCardEmiPlans)): ?>
            <p class="muted">No EMI plans yet. Add each pending EMI as a separate plan.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Card</th>
                            <th>Plan</th>
                            <th>Outstanding principal</th>
                            <th>Next due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($creditCardEmiPlans as $plan): ?>
                            <tr>
                                <td><?= htmlspecialchars(($plan['bank_name'] ?? '') . ' - ' . ($plan['card_name'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($plan['plan_name'] ?? '') ?></td>
                                <td><?= formatCurrency((float) ($plan['outstanding_principal'] ?? 0)) ?></td>
                                <td><?= htmlspecialchars($plan['next_due_date'] ?? '-') ?></td>
                                <td><span class="pill"><?= htmlspecialchars(ucfirst((string) ($plan['status'] ?? 'active'))) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Credit card billing snapshot</h2>
        <?php if (empty($creditCardStatements)): ?>
            <p class="muted">No credit-card statements yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Card</th>
                            <th>Cycle</th>
                            <th>Non-EMI spend</th>
                            <th>EMI due in cycle</th>
                            <th>Estimated statement due</th>
                            <th>Total outstanding</th>
                            <th>Available limit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($creditCardStatements as $statement): ?>
                            <tr>
                                <td><?= htmlspecialchars(($statement['bank_name'] ?? '') . ' - ' . ($statement['card_name'] ?? '')) ?></td>
                                <td><?= htmlspecialchars(($statement['cycle_start'] ?? '') . ' to ' . ($statement['cycle_end'] ?? '')) ?></td>
                                <td><?= formatCurrency((float) ($statement['statement_spend_non_emi'] ?? 0)) ?></td>
                                <td><?= formatCurrency((float) ($statement['statement_emi_due'] ?? 0)) ?></td>
                                <td><?= formatCurrency((float) ($statement['statement_total_due'] ?? 0)) ?></td>
                                <td><?= formatCurrency((float) ($statement['outstanding_balance'] ?? 0)) ?></td>
                                <td><?= formatCurrency((float) ($statement['available_limit'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>EMI schedule</h2>
        <?php if (empty($creditCardEmiSchedule)): ?>
            <p class="muted">No EMI schedule yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Card</th>
                            <th>Plan</th>
                            <th>Installment</th>
                            <th>Due date</th>
                            <th>Principal</th>
                            <th>Interest</th>
                            <th>Fee + GST</th>
                            <th>Total due</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($creditCardEmiSchedule as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars(($row['bank_name'] ?? '') . ' - ' . ($row['card_name'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($row['plan_name'] ?? '') ?></td>
                                <td>#<?= (int) ($row['installment_no'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($row['due_date'] ?? '-') ?></td>
                                <td><?= formatCurrency((float) ($row['principal_component'] ?? 0)) ?></td>
                                <td><?= formatCurrency((float) ($row['interest_component'] ?? 0)) ?></td>
                                <td><?= formatCurrency((float) (($row['processing_fee'] ?? 0) + ($row['gst_amount'] ?? 0))) ?></td>
                                <td><?= formatCurrency((float) ($row['total_due'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Recent transactions</h2>
        <?php if (empty($recentTransactions)): ?>
            <p class="muted">No transactions yet. Create income, expense, or transfer entries to start building the ledger.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Category</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTransactions as $txn): ?>
                            <tr>
                                <td><?= htmlspecialchars($txn['transaction_date']) ?></td>
                                <td><?= htmlspecialchars(ucfirst($txn['transaction_type'])) ?></td>
                                <td><?= formatCurrency((float) $txn['amount']) ?></td>
                                <td>
                                    <?= htmlspecialchars($txn['category_name'] ?? 'Uncategorized') ?>
                                    <?php if (!empty($txn['subcategory_name'])): ?>
                                        <small class="muted">(<?= htmlspecialchars($txn['subcategory_name']) ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($txn['notes'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Upcoming reminders</h2>
        <?php if (empty($upcomingReminders)): ?>
            <p class="muted">No reminders scheduled.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Due date</th>
                            <th>Frequency</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingReminders as $reminder): ?>
                            <tr>
                                <td><?= htmlspecialchars($reminder['name']) ?></td>
                                <td><?= htmlspecialchars($reminder['next_due_date']) ?></td>
                                <td><?= htmlspecialchars($reminder['frequency']) ?></td>
                                <td><?= formatCurrency((float) $reminder['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Upcoming EMIs</h2>
        <?php if (empty($upcomingEmis)): ?>
            <p class="muted">Loan EMIs will appear here once loans are created.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Loan</th>
                            <th>Due date</th>
                            <th>Principal</th>
                            <th>Interest</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingEmis as $emi): ?>
                            <tr>
                                <td><?= htmlspecialchars($emi['loan_name']) ?></td>
                                <td><?= htmlspecialchars($emi['emi_date']) ?></td>
                                <td><?= formatCurrency((float) $emi['principal_component']) ?></td>
                                <td><?= formatCurrency((float) $emi['interest_component']) ?></td>
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
            const transferPanel = document.getElementById('transfer-panel');
            const categorySelect = document.getElementById('category-select');
            const subcategorySelect = document.getElementById('subcategory-select');

            const storedOptions = Array.from(subcategorySelect.querySelectorAll('option[data-category]')).map(option => ({
                value: option.value,
                label: option.innerHTML,
                category: option.dataset.category,
            }));

            function toggleTransferFields() {
                transferPanel.style.display = typeSelect.value === 'transfer' ? 'block' : 'none';
            }

            function refreshSubcategories() {
                const selectedCategory = categorySelect.value;
                subcategorySelect.innerHTML = '<option value="">None</option>';

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

            typeSelect.addEventListener('change', toggleTransferFields);
            categorySelect.addEventListener('change', refreshSubcategories);

            toggleTransferFields();
            refreshSubcategories();
        })();
    </script>
</main>




