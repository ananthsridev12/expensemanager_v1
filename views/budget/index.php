<?php
$activeModule   = 'budget';
$categories     = $categories    ?? [];
$threeMonthAvg  = $threeMonthAvg ?? [];
$trendData      = $trendData     ?? [];
$selectedMonth  = $selectedMonth ?? (int) date('n');
$selectedYear   = $selectedYear  ?? (int) date('Y');
$prevMonth      = $prevMonth     ?? ($selectedMonth === 1 ? 12 : $selectedMonth - 1);
$prevYear       = $prevYear      ?? ($selectedMonth === 1 ? $selectedYear - 1 : $selectedYear);
$nextMonth      = $nextMonth     ?? ($selectedMonth === 12 ? 1 : $selectedMonth + 1);
$nextYear       = $nextYear      ?? ($selectedMonth === 12 ? $selectedYear + 1 : $selectedYear);
$totalBudgeted  = $totalBudgeted ?? 0.0;
$totalSpent     = $totalSpent    ?? 0.0;

$monthLabel = date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
$prevLabel  = date('M Y', mktime(0, 0, 0, $prevMonth, 1, $prevYear));

$budgetedCats  = array_filter($categories, fn($c) => (float)$c['budget_amount'] > 0);
$overallPct    = $totalBudgeted > 0 ? round($totalSpent / $totalBudgeted * 100, 1) : 0;
$remaining     = $totalBudgeted - $totalSpent;
$overCount     = count(array_filter($budgetedCats, fn($c) => (float)$c['spent'] >= (float)$c['budget_amount']));
$warnCount     = count(array_filter($budgetedCats, fn($c) => (float)$c['budget_amount'] > 0 && (float)$c['spent'] / (float)$c['budget_amount'] >= 0.8 && (float)$c['spent'] < (float)$c['budget_amount']));

$barColor = function(float $spent, float $amount): string {
    if ($amount <= 0) return '#3b82f6';
    $p = $spent / $amount * 100;
    if ($p >= 100) return '#f43f5e';
    if ($p >= 80)  return '#f59e0b';
    return '#22c55e';
};

include __DIR__ . '/../partials/nav.php';
?>

