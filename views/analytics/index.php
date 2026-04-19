<?php
$activeModule = 'analytics';
$startDate = $startDate ?? date('Y-m-01');
$endDate = $endDate ?? date('Y-m-d');
$summary = $summary ?? ['total_income' => 0, 'total_expense' => 0, 'total_transfer' => 0, 'net_cashflow' => 0];
$earningsSummary = $earningsSummary ?? ['total_earnings' => 0, 'entries' => 0];
$earningsBySubcategory = $earningsBySubcategory ?? [];
$expensesBySubcategory = $expensesBySubcategory ?? [];
$expensesByCategory = $expensesByCategory ?? [];
$incomeByCategory = $incomeByCategory ?? [];
$monthlyTrend = $monthlyTrend ?? [];
$accountWiseExpense = $accountWiseExpense ?? [];
$dayOfWeekSpend = $dayOfWeekSpend ?? [];
$drilldown = $drilldown ?? [];
$drilldownFilters = $drilldownFilters ?? [];
$categoriesWithSubs = $categoriesWithSubs ?? [];
$purchaseSources = $purchaseSources ?? [];

$netCashflow        = (float) ($summary['net_cashflow'] ?? 0);
$netClass           = $netCashflow >= 0 ? 'card--green' : 'card--red';
$excludedCategories = $excludedCategories ?? [];

