<?php
$activeModule = 'analytics';
$startDate = $startDate ?? date('Y-m-01');
$endDate = $endDate ?? date('Y-m-d');
$summary = $summary ?? ['total_income' => 0, 'total_expense' => 0, 'total_transfer' => 0, 'net_cashflow' => 0];
$earningsSummary = $earningsSummary ?? ['total_earnings' => 0, 'entries' => 0];
$earningsBySubcategory = $earningsBySubcategory ?? [];
$expensesByCategory = $expensesByCategory ?? [];
$monthlyEarnings = $monthlyEarnings ?? [];

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>Analytics</h1>
        <p>Earnings are calculated only from category <strong>Earnings</strong> (id 1), not from all income entries.</p>
    </header>

    <section class="module-panel">
        <h2>Filter period</h2>
        <form method="get" class="module-form">
            <input type="hidden" name="module" value="analytics">
            <label>
                Start date
                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
            </label>
            <label>
                End date
                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
            </label>
            <button type="submit">Apply</button>
        </form>
    </section>

    <section class="summary-cards">
        <article class="card">
            <h3>Actual earnings</h3>
            <p><?= formatCurrency((float) ($earningsSummary['total_earnings'] ?? 0)) ?></p>
            <small><?= (int) ($earningsSummary['entries'] ?? 0) ?> entries</small>
        </article>
        <article class="card">
            <h3>Total income (all)</h3>
            <p><?= formatCurrency((float) ($summary['total_income'] ?? 0)) ?></p>
        </article>
        <article class="card">
            <h3>Total expense</h3>
            <p><?= formatCurrency((float) ($summary['total_expense'] ?? 0)) ?></p>
        </article>
        <article class="card">
            <h3>Net cashflow</h3>
            <p><?= formatCurrency((float) ($summary['net_cashflow'] ?? 0)) ?></p>
        </article>
    </section>

    <section class="module-panel">
        <h2>Spend analytics (category-wise)</h2>
        <?php if (empty($expensesByCategory)): ?>
            <p class="muted">No expense data in selected period.</p>
        <?php else: ?>
            <div style="max-width: 560px; margin-bottom: 1rem;">
                <canvas id="spend-category-chart"></canvas>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Total spend</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expensesByCategory as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                                <td><?= formatCurrency((float) ($row['total_amount'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Earnings by source (subcategory)</h2>
        <?php if (empty($earningsBySubcategory)): ?>
            <p class="muted">No earnings entries in selected period.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Total amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($earningsBySubcategory as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['subcategory_name'] ?? 'Unspecified') ?></td>
                                <td><?= formatCurrency((float) ($row['total_amount'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Monthly earnings trend (last 12 months)</h2>
        <?php if (empty($monthlyEarnings)): ?>
            <p class="muted">No earnings trend data yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlyEarnings as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['period'] ?? '') ?></td>
                                <td><?= formatCurrency((float) ($row['total_amount'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($expensesByCategory)): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            (function () {
                const rows = <?= json_encode($expensesByCategory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                const labels = rows.map(row => row.category_name || 'Uncategorized');
                const values = rows.map(row => Number(row.total_amount || 0));
                const colors = [
                    '#3b82f6', '#22d3ee', '#a855f7', '#f97316', '#10b981',
                    '#eab308', '#ec4899', '#6366f1', '#14b8a6', '#ef4444'
                ];

                const ctx = document.getElementById('spend-category-chart');
                if (!ctx) {
                    return;
                }

                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: labels.map((_, i) => colors[i % colors.length]),
                            borderColor: '#0f172a',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: '#cbd5f5' }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const value = Number(context.raw || 0);
                                        return context.label + ': INR ' + value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }
                                }
                            }
                        }
                    }
                });
            })();
        </script>
    <?php endif; ?>
</main>