<main class="module-content">
    <header class="module-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1>Budget</h1>
            <p>Set spending limits for <?= htmlspecialchars($monthLabel) ?>.</p>
        </div>
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <a href="?module=budget&bm=<?= $prevMonth ?>&by=<?= $prevYear ?>"
               style="padding:0.5rem 0.9rem;background:rgba(255,255,255,0.07);border-radius:8px;text-decoration:none;color:var(--muted);">← <?= $prevLabel ?></a>
            <strong style="padding:0.5rem 0.75rem;background:rgba(59,130,246,0.12);border-radius:8px;color:#93c5fd;white-space:nowrap;"><?= htmlspecialchars($monthLabel) ?></strong>
            <a href="?module=budget&bm=<?= $nextMonth ?>&by=<?= $nextYear ?>"
               style="padding:0.5rem 0.9rem;background:rgba(255,255,255,0.07);border-radius:8px;text-decoration:none;color:var(--muted);"><?= date('M Y', mktime(0,0,0,$nextMonth,1,$nextYear)) ?> →</a>
        </div>
    </header>

    <!-- Actions bar -->
    <section class="module-panel" style="padding:0.9rem 1.2rem;">
        <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;">
            <form method="post" style="margin:0;">
                <input type="hidden" name="form"  value="budget_copy_last">
                <input type="hidden" name="month" value="<?= $selectedMonth ?>">
                <input type="hidden" name="year"  value="<?= $selectedYear ?>">
                <button type="submit" style="background:rgba(255,255,255,0.07);color:var(--muted);">
                    Copy from <?= $prevLabel ?>
                </button>
            </form>
            <button type="button" id="btn-apply-avg" style="background:rgba(99,102,241,0.15);color:#a5b4fc;">
                Apply 3-month averages
            </button>
            <button type="button" id="btn-clear-all" style="background:rgba(255,255,255,0.05);color:var(--muted);">
                Clear all
            </button>
            <span style="margin-left:auto;font-size:0.82rem;color:var(--muted);">
                <?= count($budgetedCats) ?> of <?= count($categories) ?> categories budgeted
            </span>
        </div>
    </section>

    <!-- Summary cards -->
    <section class="summary-cards">
        <article class="card card--cyan">
            <h3>Total budgeted</h3>
            <p id="card-total-budgeted"><?= formatCurrency($totalBudgeted) ?></p>
            <small><?= count($budgetedCats) ?> categories</small>
        </article>
        <article class="card <?= $totalSpent > $totalBudgeted && $totalBudgeted > 0 ? 'card--red' : 'card--green' ?>">
            <h3>Spent this month</h3>
            <p><?= formatCurrency($totalSpent) ?></p>
            <small><?= $totalBudgeted > 0 ? $overallPct . '% of budget' : 'No budget set' ?></small>
        </article>
        <article class="card <?= $remaining >= 0 ? 'card--green' : 'card--red' ?>">
            <h3><?= $remaining >= 0 ? 'Remaining' : 'Over budget' ?></h3>
            <p><?= formatCurrency(abs($remaining)) ?></p>
            <small><?= $remaining >= 0 ? 'left to spend' : 'exceeded total' ?></small>
        </article>
        <?php if ($warnCount > 0): ?>
        <article class="card card--orange">
            <h3>Near limit</h3>
            <p><?= $warnCount ?></p>
            <small>categor<?= $warnCount === 1 ? 'y' : 'ies' ?> above 80%</small>
        </article>
        <?php endif; ?>
        <?php if ($overCount > 0): ?>
        <article class="card card--red">
            <h3>Over limit</h3>
            <p><?= $overCount ?></p>
            <small>categor<?= $overCount === 1 ? 'y' : 'ies' ?> exceeded</small>
        </article>
        <?php endif; ?>
    </section>

    <!-- Trend chart -->
    <?php if (count($trendData) >= 2): ?>
    <section class="module-panel">
        <h2>Budget vs Actual — last <?= count($trendData) ?> months</h2>
        <canvas id="trend-chart" height="90"></canvas>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        (function() {
            const rows = <?= json_encode($trendData, JSON_UNESCAPED_UNICODE) ?>;
            const labels = rows.map(r => {
                const d = new Date(r.year, r.month - 1, 1);
                return d.toLocaleString('default', { month: 'short', year: 'numeric' });
            });
            new Chart(document.getElementById('trend-chart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Budgeted', data: rows.map(r => Number(r.total_budgeted)), backgroundColor: 'rgba(99,102,241,0.5)', borderRadius: 4 },
                        { label: 'Actual',   data: rows.map(r => Number(r.total_spent)),    backgroundColor: 'rgba(244,63,94,0.5)',  borderRadius: 4 },
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { labels: { color: '#94a3b8', boxWidth: 12, font: { size: 11 } } },
                        tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 }) } }
                    },
                    scales: {
                        x: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.04)' } },
                        y: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.04)' } }
                    }
                }
            });
        })();
        </script>
    </section>
    <?php endif; ?>

    <!-- Bulk entry table -->
    <form method="post" id="budget-form">
        <input type="hidden" name="form"  value="budget_save_all">
        <input type="hidden" name="month" value="<?= $selectedMonth ?>">
        <input type="hidden" name="year"  value="<?= $selectedYear ?>">

        <section class="module-panel">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem;">
                <h2 style="margin:0;">All categories — <?= htmlspecialchars($monthLabel) ?></h2>
                <div style="display:flex;align-items:center;gap:1rem;">
                    <span style="font-size:0.82rem;color:var(--muted);">
                        Total: <strong id="live-total" style="color:#93c5fd;"><?= formatCurrency($totalBudgeted) ?></strong>
                    </span>
                    <button type="submit">Save budgets</button>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th style="width:160px;">Budget (₹)</th>
                            <th>Spent</th>
                            <th>3-mo avg</th>
                            <th style="min-width:160px;">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $cat):
                        $catId   = (int) $cat['category_id'];
                        $amount  = (float) $cat['budget_amount'];
                        $spent   = (float) $cat['spent'];
                        $avg     = (float) ($threeMonthAvg[$catId] ?? 0);
                        $pct     = $amount > 0 ? min(200, round($spent / $amount * 100, 1)) : 0;
                        $color   = $barColor($spent, $amount);
                        $alert   = '';
                        if ($amount > 0) {
                            if ($pct >= 120)     $alert = '🔴 120%+';
                            elseif ($pct >= 100) $alert = '🔴 Over';
                            elseif ($pct >= 80)  $alert = '🟡 80%+';
                        }
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($cat['category_name']) ?></td>
                            <td>
                                <input
                                    type="number"
                                    class="budget-input"
                                    name="budget[<?= $catId ?>][amount]"
                                    data-category-id="<?= $catId ?>"
                                    data-avg="<?= $avg ?>"
                                    value="<?= $amount > 0 ? number_format($amount, 2, '.', '') : '' ?>"
                                    min="0" step="0.01"
                                    placeholder="—"
                                    style="width:100%;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:6px;padding:0.45rem 0.6rem;color:inherit;font-size:0.9rem;">
                                <input type="hidden" name="budget[<?= $catId ?>][name]" value="<?= htmlspecialchars($cat['category_name']) ?>">
                            </td>
                            <td style="<?= $spent > 0 ? 'color:inherit' : 'color:var(--muted)' ?>">
                                <?= $spent > 0 ? formatCurrency($spent) : '—' ?>
                            </td>
                            <td style="color:var(--muted);font-size:0.85rem;">
                                <?= $avg > 0 ? formatCurrency($avg) : '—' ?>
                            </td>
                            <td>
                                <?php if ($amount > 0): ?>
                                <div style="display:flex;align-items:center;gap:0.4rem;">
                                    <div style="flex:1;background:rgba(255,255,255,0.07);border-radius:5px;height:7px;min-width:80px;">
                                        <div style="background:<?= $color ?>;border-radius:5px;height:7px;width:<?= min(100,$pct) ?>%;max-width:100%;"></div>
                                    </div>
                                    <span style="font-size:0.75rem;color:var(--muted);white-space:nowrap;"><?= $pct ?>%</span>
                                    <?php if ($alert): ?>
                                        <span style="font-size:0.72rem;white-space:nowrap;"><?= $alert ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <span style="color:rgba(255,255,255,0.15);font-size:0.78rem;">No limit</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="border-top:1px solid rgba(255,255,255,0.1);">
                            <td><strong>Total</strong></td>
                            <td><strong id="live-total-foot"><?= formatCurrency($totalBudgeted) ?></strong></td>
                            <td><strong><?= formatCurrency($totalSpent) ?></strong></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div style="margin-top:1rem;display:flex;justify-content:flex-end;gap:0.75rem;">
                <button type="submit">Save budgets for <?= htmlspecialchars($monthLabel) ?></button>
            </div>
        </section>
    </form>

    <!-- Month-end summary (show when viewing a past month with budgets) -->
    <?php
    $isPastMonth = ($selectedYear < (int)date('Y')) || ($selectedYear == (int)date('Y') && $selectedMonth < (int)date('n'));
    if ($isPastMonth && count($budgetedCats) > 0):
        $underBudget = array_filter($budgetedCats, fn($c) => (float)$c['spent'] < (float)$c['budget_amount']);
        $savedTotal  = array_sum(array_map(fn($c) => (float)$c['budget_amount'] - (float)$c['spent'], $underBudget));
    ?>
    <section class="module-panel">
        <h2>Month-end summary — <?= htmlspecialchars($monthLabel) ?></h2>
        <div class="summary-cards">
            <article class="card <?= $remaining >= 0 ? 'card--green' : 'card--red' ?>">
                <h3><?= $remaining >= 0 ? 'Under budget' : 'Over budget' ?></h3>
                <p><?= formatCurrency(abs($remaining)) ?></p>
                <small><?= $remaining >= 0 ? 'You stayed within your plan' : 'You exceeded your plan' ?></small>
            </article>
            <?php if ($overCount > 0): ?>
            <article class="card card--red">
                <h3>Categories over</h3>
                <p><?= $overCount ?></p>
                <small>exceeded their limit</small>
            </article>
            <?php endif; ?>
            <?php if (count($underBudget) > 0): ?>
            <article class="card card--green">
                <h3>Categories under</h3>
                <p><?= count($underBudget) ?></p>
                <small>saved <?= formatCurrency($savedTotal) ?> combined</small>
            </article>
            <?php endif; ?>
        </div>

        <div class="table-wrapper" style="margin-top:1rem;">
            <table>
                <thead>
                    <tr><th>Category</th><th>Budgeted</th><th>Spent</th><th>Diff</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($budgetedCats as $c):
                    $diff   = (float)$c['budget_amount'] - (float)$c['spent'];
                    $cpct   = (float)$c['budget_amount'] > 0 ? round((float)$c['spent'] / (float)$c['budget_amount'] * 100, 1) : 0;
                    $status = $cpct >= 100 ? '🔴 Over' : ($cpct >= 80 ? '🟡 Near' : '🟢 OK');
                ?>
                <tr>
                    <td><?= htmlspecialchars($c['category_name']) ?></td>
                    <td><?= formatCurrency((float)$c['budget_amount']) ?></td>
                    <td><?= formatCurrency((float)$c['spent']) ?></td>
                    <td style="color:<?= $diff >= 0 ? '#22c55e' : '#f43f5e' ?>">
                        <?= $diff >= 0 ? '-' : '+' ?><?= formatCurrency(abs($diff)) ?>
                    </td>
                    <td><?= $status ?> <?= $cpct ?>%</td>
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
    const inputs    = document.querySelectorAll('.budget-input');
    const liveTotal = document.getElementById('live-total');
    const liveFoot  = document.getElementById('live-total-foot');
    const avgMap    = <?= json_encode($threeMonthAvg, JSON_UNESCAPED_UNICODE) ?>;

    function fmt(n) {
        return '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateTotal() {
        let total = 0;
        inputs.forEach(inp => { total += parseFloat(inp.value) || 0; });
        const str = fmt(total);
        if (liveTotal) liveTotal.textContent = str;
        if (liveFoot)  liveFoot.textContent  = str;
    }

    inputs.forEach(inp => inp.addEventListener('input', updateTotal));

    // Apply 3-month averages to empty inputs
    document.getElementById('btn-apply-avg')?.addEventListener('click', function () {
        inputs.forEach(inp => {
            const catId = inp.dataset.categoryId;
            const avg   = avgMap[catId];
            if (avg && avg > 0 && (!inp.value || parseFloat(inp.value) === 0)) {
                inp.value = Number(avg).toFixed(2);
            }
        });
        updateTotal();
    });

    // Clear all inputs
    document.getElementById('btn-clear-all')?.addEventListener('click', function () {
        if (!confirm('Clear all budget amounts for this month?')) return;
        inputs.forEach(inp => { inp.value = ''; });
        updateTotal();
    });
})();
</script>