// ── Insights engine ──────────────────────────────────────────────────────────
function buildInsights(array $p): array {
    $insights    = [];
    $totalExp    = (float) ($p['summary']['total_expense'] ?? 0);
    $totalInc    = (float) ($p['summary']['total_income']  ?? 0);
    $expCats     = $p['expensesByCategory'] ?? [];
    $dowSpend    = $p['dayOfWeekSpend']    ?? [];
    $trend       = $p['monthlyTrend']      ?? [];
    $ddCat       = $p['ddCat']             ?? [];
    $ddSub       = $p['ddSub']             ?? [];
    $ddSource    = $p['ddSource']          ?? [];
    $ddTotal     = (float) ($p['ddTotal']  ?? 0);
    $ddType      = $p['ddType']            ?? '';
    $startDate   = $p['startDate'];
    $endDate     = $p['endDate'];

    $dtStart  = date_create($startDate);
    $dtEnd    = date_create($endDate);
    $days     = (int) date_diff($dtStart, $dtEnd)->days + 1;
    $fmt      = fn($v) => '₹' . number_format((float)$v, 2, '.', ',');
    $pct      = fn($v, $t) => $t > 0 ? round($v / $t * 100, 1) : 0;

    // 1. Top spending category
    if (!empty($expCats)) {
        $top    = $expCats[0];
        $topAmt = (float) $top['total_amount'];
        $topPct = $pct($topAmt, $totalExp);
        $save30 = $topAmt * 0.3;
        $insights[] = [
            'type'  => 'warning',
            'icon'  => '🏷️',
            'title' => 'Biggest spend: ' . ($top['category_name'] ?? 'Uncategorized'),
            'body'  => "You spent {$fmt($topAmt)} on {$top['category_name']} — {$topPct}% of total expenses. Trimming this by 30% would free up {$fmt($save30)} over this period.",
        ];
    }

    // 2. Top 2 categories concentration
    if (count($expCats) >= 2 && $totalExp > 0) {
        $top2   = (float)$expCats[0]['total_amount'] + (float)$expCats[1]['total_amount'];
        $top2Pc = $pct($top2, $totalExp);
        if ($top2Pc >= 50) {
            $insights[] = [
                'type'  => 'info',
                'icon'  => '📊',
                'title' => 'Spending concentrated in 2 categories',
                'body'  => "{$top2Pc}% of your expenses ({$fmt($top2)}) come from just \"{$expCats[0]['category_name']}\" and \"{$expCats[1]['category_name']}\". Diversifying or controlling these two gives the biggest impact.",
            ];
        }
    }

    // 3. Daily average burn rate
    if ($totalExp > 0 && $days > 0) {
        $daily = $totalExp / $days;
        $proj  = $daily * 30;
        $insights[] = [
            'type'  => 'info',
            'icon'  => '📅',
            'title' => 'Daily spend rate: ' . $fmt($daily),
            'body'  => "Over {$days} days you averaged {$fmt($daily)}/day in expenses. At this rate your monthly outflow would be {$fmt($proj)}.",
        ];
    }

    // 4. Savings rate
    if ($totalInc > 0) {
        $saved    = $totalInc - $totalExp;
        $saveRate = $pct($saved, $totalInc);
        $type     = $saveRate >= 20 ? 'positive' : ($saveRate >= 0 ? 'warning' : 'negative');
        $msg      = $saveRate >= 20
            ? "Great discipline! You saved {$saveRate}% of income ({$fmt($saved)})."
            : ($saveRate >= 0
                ? "You saved {$saveRate}% of income ({$fmt($saved)}). Aim for 20%+ for a stronger financial cushion."
                : "Expenses exceeded income by {$fmt(abs($saved))} ({$fmt($totalInc)} earned vs {$fmt($totalExp)} spent). Review recurring costs.");
        $insights[] = [
            'type'  => $type,
            'icon'  => $saveRate >= 20 ? '✅' : ($saveRate >= 0 ? '⚠️' : '🚨'),
            'title' => 'Savings rate: ' . $saveRate . '%',
            'body'  => $msg,
        ];
    }

    // 5. Peak spending day of week
    if (!empty($dowSpend)) {
        $sorted = $dowSpend;
        usort($sorted, fn($a, $b) => (float)$b['total_amount'] <=> (float)$a['total_amount']);
        $peak    = $sorted[0];
        $peakAmt = (float) $peak['total_amount'];
        $peakPct = $pct($peakAmt, $totalExp);
        $insights[] = [
            'type'  => 'info',
            'icon'  => '📆',
            'title' => 'Peak spending day: ' . $peak['dow_name'],
            'body'  => "You spend most on {$peak['dow_name']}s — {$fmt($peakAmt)} ({$peakPct}% of expenses, {$peak['tx_count']} transactions). Consider setting a daily budget for high-spend days.",
        ];
    }

    // 6. Monthly trend comparison (last 2 months)
    if (count($trend) >= 2) {
        $last = end($trend);
        prev($trend);
        $prev     = current($trend);
        $lastExp  = (float) $last['expense'];
        $prevExp  = (float) $prev['expense'];
        if ($prevExp > 0) {
            $delta = $lastExp - $prevExp;
            $deltaPct = round(abs($delta) / $prevExp * 100, 1);
            $dir   = $delta > 0 ? 'increased' : 'decreased';
            $type  = $delta > 0 ? 'warning' : 'positive';
            $insights[] = [
                'type'  => $type,
                'icon'  => $delta > 0 ? '📈' : '📉',
                'title' => "Spending {$dir} {$deltaPct}% vs prior month",
                'body'  => "Last month ({$last['period']}) expenses: {$fmt($lastExp)}. Prior month ({$prev['period']}): {$fmt($prevExp)}. Difference: {$fmt(abs($delta))}.",
            ];
        }
    }

    // 7. Drilldown-specific insights (shown only when filter is active)
    if ($ddTotal > 0) {
        $typeLabel = $ddType === 'expense' ? 'expense' : ($ddType === 'income' ? 'income' : 'transaction');

        // Top filtered source
        if (!empty($ddSource)) {
            $src     = $ddSource[0];
            $srcAmt  = (float) $src['total'];
            $srcPct  = $pct($srcAmt, $ddTotal);
            $insights[] = [
                'type'  => 'warning',
                'icon'  => '🏪',
                'title' => 'Filter focus: most spent at ' . $src['label'],
                'body'  => "In your filtered results, {$fmt($srcAmt)} ({$srcPct}%) was spent at \"{$src['label']}\". " . (count($ddSource) > 1 ? "Second was \"{$ddSource[1]['label']}\" at {$fmt((float)$ddSource[1]['total'])}." : ""),
            ];
        }

        // Top filtered subcategory
        if (!empty($ddSub)) {
            $sub    = $ddSub[0];
            $subAmt = (float) $sub['total'];
            $subPct = $pct($subAmt, $ddTotal);
            $insights[] = [
                'type'  => 'info',
                'icon'  => '🔍',
                'title' => 'Filter spotlight: ' . $sub['label'],
                'body'  => "\"{$sub['label']}\" accounts for {$fmt($subAmt)} ({$subPct}%) of your filtered {$typeLabel}s. " . (count($ddSub) > 1 ? "If you eliminated just this subcategory you'd cut the filtered total by over {$subPct}%." : ""),
            ];
        }
    }

    return $insights;
}

