<?php
$activeModule = 'analytics';
$startDate = $startDate ?? date('Y-m-01');
$endDate = $endDate ?? date('Y-m-d');
$summary = $summary ?? ['total_income' => 0, 'total_expense' => 0, 'total_transfer' => 0, 'net_cashflow' => 0];
$earningsSummary = $earningsSummary ?? ['total_earnings' => 0, 'entries' => 0];
$earningsBySubcategory = $earningsBySubcategory ?? [];
$expensesByCategory = $expensesByCategory ?? [];
$incomeByCategory = $incomeByCategory ?? [];
$monthlyTrend = $monthlyTrend ?? [];
$accountWiseExpense = $accountWiseExpense ?? [];
$dayOfWeekSpend = $dayOfWeekSpend ?? [];

$netCashflow = (float) ($summary['net_cashflow'] ?? 0);
$netClass = $netCashflow >= 0 ? 'card--green' : 'card--red';

$hasChartData = !empty($expensesByCategory) || !empty($incomeByCategory) || !empty($monthlyTrend) || !empty($accountWiseExpense) || !empty($dayOfWeekSpend);

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>Analytics</h1>
    </header>

    <!-- Filter -->
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

    <!-- Summary cards -->
    <section class="summary-cards">
        <article class="card card--green">
            <h3>Total income</h3>
            <p><?= formatCurrency((float) ($summary['total_income'] ?? 0)) ?></p>
            <small>All income entries</small>
        </article>
        <article class="card card--cyan">
            <h3>Actual earnings</h3>
            <p><?= formatCurrency((float) ($earningsSummary['total_earnings'] ?? 0)) ?></p>
            <small><?= (int) ($earningsSummary['entries'] ?? 0) ?> entries</small>
        </article>
        <article class="card card--red">
            <h3>Total expense</h3>
            <p><?= formatCurrency((float) ($summary['total_expense'] ?? 0)) ?></p>
            <small>All expense entries</small>
        </article>
        <article class="card <?= $netClass ?>">
            <h3>Net cashflow</h3>
            <p><?= formatCurrency($netCashflow) ?></p>
            <small>Income minus expense</small>
        </article>
    </section>

    <?php if ($hasChartData): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>

    <!-- Monthly income vs expense bar chart + cashflow line -->
    <?php if (!empty($monthlyTrend)): ?>
    <section class="module-panel">
        <h2>Monthly income vs expense (last 12 months)</h2>
        <div class="charts-2col" style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;align-items:start;">
            <div>
                <h3 style="font-size:0.85rem;color:var(--muted);margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:.05em;">Income vs Expense</h3>
                <canvas id="monthly-bar-chart"></canvas>
            </div>
            <div>
                <h3 style="font-size:0.85rem;color:var(--muted);margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:.05em;">Net cashflow trend</h3>
                <canvas id="cashflow-line-chart"></canvas>
            </div>
        </div>
        <div class="table-wrapper" style="margin-top:1.2rem;">
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Income</th>
                        <th>Expense</th>
                        <th>Net</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyTrend as $row): ?>
                        <?php $net = (float)$row['income'] - (float)$row['expense']; ?>
                        <tr>
                            <td><?= htmlspecialchars($row['period']) ?></td>
                            <td style="color:var(--green)"><?= formatCurrency((float)$row['income']) ?></td>
                            <td style="color:var(--red)"><?= formatCurrency((float)$row['expense']) ?></td>
                            <td style="color:<?= $net >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= formatCurrency($net) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        (function () {
            const rows = <?= json_encode($monthlyTrend, JSON_UNESCAPED_UNICODE) ?>;
            const labels = rows.map(r => r.period);
            const income = rows.map(r => Number(r.income));
            const expense = rows.map(r => Number(r.expense));
            const net = rows.map((r, i) => income[i] - expense[i]);
            const gridColor = 'rgba(255,255,255,0.07)';
            const tickColor = '#94a3b8';
            const chartDefaults = {
                responsive: true,
                plugins: { legend: { labels: { color: '#cbd5e1' } } },
                scales: {
                    x: { ticks: { color: tickColor }, grid: { color: gridColor } },
                    y: { ticks: { color: tickColor }, grid: { color: gridColor } }
                }
            };

            new Chart(document.getElementById('monthly-bar-chart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Income', data: income, backgroundColor: 'rgba(16,185,129,0.75)', borderRadius: 4 },
                        { label: 'Expense', data: expense, backgroundColor: 'rgba(239,68,68,0.75)', borderRadius: 4 }
                    ]
                },
                options: { ...chartDefaults, plugins: { ...chartDefaults.plugins, tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 }) } } } }
            });

            new Chart(document.getElementById('cashflow-line-chart'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Net cashflow',
                        data: net,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,0.1)',
                        pointBackgroundColor: net.map(v => v >= 0 ? '#10b981' : '#ef4444'),
                        fill: true,
                        tension: 0.35
                    }]
                },
                options: {
                    ...chartDefaults,
                    plugins: {
                        ...chartDefaults.plugins,
                        tooltip: { callbacks: { label: ctx => 'Net: ₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 }) } }
                    },
                    scales: {
                        ...chartDefaults.scales,
                        y: {
                            ...chartDefaults.scales.y,
                            ticks: {
                                color: tickColor,
                                callback: v => v >= 0 ? '+₹' + v.toLocaleString('en-IN') : '-₹' + Math.abs(v).toLocaleString('en-IN')
                            }
                        }
                    }
                }
            });
        })();
        </script>
    </section>
    <?php endif; ?>

    <!-- Expense by category donut + Income by category donut -->
    <?php if (!empty($expensesByCategory) || !empty($incomeByCategory)): ?>
    <section class="module-panel">
        <h2>Income &amp; expense breakdown</h2>
        <div class="charts-2col" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;">
            <?php if (!empty($expensesByCategory)): ?>
            <div>
                <h3 style="font-size:0.85rem;color:var(--muted);margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:.05em;">Expense by category</h3>
                <div style="max-width:340px;margin:0 auto;">
                    <canvas id="expense-donut-chart"></canvas>
                </div>
                <div class="table-wrapper" style="margin-top:0.8rem;">
                    <table>
                        <thead><tr><th>Category</th><th>Amount</th></tr></thead>
                        <tbody>
                            <?php foreach ($expensesByCategory as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                                    <td><?= formatCurrency((float)($row['total_amount'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($incomeByCategory)): ?>
            <div>
                <h3 style="font-size:0.85rem;color:var(--muted);margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:.05em;">Income by category</h3>
                <div style="max-width:340px;margin:0 auto;">
                    <canvas id="income-donut-chart"></canvas>
                </div>
                <div class="table-wrapper" style="margin-top:0.8rem;">
                    <table>
                        <thead><tr><th>Category</th><th>Amount</th></tr></thead>
                        <tbody>
                            <?php foreach ($incomeByCategory as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                                    <td><?= formatCurrency((float)($row['total_amount'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <script>
        (function () {
            const colors = ['#3b82f6','#22d3ee','#a855f7','#f97316','#10b981','#eab308','#ec4899','#6366f1','#14b8a6','#ef4444'];
            const donutOptions = (title) => ({
                responsive: true,
                cutout: '55%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#cbd5e1', boxWidth: 12 } },
                    tooltip: { callbacks: { label: ctx => ctx.label + ': ₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 }) } }
                }
            });

            <?php if (!empty($expensesByCategory)): ?>
            (function () {
                const rows = <?= json_encode($expensesByCategory, JSON_UNESCAPED_UNICODE) ?>;
                new Chart(document.getElementById('expense-donut-chart'), {
                    type: 'doughnut',
                    data: {
                        labels: rows.map(r => r.category_name || 'Uncategorized'),
                        datasets: [{ data: rows.map(r => Number(r.total_amount)), backgroundColor: rows.map((_, i) => colors[i % colors.length]), borderColor: '#0f172a', borderWidth: 2 }]
                    },
                    options: donutOptions('Expense by category')
                });
            })();
            <?php endif; ?>

            <?php if (!empty($incomeByCategory)): ?>
            (function () {
                const rows = <?= json_encode($incomeByCategory, JSON_UNESCAPED_UNICODE) ?>;
                new Chart(document.getElementById('income-donut-chart'), {
                    type: 'doughnut',
                    data: {
                        labels: rows.map(r => r.category_name || 'Uncategorized'),
                        datasets: [{ data: rows.map(r => Number(r.total_amount)), backgroundColor: rows.map((_, i) => colors[i % colors.length]), borderColor: '#0f172a', borderWidth: 2 }]
                    },
                    options: donutOptions('Income by category')
                });
            })();
            <?php endif; ?>
        })();
        </script>
    </section>
    <?php endif; ?>

    <!-- Account-wise spending + Day-of-week -->
    <?php if (!empty($accountWiseExpense) || !empty($dayOfWeekSpend)): ?>
    <section class="module-panel">
        <h2>Spending patterns</h2>
        <div class="charts-2col" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;">
            <?php if (!empty($accountWiseExpense)): ?>
            <div>
                <h3 style="font-size:0.85rem;color:var(--muted);margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:.05em;">Expense by account</h3>
                <canvas id="account-expense-chart"></canvas>
                <div class="table-wrapper" style="margin-top:0.8rem;">
                    <table>
                        <thead><tr><th>Account</th><th>Total spent</th></tr></thead>
                        <tbody>
                            <?php foreach ($accountWiseExpense as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['account_label'] ?? '—') ?></td>
                                    <td><?= formatCurrency((float)($row['total_amount'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($dayOfWeekSpend)): ?>
            <div>
                <h3 style="font-size:0.85rem;color:var(--muted);margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:.05em;">Spend by day of week</h3>
                <canvas id="dow-chart"></canvas>
                <div class="table-wrapper" style="margin-top:0.8rem;">
                    <table>
                        <thead><tr><th>Day</th><th>Total spent</th><th>Transactions</th></tr></thead>
                        <tbody>
                            <?php foreach ($dayOfWeekSpend as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['dow_name']) ?></td>
                                    <td><?= formatCurrency((float)$row['total_amount']) ?></td>
                                    <td><?= (int)$row['tx_count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <script>
        (function () {
            const colors = ['#3b82f6','#22d3ee','#a855f7','#f97316','#10b981','#eab308','#ec4899','#6366f1','#14b8a6','#ef4444'];
            const gridColor = 'rgba(255,255,255,0.07)';
            const tickColor = '#94a3b8';

            <?php if (!empty($accountWiseExpense)): ?>
            (function () {
                const rows = <?= json_encode($accountWiseExpense, JSON_UNESCAPED_UNICODE) ?>;
                new Chart(document.getElementById('account-expense-chart'), {
                    type: 'bar',
                    data: {
                        labels: rows.map(r => r.account_label || '—'),
                        datasets: [{
                            label: 'Spent',
                            data: rows.map(r => Number(r.total_amount)),
                            backgroundColor: rows.map((_, i) => colors[i % colors.length]),
                            borderRadius: 4
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: ctx => '₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 }) } }
                        },
                        scales: {
                            x: { ticks: { color: tickColor }, grid: { color: gridColor } },
                            y: { ticks: { color: tickColor }, grid: { color: gridColor } }
                        }
                    }
                });
            })();
            <?php endif; ?>

            <?php if (!empty($dayOfWeekSpend)): ?>
            (function () {
                const rows = <?= json_encode($dayOfWeekSpend, JSON_UNESCAPED_UNICODE) ?>;
                const maxVal = Math.max(...rows.map(r => Number(r.total_amount)));
                new Chart(document.getElementById('dow-chart'), {
                    type: 'bar',
                    data: {
                        labels: rows.map(r => r.dow_name),
                        datasets: [{
                            label: 'Spent',
                            data: rows.map(r => Number(r.total_amount)),
                            backgroundColor: rows.map(r => {
                                const val = Number(r.total_amount);
                                const intensity = maxVal > 0 ? val / maxVal : 0;
                                return `rgba(239,68,68,${0.2 + intensity * 0.75})`;
                            }),
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: ctx => '₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 }) + ' · ' + rows[ctx.dataIndex].tx_count + ' txns' } }
                        },
                        scales: {
                            x: { ticks: { color: tickColor }, grid: { color: gridColor } },
                            y: { ticks: { color: tickColor }, grid: { color: gridColor } }
                        }
                    }
                });
            })();
            <?php endif; ?>
        })();
        </script>
    </section>
    <?php endif; ?>

    <!-- Earnings by source -->
    <?php if (!empty($earningsBySubcategory)): ?>
    <section class="module-panel">
        <h2>Earnings by source</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Source</th><th>Amount</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($earningsBySubcategory as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['subcategory_name'] ?? 'Unspecified') ?></td>
                            <td><?= formatCurrency((float)($row['total_amount'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <?php if (empty($expensesByCategory) && empty($incomeByCategory) && empty($monthlyTrend)): ?>
    <section class="module-panel">
        <p class="muted">No transaction data in selected period.</p>
    </section>
    <?php endif; ?>

</main>
