<?php
$activeModule      = 'budget';
$categories        = $categories        ?? [];
$subcategories     = $subcategories     ?? [];   // [category_id => [sub rows]]
$threeMonthAvg     = $threeMonthAvg     ?? [];
$subThreeMonthAvg  = $subThreeMonthAvg  ?? [];
$trendData         = $trendData         ?? [];
$selectedMonth     = $selectedMonth     ?? (int) date('n');
$selectedYear      = $selectedYear      ?? (int) date('Y');
$prevMonth         = $prevMonth         ?? ($selectedMonth === 1 ? 12 : $selectedMonth - 1);
$prevYear          = $prevYear          ?? ($selectedMonth === 1 ? $selectedYear - 1 : $selectedYear);
$nextMonth         = $nextMonth         ?? ($selectedMonth === 12 ? 1 : $selectedMonth + 1);
$nextYear          = $nextYear          ?? ($selectedMonth === 12 ? $selectedYear + 1 : $selectedYear);
$totalBudgeted     = $totalBudgeted     ?? 0.0;
$totalSpent        = $totalSpent        ?? 0.0;

$monthLabel = date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
$prevLabel  = date('M Y', mktime(0, 0, 0, $prevMonth, 1, $prevYear));

$budgetedCats = array_filter($categories, fn($c) => (float)$c['budget_amount'] > 0);
$overallPct   = $totalBudgeted > 0 ? round($totalSpent / $totalBudgeted * 100, 1) : 0;
$remaining    = $totalBudgeted - $totalSpent;
$overCount    = count(array_filter($budgetedCats, fn($c) => (float)$c['spent'] >= (float)$c['budget_amount']));
$warnCount    = count(array_filter($budgetedCats, fn($c) => (float)$c['budget_amount'] > 0 && (float)$c['spent'] / (float)$c['budget_amount'] >= 0.8 && (float)$c['spent'] < (float)$c['budget_amount']));

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
            <a href="?module=budget&bm=<?= $prevMonth ?>&by=<?= $prevYear ?>" class="secondary">← <?= $prevLabel ?></a>
            <strong style="padding:0.5rem 0.75rem;background:rgba(59,130,246,0.12);border-radius:8px;color:#93c5fd;white-space:nowrap;"><?= htmlspecialchars($monthLabel) ?></strong>
            <a href="?module=budget&bm=<?= $nextMonth ?>&by=<?= $nextYear ?>" class="secondary"><?= date('M Y', mktime(0,0,0,$nextMonth,1,$nextYear)) ?> →</a>
        </div>
    </header>

    <!-- Actions bar -->
    <section class="module-panel" style="padding:0.9rem 1.2rem;">
        <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;">
            <form method="post" style="margin:0;">
                <input type="hidden" name="form"  value="budget_copy_last">
                <input type="hidden" name="month" value="<?= $selectedMonth ?>">
                <input type="hidden" name="year"  value="<?= $selectedYear ?>">
                <button type="submit" class="secondary">Copy from <?= $prevLabel ?></button>
            </form>
            <button type="button" id="btn-apply-avg" class="secondary">Apply 3-month averages</button>
            <button type="button" id="btn-clear-all" class="secondary">Clear all</button>
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

    <!-- Overall progress graph -->
    <?php if ($totalBudgeted > 0): ?>
    <section class="module-panel">
        <h2>Overall Budget Progress — <?= htmlspecialchars($monthLabel) ?></h2>
        <div style="display:flex;align-items:center;gap:2rem;flex-wrap:wrap;">
            <div style="position:relative;width:180px;height:180px;flex-shrink:0;">
                <canvas id="overall-donut" width="180" height="180"></canvas>
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                    <span style="font-size:1.6rem;font-weight:700;color:<?= $overallPct >= 100 ? '#f43f5e' : ($overallPct >= 80 ? '#f59e0b' : '#22c55e') ?>;"><?= $overallPct ?>%</span>
                    <span style="font-size:0.72rem;color:var(--muted);">of budget</span>
                </div>
            </div>
            <div style="flex:1;min-width:200px;">
                <div style="margin-bottom:1.25rem;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:0.3rem;font-size:0.85rem;">
                        <span>Spent</span>
                        <strong style="color:<?= $overallPct >= 100 ? '#f43f5e' : ($overallPct >= 80 ? '#f59e0b' : '#22c55e') ?>;"><?= formatCurrency($totalSpent) ?></strong>
                    </div>
                    <div style="background:rgba(255,255,255,0.07);border-radius:8px;height:10px;">
                        <div style="background:<?= $overallPct >= 100 ? '#f43f5e' : ($overallPct >= 80 ? '#f59e0b' : '#22c55e') ?>;border-radius:8px;height:10px;width:<?= min(100,$overallPct) ?>%;transition:width 0.4s ease;"></div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0.75rem 1.5rem;font-size:0.85rem;">
                    <div>
                        <div style="color:var(--muted);font-size:0.75rem;margin-bottom:0.2rem;">Total Budgeted</div>
                        <div style="font-weight:600;"><?= formatCurrency($totalBudgeted) ?></div>
                    </div>
                    <div>
                        <div style="color:var(--muted);font-size:0.75rem;margin-bottom:0.2rem;"><?= $remaining >= 0 ? 'Remaining' : 'Over by' ?></div>
                        <div style="font-weight:600;color:<?= $remaining >= 0 ? '#22c55e' : '#f43f5e' ?>;"><?= formatCurrency(abs($remaining)) ?></div>
                    </div>
                    <?php if ($overCount > 0): ?>
                    <div>
                        <div style="color:var(--muted);font-size:0.75rem;margin-bottom:0.2rem;">Over limit</div>
                        <div style="font-weight:600;color:#f43f5e;"><?= $overCount ?> categor<?= $overCount === 1 ? 'y' : 'ies' ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($warnCount > 0): ?>
                    <div>
                        <div style="color:var(--muted);font-size:0.75rem;margin-bottom:0.2rem;">Near limit (80%+)</div>
                        <div style="font-weight:600;color:#f59e0b;"><?= $warnCount ?> categor<?= $warnCount === 1 ? 'y' : 'ies' ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        (function() {
            const spent    = <?= $totalSpent ?>;
            const budgeted = <?= $totalBudgeted ?>;
            const rem      = Math.max(0, budgeted - spent);
            const over     = Math.max(0, spent - budgeted);
            const pct      = budgeted > 0 ? spent / budgeted * 100 : 0;
            const color    = pct >= 100 ? '#f43f5e' : pct >= 80 ? '#f59e0b' : '#22c55e';

            new Chart(document.getElementById('overall-donut'), {
                type: 'doughnut',
                data: {
                    labels: over > 0 ? ['Spent (over budget)'] : ['Spent', 'Remaining'],
                    datasets: [{
                        data: over > 0 ? [spent] : [spent, rem],
                        backgroundColor: over > 0 ? [color] : [color, 'rgba(255,255,255,0.07)'],
                        borderWidth: 0,
                        hoverOffset: 4,
                    }]
                },
                options: {
                    cutout: '72%',
                    responsive: false,
                    animation: { animateRotate: true, duration: 600 },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: ctx => ' ₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 }) } }
                    }
                }
            });
        })();
        </script>
    </section>
    <?php endif; ?>

    <!-- Trend chart -->
    <?php if (count($trendData) >= 2): ?>
    <section class="module-panel">
        <h2>Budget vs Actual — last <?= count($trendData) ?> months</h2>
        <canvas id="trend-chart" height="90"></canvas>
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
                    <button type="submit"
                            style="border:none;border-radius:999px;padding:0.6rem 1.4rem;background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;font-weight:700;font-size:0.875rem;cursor:pointer;box-shadow:0 4px 14px rgba(59,130,246,0.3);">
                        Save budgets
                    </button>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Category / Subcategory</th>
                            <th style="width:150px;">Budget (₹)</th>
                            <th>Spent</th>
                            <th>3-mo avg</th>
                            <th style="min-width:150px;">Progress</th>
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
                        $hasSubs = !empty($subcategories[$catId]);
                    ?>
                        <!-- Category row -->
                        <tr class="cat-row" data-cat-id="<?= $catId ?>">
                            <td>
                                <?php if ($hasSubs): ?>
                                    <button type="button" class="toggle-subs secondary"
                                            data-cat="<?= $catId ?>"
                                            style="padding:0.15rem 0.45rem;font-size:0.72rem;margin-right:0.4rem;min-width:24px;"
                                            title="Expand subcategories">▶</button>
                                <?php endif; ?>
                                <strong><?= htmlspecialchars($cat['category_name']) ?></strong>
                            </td>
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
                                    <div style="flex:1;background:rgba(255,255,255,0.07);border-radius:5px;height:7px;min-width:70px;">
                                        <div style="background:<?= $color ?>;border-radius:5px;height:7px;width:<?= min(100,$pct) ?>%;"></div>
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

                        <!-- Subcategory rows (hidden by default) -->
                        <?php if ($hasSubs): ?>
                            <?php foreach ($subcategories[$catId] as $sub):
                                $subId     = (int) $sub['subcategory_id'];
                                $subAmt    = (float) $sub['budget_amount'];
                                $subSpent  = (float) $sub['spent'];
                                $subAvg    = (float) ($subThreeMonthAvg[$subId] ?? 0);
                                $subPct    = $subAmt > 0 ? min(200, round($subSpent / $subAmt * 100, 1)) : 0;
                                $subColor  = $barColor($subSpent, $subAmt);
                                $subAlert  = '';
                                if ($subAmt > 0) {
                                    if ($subPct >= 120)     $subAlert = '🔴 120%+';
                                    elseif ($subPct >= 100) $subAlert = '🔴 Over';
                                    elseif ($subPct >= 80)  $subAlert = '🟡 80%+';
                                }
                            ?>
                            <tr class="sub-row" data-parent-cat="<?= $catId ?>" style="display:none;background:rgba(0,0,0,0.15);">
                                <td style="padding-left:2.5rem;color:var(--muted);font-size:0.88rem;">
                                    ↳ <?= htmlspecialchars($sub['subcategory_name']) ?>
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        class="budget-input sub-budget-input"
                                        name="sub_budget[<?= $subId ?>][amount]"
                                        data-subcategory-id="<?= $subId ?>"
                                        data-avg="<?= $subAvg ?>"
                                        value="<?= $subAmt > 0 ? number_format($subAmt, 2, '.', '') : '' ?>"
                                        min="0" step="0.01"
                                        placeholder="—"
                                        style="width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:6px;padding:0.4rem 0.6rem;color:inherit;font-size:0.85rem;">
                                    <input type="hidden" name="sub_budget[<?= $subId ?>][name]" value="<?= htmlspecialchars($sub['subcategory_name']) ?>">
                                </td>
                                <td style="font-size:0.85rem;<?= $subSpent > 0 ? '' : 'color:var(--muted)' ?>">
                                    <?= $subSpent > 0 ? formatCurrency($subSpent) : '—' ?>
                                </td>
                                <td style="color:var(--muted);font-size:0.82rem;">
                                    <?= $subAvg > 0 ? formatCurrency($subAvg) : '—' ?>
                                </td>
                                <td>
                                    <?php if ($subAmt > 0): ?>
                                    <div style="display:flex;align-items:center;gap:0.4rem;">
                                        <div style="flex:1;background:rgba(255,255,255,0.07);border-radius:5px;height:6px;min-width:70px;">
                                            <div style="background:<?= $subColor ?>;border-radius:5px;height:6px;width:<?= min(100,$subPct) ?>%;"></div>
                                        </div>
                                        <span style="font-size:0.72rem;color:var(--muted);white-space:nowrap;"><?= $subPct ?>%</span>
                                        <?php if ($subAlert): ?>
                                            <span style="font-size:0.7rem;white-space:nowrap;"><?= $subAlert ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <span style="color:rgba(255,255,255,0.12);font-size:0.78rem;">No limit</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

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

            <div style="margin-top:1rem;display:flex;justify-content:flex-end;">
                <button type="submit"
                        style="border:none;border-radius:999px;padding:0.6rem 1.4rem;background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;font-weight:700;font-size:0.875rem;cursor:pointer;box-shadow:0 4px 14px rgba(59,130,246,0.3);">
                    Save budgets for <?= htmlspecialchars($monthLabel) ?>
                </button>
            </div>
        </section>
    </form>

    <!-- Spend ranking section -->
    <?php
    $rankedCategories = $categories;
    usort($rankedCategories, fn($a, $b) => (float)$b['spent'] <=> (float)$a['spent']);
    $hasAnySpend = array_sum(array_column($rankedCategories, 'spent')) > 0;
    if ($hasAnySpend):
    ?>
    <section class="module-panel">
        <h2 style="margin-bottom:1rem;">Spend Ranking — <?= htmlspecialchars($monthLabel) ?></h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Category / Subcategory</th>
                        <th>Spent</th>
                        <th>Budget (₹)</th>
                        <th>3-mo avg</th>
                        <th style="min-width:150px;">Progress</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rankedCategories as $rank => $cat):
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

                    $hasSubs = !empty($subcategories[$catId]);
                    $rankedSubs = $hasSubs ? $subcategories[$catId] : [];
                    if ($hasSubs) {
                        usort($rankedSubs, fn($a, $b) => (float)$b['spent'] <=> (float)$a['spent']);
                    }
                ?>
                    <tr class="cat-row">
                        <td style="color:var(--muted);font-size:0.85rem;width:2rem;"><?= $rank + 1 ?></td>
                        <td>
                            <?php if ($hasSubs): ?>
                                <button type="button" class="toggle-subs-rank secondary"
                                        data-cat="rank-<?= $catId ?>"
                                        style="padding:0.15rem 0.45rem;font-size:0.72rem;margin-right:0.4rem;min-width:24px;"
                                        title="Expand subcategories">▶</button>
                            <?php endif; ?>
                            <strong><?= htmlspecialchars($cat['category_name']) ?></strong>
                        </td>
                        <td style="<?= $spent > 0 ? 'color:inherit' : 'color:var(--muted)' ?>">
                            <?= $spent > 0 ? formatCurrency($spent) : '—' ?>
                        </td>
                        <td style="color:var(--muted);">
                            <?= $amount > 0 ? formatCurrency($amount) : '—' ?>
                        </td>
                        <td style="color:var(--muted);font-size:0.85rem;">
                            <?= $avg > 0 ? formatCurrency($avg) : '—' ?>
                        </td>
                        <td>
                            <?php if ($amount > 0): ?>
                            <div style="display:flex;align-items:center;gap:0.4rem;">
                                <div style="flex:1;background:rgba(255,255,255,0.07);border-radius:5px;height:7px;min-width:70px;">
                                    <div style="background:<?= $color ?>;border-radius:5px;height:7px;width:<?= min(100,$pct) ?>%;"></div>
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

                    <?php if ($hasSubs): ?>
                        <?php foreach ($rankedSubs as $sub):
                            $subId     = (int) $sub['subcategory_id'];
                            $subAmt    = (float) $sub['budget_amount'];
                            $subSpent  = (float) $sub['spent'];
                            $subAvg    = (float) ($subThreeMonthAvg[$subId] ?? 0);
                            $subPct    = $subAmt > 0 ? min(200, round($subSpent / $subAmt * 100, 1)) : 0;
                            $subColor  = $barColor($subSpent, $subAmt);
                            $subAlert  = '';
                            if ($subAmt > 0) {
                                if ($subPct >= 120)     $subAlert = '🔴 120%+';
                                elseif ($subPct >= 100) $subAlert = '🔴 Over';
                                elseif ($subPct >= 80)  $subAlert = '🟡 80%+';
                            }
                        ?>
                        <tr class="sub-row-rank" data-parent-cat="rank-<?= $catId ?>" style="display:none;background:rgba(0,0,0,0.15);">
                            <td style="color:var(--muted);font-size:0.78rem;"></td>
                            <td style="padding-left:2.5rem;color:var(--muted);font-size:0.88rem;">
                                ↳ <?= htmlspecialchars($sub['subcategory_name']) ?>
                            </td>
                            <td style="font-size:0.85rem;<?= $subSpent > 0 ? '' : 'color:var(--muted)' ?>">
                                <?= $subSpent > 0 ? formatCurrency($subSpent) : '—' ?>
                            </td>
                            <td style="color:var(--muted);font-size:0.85rem;">
                                <?= $subAmt > 0 ? formatCurrency($subAmt) : '—' ?>
                            </td>
                            <td style="color:var(--muted);font-size:0.82rem;">
                                <?= $subAvg > 0 ? formatCurrency($subAvg) : '—' ?>
                            </td>
                            <td>
                                <?php if ($subAmt > 0): ?>
                                <div style="display:flex;align-items:center;gap:0.4rem;">
                                    <div style="flex:1;background:rgba(255,255,255,0.07);border-radius:5px;height:6px;min-width:70px;">
                                        <div style="background:<?= $subColor ?>;border-radius:5px;height:6px;width:<?= min(100,$subPct) ?>%;"></div>
                                    </div>
                                    <span style="font-size:0.72rem;color:var(--muted);white-space:nowrap;"><?= $subPct ?>%</span>
                                    <?php if ($subAlert): ?>
                                        <span style="font-size:0.7rem;white-space:nowrap;"><?= $subAlert ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <span style="color:rgba(255,255,255,0.12);font-size:0.78rem;">No limit</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <!-- Month-end summary (past months only) -->
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
    const catInputs = document.querySelectorAll('.budget-input:not(.sub-budget-input)');
    const liveTotal = document.getElementById('live-total');
    const liveFoot  = document.getElementById('live-total-foot');
    const avgMap    = <?= json_encode($threeMonthAvg, JSON_UNESCAPED_UNICODE) ?>;
    const subAvgMap = <?= json_encode($subThreeMonthAvg, JSON_UNESCAPED_UNICODE) ?>;

    function fmt(n) {
        return '₹\u200a' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateTotal() {
        let total = 0;
        catInputs.forEach(inp => { total += parseFloat(inp.value) || 0; });
        const str = fmt(total);
        if (liveTotal) liveTotal.textContent = str;
        if (liveFoot)  liveFoot.textContent  = str;
    }

    catInputs.forEach(inp => inp.addEventListener('input', updateTotal));

    // Apply 3-month averages to empty category inputs
    document.getElementById('btn-apply-avg')?.addEventListener('click', function () {
        catInputs.forEach(inp => {
            const catId = inp.dataset.categoryId;
            const avg   = avgMap[catId];
            if (avg && avg > 0 && (!inp.value || parseFloat(inp.value) === 0)) {
                inp.value = Number(avg).toFixed(2);
            }
        });
        // Also apply to subcategory inputs
        document.querySelectorAll('.sub-budget-input').forEach(inp => {
            const subId = inp.dataset.subcategoryId;
            const avg   = subAvgMap[subId];
            if (avg && avg > 0 && (!inp.value || parseFloat(inp.value) === 0)) {
                inp.value = Number(avg).toFixed(2);
            }
        });
        updateTotal();
    });

    // Clear all inputs
    document.getElementById('btn-clear-all')?.addEventListener('click', function () {
        if (!confirm('Clear all budget amounts for this month?')) return;
        document.querySelectorAll('.budget-input').forEach(inp => { inp.value = ''; });
        updateTotal();
    });

    // Toggle subcategory rows
    document.querySelectorAll('.toggle-subs').forEach(btn => {
        btn.addEventListener('click', function () {
            const catId  = this.dataset.cat;
            const rows   = document.querySelectorAll('.sub-row[data-parent-cat="' + catId + '"]');
            const isOpen = this.textContent === '▼';
            rows.forEach(r => { r.style.display = isOpen ? 'none' : ''; });
            this.textContent = isOpen ? '▶' : '▼';
            this.title       = isOpen ? 'Expand subcategories' : 'Collapse subcategories';
        });
    });

    // Toggle subcategory rows in spend ranking section
    document.querySelectorAll('.toggle-subs-rank').forEach(btn => {
        btn.addEventListener('click', function () {
            const catId  = this.dataset.cat;
            const rows   = document.querySelectorAll('.sub-row-rank[data-parent-cat="' + catId + '"]');
            const isOpen = this.textContent === '▼';
            rows.forEach(r => { r.style.display = isOpen ? 'none' : ''; });
            this.textContent = isOpen ? '▶' : '▼';
            this.title       = isOpen ? 'Expand subcategories' : 'Collapse subcategories';
        });
    });
})();
</script>