$ddCat    = $drilldown['by_category']    ?? [];
$ddSub    = $drilldown['by_subcategory'] ?? [];
$ddSource = $drilldown['by_source']      ?? [];
$ddTotal  = (float) ($drilldown['summary']['total'] ?? 0);
$ddType   = $drilldownFilters['tx_type'] ?? '';

$ddCatIncome   = $drilldown['by_category_income']    ?? [];
$ddCatExpense  = $drilldown['by_category_expense']   ?? [];
$ddSubIncome   = $drilldown['by_subcategory_income']  ?? [];
$ddSubExpense  = $drilldown['by_subcategory_expense'] ?? [];
$ddTotalIncome  = (float) ($drilldown['summary_income']['total']  ?? 0);
$ddTotalExpense = (float) ($drilldown['summary_expense']['total'] ?? 0);
$ddCountIncome  = (int)   ($drilldown['summary_income']['tx_count']  ?? 0);
$ddCountExpense = (int)   ($drilldown['summary_expense']['tx_count'] ?? 0);

$insights = buildInsights([
    'summary'           => $summary,
    'expensesByCategory'=> $expensesByCategory,
    'dayOfWeekSpend'    => $dayOfWeekSpend,
    'monthlyTrend'      => $monthlyTrend,
    'ddCat'             => $ddCat,
    'ddSub'             => $ddSub,
    'ddSource'          => $ddSource,
    'ddTotal'           => $ddTotal,
    'ddType'            => $ddType,
    'startDate'         => $startDate,
    'endDate'           => $endDate,
]);

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <!-- Chart.js loaded once here — avoids race conditions with conditional loading -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <header class="module-header">
        <h1>Analytics</h1>
    </header>

    <?php if (!empty($excludedCategories)): ?>
    <div style="background:rgba(148,163,184,0.08);border:1px solid rgba(148,163,184,0.25);border-radius:6px;padding:0.6rem 1rem;display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;font-size:0.82rem;color:var(--muted);">
        <span>⚙️ Excluded from all analytics:</span>
        <?php foreach ($excludedCategories as $exc): ?>
            <span class="pill pill--muted"><?= htmlspecialchars($exc['name']) ?></span>
        <?php endforeach; ?>
        <a href="?module=categories" style="margin-left:auto;font-size:0.78rem;color:var(--muted);">Manage →</a>
    </div>
    <?php endif; ?>

    <!-- Drilldown filter -->
    <?php
    $selCatIds = $drilldownFilters['category_ids'] ?? [];
    $selSubIds = $drilldownFilters['subcategory_ids'] ?? [];
    $selSrcIds = $drilldownFilters['purchase_source_ids'] ?? [];
    ?>
    <section class="module-panel">
        <h2>Drill-down analysis</h2>
        <form method="get" id="drilldown-form">
            <input type="hidden" name="module" value="analytics">
            <div class="module-form" style="align-items:flex-end;">
                <label>Start date
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                </label>
                <label>End date
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                </label>
                <label>Type
                    <select name="tx_type">
                        <option value="">All (income + expense)</option>
                        <option value="expense" <?= ($drilldownFilters['tx_type'] ?? '') === 'expense' ? 'selected' : '' ?>>Expense only</option>
                        <option value="income"  <?= ($drilldownFilters['tx_type'] ?? '') === 'income'  ? 'selected' : '' ?>>Income only</option>
                    </select>
                </label>
                <div style="display:flex;gap:0.5rem;">
                    <button type="submit">Apply</button>
                    <a class="secondary" href="?module=analytics">Reset</a>
                </div>
            </div>

            <div class="check-group-wrap" style="margin-top:1rem;">

                <!-- Categories -->
                <div class="check-group <?= !empty($selCatIds) ? 'open' : '' ?>" id="cg-category">
                    <div class="check-group-header" onclick="toggleCG('cg-category')">
                        Category<?php if (!empty($selCatIds)): ?><span class="check-sel-count"><?= count($selCatIds) ?></span><?php endif; ?>
                        <span class="cg-arrow">▼</span>
                    </div>
                    <div class="check-group-body">
                        <label>
                            <input type="checkbox" name="category_id[]" value="0"
                                <?= in_array(0, $selCatIds) ? 'checked' : '' ?>
                                data-cg-cat="0"
                                onchange="filterSubcategories()">
                            <em class="muted">Uncategorized</em>
                        </label>
                        <?php foreach ($categoriesWithSubs as $cat): ?>
                            <label>
                                <input type="checkbox" name="category_id[]" value="<?= (int)$cat['id'] ?>"
                                    <?= in_array((int)$cat['id'], $selCatIds) ? 'checked' : '' ?>
                                    data-cg-cat="<?= (int)$cat['id'] ?>"
                                    onchange="filterSubcategories()">
                                <?= htmlspecialchars($cat['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Subcategories -->
                <div class="check-group <?= !empty($selSubIds) ? 'open' : '' ?>" id="cg-subcategory">
                    <div class="check-group-header" onclick="toggleCG('cg-subcategory')">
                        Subcategory<?php if (!empty($selSubIds)): ?><span class="check-sel-count"><?= count($selSubIds) ?></span><?php endif; ?>
                        <span class="cg-arrow">▼</span>
                    </div>
                    <div class="check-group-body">
                        <label data-sub-parent="0">
                            <input type="checkbox" name="subcategory_id[]" value="0"
                                <?= in_array(0, $selSubIds) ? 'checked' : '' ?>>
                            <em class="muted">Unspecified</em>
                        </label>
                        <?php foreach ($categoriesWithSubs as $cat): ?>
                            <?php foreach (($cat['subcategories'] ?? []) as $sub): ?>
                                <label data-sub-parent="<?= (int)$cat['id'] ?>">
                                    <input type="checkbox" name="subcategory_id[]" value="<?= (int)$sub['id'] ?>"
                                        <?= in_array((int)$sub['id'], $selSubIds) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($sub['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Purchase sources -->
                <?php if (!empty($purchaseSources)): ?>
                <div class="check-group <?= !empty($selSrcIds) ? 'open' : '' ?>" id="cg-source">
                    <div class="check-group-header" onclick="toggleCG('cg-source')">
                        Purchased from<?php if (!empty($selSrcIds)): ?><span class="check-sel-count"><?= count($selSrcIds) ?></span><?php endif; ?>
                        <span class="cg-arrow">▼</span>
                    </div>
                    <div class="check-group-body">
                        <?php foreach ($purchaseSources as $src): ?>
                            <label>
                                <input type="checkbox" name="purchase_source_id[]" value="<?= (int)$src['id'] ?>"
                                    <?= in_array((int)$src['id'], $selSrcIds) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($src['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </form>
    </section>

    <!-- Drilldown results -->
    <?php
    $ddSummary  = $drilldown['summary']        ?? ['total' => 0, 'tx_count' => 0];
    $ddTxns     = $drilldown['transactions']   ?? [];
    ?>
    <section class="summary-cards">
        <?php if ($ddType === 'income'): ?>
            <article class="card card--green">
                <h3>Total income</h3>
                <p><?= formatCurrency($ddTotalIncome) ?></p>
                <small><?= $ddCountIncome ?> transactions</small>
            </article>
        <?php elseif ($ddType === 'expense'): ?>
            <article class="card card--red">
                <h3>Total expense</h3>
                <p><?= formatCurrency($ddTotalExpense) ?></p>
                <small><?= $ddCountExpense ?> transactions</small>
            </article>
        <?php else: ?>
            <article class="card card--green">
                <h3>Total income</h3>
                <p><?= formatCurrency($ddTotalIncome) ?></p>
                <small><?= $ddCountIncome ?> transactions</small>
            </article>
            <article class="card card--red">
                <h3>Total expense</h3>
                <p><?= formatCurrency($ddTotalExpense) ?></p>
                <small><?= $ddCountExpense ?> transactions</small>
            </article>
            <article class="card card--cyan">
                <h3>Net</h3>
                <p><?= formatCurrency($ddTotalIncome - $ddTotalExpense) ?></p>
                <small>Income minus expense</small>
            </article>
        <?php endif; ?>
    </section>

    <!-- Insights panel -->
    <?php if (!empty($insights)): ?>
    <section class="module-panel">
        <h2>Spending insights <small style="font-size:0.75rem;color:var(--muted);font-weight:400;">· <?= htmlspecialchars($startDate) ?> to <?= htmlspecialchars($endDate) ?></small></h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
            <?php
            $insightColors = [
                'warning'  => ['bg' => 'rgba(251,146,60,0.12)', 'border' => '#f97316', 'text' => '#fdba74'],
                'info'     => ['bg' => 'rgba(59,130,246,0.12)', 'border' => '#3b82f6', 'text' => '#93c5fd'],
                'positive' => ['bg' => 'rgba(16,185,129,0.12)', 'border' => '#10b981', 'text' => '#6ee7b7'],
                'negative' => ['bg' => 'rgba(239,68,68,0.12)',  'border' => '#ef4444', 'text' => '#fca5a5'],
            ];
            foreach ($insights as $ins):
                $c = $insightColors[$ins['type']] ?? $insightColors['info'];
            ?>
            <div style="background:<?= $c['bg'] ?>;border:1px solid <?= $c['border'] ?>;border-radius:8px;padding:1rem;">
                <div style="font-size:1.3rem;margin-bottom:0.4rem;"><?= $ins['icon'] ?></div>
                <div style="font-weight:600;margin-bottom:0.3rem;color:<?= $c['text'] ?>;font-size:0.9rem;"><?= htmlspecialchars($ins['title']) ?></div>
                <div style="font-size:0.82rem;color:var(--muted);line-height:1.5;"><?= htmlspecialchars($ins['body']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php
    $showIncome  = $ddType === '' || $ddType === 'income';
    $showExpense = $ddType === '' || $ddType === 'expense';
    $hasAny = (!empty($ddCatIncome) && $showIncome) || (!empty($ddCatExpense) && $showExpense) || !empty($ddSource);
    ?>
    <?php if ($hasAny): ?>
    <section class="module-panel">
        <h2>Filter breakdown <small style="font-size:0.75rem;color:var(--muted);font-weight:400;">· <?= $ddType ? ucfirst($ddType) . 's' : 'Income &amp; Expense' ?> · <?= htmlspecialchars($startDate) ?> → <?= htmlspecialchars($endDate) ?></small></h2>

        <?php
        $barColors = ['#3b82f6','#f97316','#a855f7','#22d3ee','#10b981','#eab308','#ec4899','#6366f1','#14b8a6','#ef4444'];

        // Helper: stacked bar for a dataset
        $renderBar = function(array $rows, float $total) use ($barColors): void {
            if (empty($rows) || $total <= 0) return;
            echo '<div style="display:flex;height:22px;border-radius:6px;overflow:hidden;gap:2px;margin-bottom:0.4rem;">';
            foreach ($rows as $i => $row) {
                $w = round((float)$row['total'] / $total * 100, 2);
                if ($w <= 0) continue;
                $col = $barColors[$i % count($barColors)];
                echo '<div title="' . htmlspecialchars($row['label']) . ': ' . formatCurrency((float)$row['total']) . ' (' . $w . '%)"'
                   . ' style="background:' . $col . ';width:' . $w . '%;min-width:3px;cursor:default;"'
                   . ' onmouseenter="this.style.opacity=\'.7\'" onmouseleave="this.style.opacity=\'1\'"></div>';
            }
            echo '</div>';
        };
        ?>

        <?php if ($ddType === ''): ?>
        <!-- Side-by-side income / expense when "All transactions" is selected -->
        <div class="charts-2col">

            <!-- Income column -->
            <?php if (!empty($ddCatIncome)): ?>
            <div>
                <div style="font-size:0.78rem;font-weight:600;color:#10b981;text-transform:uppercase;letter-spacing:.05em;margin-bottom:0.6rem;">
                    ▲ Income — <?= formatCurrency($ddTotalIncome) ?> (<?= $ddCountIncome ?> txns)
                </div>
                <?php $renderBar($ddCatIncome, $ddTotalIncome); ?>
                <div style="margin-bottom:0.8rem;"><canvas id="dd-cat-income-chart"></canvas></div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Category</th><th>Amount</th><th>%</th></tr></thead>
                        <tbody>
                            <?php foreach ($ddCatIncome as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['label']) ?></td>
                                <td style="color:var(--green)"><?= formatCurrency((float)$row['total']) ?></td>
                                <td><?= $ddTotalIncome > 0 ? number_format((float)$row['total'] / $ddTotalIncome * 100, 1) . '%' : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($ddSubIncome)): ?>
                <h3 style="font-size:0.8rem;color:var(--muted);margin:1rem 0 0.4rem;text-transform:uppercase;letter-spacing:.05em;">By subcategory</h3>
                <div style="margin-bottom:0.8rem;"><canvas id="dd-sub-income-chart"></canvas></div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Subcategory</th><th>Amount</th><th>%</th></tr></thead>
                        <tbody>
                            <?php foreach ($ddSubIncome as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['label']) ?></td>
                                <td style="color:var(--green)"><?= formatCurrency((float)$row['total']) ?></td>
                                <td><?= $ddTotalIncome > 0 ? number_format((float)$row['total'] / $ddTotalIncome * 100, 1) . '%' : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Expense column -->
            <?php if (!empty($ddCatExpense)): ?>
            <div>
                <div style="font-size:0.78rem;font-weight:600;color:#ef4444;text-transform:uppercase;letter-spacing:.05em;margin-bottom:0.6rem;">
                    ▼ Expense — <?= formatCurrency($ddTotalExpense) ?> (<?= $ddCountExpense ?> txns)
                </div>
                <?php $renderBar($ddCatExpense, $ddTotalExpense); ?>
                <div style="margin-bottom:0.8rem;"><canvas id="dd-cat-expense-chart"></canvas></div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Category</th><th>Amount</th><th>%</th></tr></thead>
                        <tbody>
                            <?php foreach ($ddCatExpense as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['label']) ?></td>
                                <td style="color:var(--red,#f43f5e)"><?= formatCurrency((float)$row['total']) ?></td>
                                <td><?= $ddTotalExpense > 0 ? number_format((float)$row['total'] / $ddTotalExpense * 100, 1) . '%' : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($ddSubExpense)): ?>
                <h3 style="font-size:0.8rem;color:var(--muted);margin:1rem 0 0.4rem;text-transform:uppercase;letter-spacing:.05em;">By subcategory</h3>
                <div style="margin-bottom:0.8rem;"><canvas id="dd-sub-expense-chart"></canvas></div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Subcategory</th><th>Amount</th><th>%</th></tr></thead>
                        <tbody>
                            <?php foreach ($ddSubExpense as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['label']) ?></td>
                                <td style="color:var(--red,#f43f5e)"><?= formatCurrency((float)$row['total']) ?></td>
                                <td><?= $ddTotalExpense > 0 ? number_format((float)$row['total'] / $ddTotalExpense * 100, 1) . '%' : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div><!-- end side-by-side -->

        <?php else: ?>
        <!-- Single-type view (Income only or Expense only) -->
        <?php
        $singleCat   = $ddType === 'income' ? $ddCatIncome   : $ddCatExpense;
        $singleSub   = $ddType === 'income' ? $ddSubIncome   : $ddSubExpense;
        $singleTotal = $ddType === 'income' ? $ddTotalIncome : $ddTotalExpense;
        $singleColor = $ddType === 'income' ? 'var(--green)' : 'var(--red,#f43f5e)';
        ?>
        <?php if (!empty($singleCat)): ?>
        <?php $renderBar($singleCat, $singleTotal); ?>
        <div class="charts-2col">
            <div>
                <h3 style="font-size:0.85rem;color:var(--muted);margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:.05em;">By category</h3>
                <div><canvas id="dd-cat-single-chart"></canvas></div>
                <div class="table-wrapper" style="margin-top:0.8rem;">
                    <table>
                        <thead><tr><th>Category</th><th>Amount</th><th>%</th></tr></thead>
                        <tbody>
                            <?php foreach ($singleCat as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['label']) ?></td>
                                <td style="color:<?= $singleColor ?>"><?= formatCurrency((float)$row['total']) ?></td>
                                <td><?= $singleTotal > 0 ? number_format((float)$row['total'] / $singleTotal * 100, 1) . '%' : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (!empty($singleSub)): ?>
            <div>
                <h3 style="font-size:0.85rem;color:var(--muted);margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:.05em;">By subcategory</h3>
                <div><canvas id="dd-sub-single-chart"></canvas></div>
                <div class="table-wrapper" style="margin-top:0.8rem;">
                    <table>
                        <thead><tr><th>Subcategory</th><th>Amount</th><th>%</th></tr></thead>
                        <tbody>
                            <?php foreach ($singleSub as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['label']) ?></td>
                                <td style="color:<?= $singleColor ?>"><?= formatCurrency((float)$row['total']) ?></td>
                                <td><?= $singleTotal > 0 ? number_format((float)$row['total'] / $singleTotal * 100, 1) . '%' : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($ddSource)): ?>
        <div style="margin-top:1.2rem;">
            <h3 style="font-size:0.85rem;color:var(--muted);margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:.05em;">By purchased from (expense)</h3>
            <canvas id="dd-source-chart" style="max-height:220px;"></canvas>
            <div class="table-wrapper" style="margin-top:0.8rem;">
                <table>
                    <thead><tr><th>Source</th><th>Amount</th><th>%</th></tr></thead>
                    <tbody>
                        <?php foreach ($ddSource as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['label']) ?></td>
                                <td><?= formatCurrency((float)$row['total']) ?></td>
                                <td><?= $ddTotalExpense > 0 ? number_format((float)$row['total'] / $ddTotalExpense * 100, 1) . '%' : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <script>
        (function () {
            const incomeColors  = ['#10b981','#22d3ee','#6ee7b7','#34d399','#059669','#14b8a6','#a7f3d0','#6366f1','#93c5fd','#bbf7d0'];
            const expenseColors = ['#ef4444','#f97316','#eab308','#a855f7','#3b82f6','#ec4899','#f43f5e','#fb923c','#facc15','#c084fc'];
            const mixedColors   = ['#3b82f6','#22d3ee','#a855f7','#f97316','#10b981','#eab308','#ec4899','#6366f1','#14b8a6','#ef4444'];
            const donutOpts = (colors) => ({ responsive: true, aspectRatio: 2.5, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { color: '#cbd5e1', boxWidth: 12 } }, tooltip: { callbacks: { label: ctx => ctx.label + ': ₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 }) } } } });
            const barOpts   = { indexAxis: 'y', responsive: true, plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => '₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 }) } } }, scales: { x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.07)' } }, y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.07)' } } } };
            const makeDonut = (id, rows, colors) => {
                const el = document.getElementById(id);
                if (!el || !rows.length) return;
                new Chart(el, { type: 'doughnut', data: { labels: rows.map(r => r.label), datasets: [{ data: rows.map(r => Number(r.total)), backgroundColor: rows.map((_, i) => colors[i % colors.length]), borderColor: '#0f172a', borderWidth: 2 }] }, options: donutOpts(colors) });
            };

            <?php if ($ddType === ''): ?>
            makeDonut('dd-cat-income-chart',  <?= json_encode($ddCatIncome,  JSON_UNESCAPED_UNICODE) ?>, incomeColors);
            makeDonut('dd-sub-income-chart',  <?= json_encode($ddSubIncome,  JSON_UNESCAPED_UNICODE) ?>, incomeColors);
            makeDonut('dd-cat-expense-chart', <?= json_encode($ddCatExpense, JSON_UNESCAPED_UNICODE) ?>, expenseColors);
            makeDonut('dd-sub-expense-chart', <?= json_encode($ddSubExpense, JSON_UNESCAPED_UNICODE) ?>, expenseColors);
            <?php else: ?>
            makeDonut('dd-cat-single-chart', <?= json_encode($ddType === 'income' ? $ddCatIncome : $ddCatExpense, JSON_UNESCAPED_UNICODE) ?>, <?= $ddType === 'income' ? 'incomeColors' : 'expenseColors' ?>);
            makeDonut('dd-sub-single-chart', <?= json_encode($ddType === 'income' ? $ddSubIncome : $ddSubExpense, JSON_UNESCAPED_UNICODE) ?>, <?= $ddType === 'income' ? 'incomeColors' : 'expenseColors' ?>);
            <?php endif; ?>

            <?php if (!empty($ddSource)): ?>
            (function () {
                const rows = <?= json_encode($ddSource, JSON_UNESCAPED_UNICODE) ?>;
                new Chart(document.getElementById('dd-source-chart'), { type: 'bar', data: { labels: rows.map(r => r.label), datasets: [{ data: rows.map(r => Number(r.total)), backgroundColor: rows.map((_, i) => expenseColors[i % expenseColors.length]), borderRadius: 4 }] }, options: barOpts });
            })();
            <?php endif; ?>
        })();
        </script>
    </section>
    <?php endif; ?>

    <?php if (!empty($ddTxns)): ?>
    <section class="module-panel">
        <h2>Transactions (latest <?= count($ddTxns) ?>)</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Date</th><th>Type</th><th>Category</th><th>Subcategory</th><th>Purchased from</th><th>Amount</th><th>Notes</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($ddTxns as $tx): ?>
                        <tr>
                            <td><?= htmlspecialchars($tx['transaction_date']) ?></td>
                            <td><span class="pill <?= $tx['transaction_type'] === 'income' ? 'pill--green' : 'pill--red' ?>"><?= ucfirst($tx['transaction_type']) ?></span></td>
                            <td><?= htmlspecialchars($tx['category_name']) ?></td>
                            <td><?= htmlspecialchars($tx['subcategory_name']) ?: '—' ?></td>
                            <td><?= htmlspecialchars($tx['source_name']) ?: '—' ?></td>
                            <td><?= formatCurrency((float)$tx['amount']) ?></td>
                            <td><?= htmlspecialchars($tx['notes'] ?? '') ?: '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

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

    <script>
    function toggleCG(id) {
        document.getElementById(id).classList.toggle('open');
    }
    function filterSubcategories() {
        const checkedCats = new Set(
            Array.from(document.querySelectorAll('[data-cg-cat]:checked')).map(el => el.value)
        );
        document.querySelectorAll('#cg-subcategory [data-sub-parent]').forEach(label => {
            const show = checkedCats.size === 0 || checkedCats.has(label.dataset.subParent);
            label.classList.toggle('hidden', !show);
            if (!show) label.querySelector('input').checked = false;
        });
    }
    filterSubcategories();
    </script>

    <!-- Monthly income vs expense bar chart + cashflow line -->
    <?php if (!empty($monthlyTrend)): ?>
    <section class="module-panel">
        <h2>Monthly income vs expense (last 12 months)</h2>
        <div class="charts-2col">
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
        <div class="charts-2col">
            <?php if (!empty($expensesByCategory)): ?>
            <div>
                <h3 style="font-size:0.85rem;color:var(--muted);margin-bottom:0.6rem;text-transform:uppercase;letter-spacing:.05em;">Expense by category</h3>
                <div><canvas id="expense-donut-chart"></canvas></div>
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
                <h3 style="font-size:0.85rem;color:var(--muted);margin-bottom:0.6rem;text-transform:uppercase;letter-spacing:.05em;">Income by category</h3>
                <div><canvas id="income-donut-chart"></canvas></div>
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
                aspectRatio: 2,
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
        <div class="charts-2col">
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

    <!-- Expenses by subcategory -->
    <?php if (!empty($expensesBySubcategory)): ?>
    <section class="module-panel">
        <h2>Actual expenses by subcategory</h2>
        <div class="charts-2col">
            <div><canvas id="expense-sub-donut-chart"></canvas></div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Subcategory</th><th>Category</th><th>Amount</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expensesBySubcategory as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['subcategory_name']) ?></td>
                                <td style="color:var(--muted);font-size:0.85rem;"><?= htmlspecialchars($row['category_name']) ?></td>
                                <td><?= formatCurrency((float)($row['total_amount'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
        (function () {
            const rows = <?= json_encode($expensesBySubcategory, JSON_UNESCAPED_UNICODE) ?>;
            const colors = ['#ef4444','#f97316','#eab308','#a855f7','#3b82f6','#22d3ee','#10b981','#ec4899','#6366f1','#14b8a6'];
            new Chart(document.getElementById('expense-sub-donut-chart'), {
                type: 'doughnut',
                data: {
                    labels: rows.map(r => r.subcategory_name),
                    datasets: [{
                        data: rows.map(r => Number(r.total_amount)),
                        backgroundColor: rows.map((_, i) => colors[i % colors.length]),
                        borderColor: '#0f172a',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    aspectRatio: 2,
                    cutout: '55%',
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#cbd5e1', boxWidth: 12 } },
                        tooltip: { callbacks: { label: ctx => ctx.label + ': ₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 }) } }
                    }
                }
            });
        })();
        </script>
    </section>
    <?php endif; ?>

    <?php if (empty($expensesByCategory) && empty($incomeByCategory) && empty($monthlyTrend)): ?>
    <section class="module-panel">
        <p class="muted">No transaction data in selected period.</p>
    </section>
    <?php endif; ?>

</main>
