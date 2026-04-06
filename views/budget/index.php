<?php
$activeModule  = 'budget';
$budgets       = $budgets       ?? [];
$categories    = $categories    ?? [];
$selectedMonth = $selectedMonth ?? (int) date('n');
$selectedYear  = $selectedYear  ?? (int) date('Y');
$editBudget    = $editBudget    ?? null;
$totalBudgeted = $totalBudgeted ?? 0.0;
$totalSpent    = $totalSpent    ?? 0.0;
$remaining     = $remaining     ?? 0.0;
$overCount     = $overCount     ?? 0;

$monthName     = date('F', mktime(0, 0, 0, $selectedMonth, 1));
$overallPct    = $totalBudgeted > 0 ? round($totalSpent / $totalBudgeted * 100, 1) : 0;

$budgetBarColor = function(float $spent, float $amount): string {
    if ($amount <= 0) return '#3b82f6';
    $pct = $spent / $amount * 100;
    if ($pct >= 100) return '#f43f5e';
    if ($pct >= 75)  return '#f59e0b';
    return '#22c55e';
};

include __DIR__ . '/../partials/nav.php';
?>

<main class="module-content">
    <header class="module-header">
        <h1>Budget</h1>
        <p>Track spending limits by category for <?= htmlspecialchars($monthName . ' ' . $selectedYear) ?>.</p>
    </header>

    <!-- Month selector -->
    <section class="module-panel" style="padding-bottom:1rem;">
        <form method="get" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:flex-end;">
            <input type="hidden" name="module" value="budget">
            <label style="margin:0;">
                <span style="display:block;font-size:0.78rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:0.35rem;">Month</span>
                <select name="bm">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $selectedMonth ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </label>
            <label style="margin:0;">
                <span style="display:block;font-size:0.78rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:0.35rem;">Year</span>
                <input type="number" name="by" value="<?= $selectedYear ?>" min="2000" max="2099" style="width:6.5rem;">
            </label>
            <button type="submit">View</button>
        </form>
    </section>

    <!-- Summary cards -->
    <section class="summary-cards">
        <article class="card card--cyan">
            <h3>Total budgeted</h3>
            <p><?= formatCurrency($totalBudgeted) ?></p>
            <small><?= count($budgets) ?> budget<?= count($budgets) !== 1 ? 's' : '' ?></small>
        </article>
        <article class="card <?= $totalSpent > $totalBudgeted ? 'card--red' : 'card--green' ?>">
            <h3>Total spent</h3>
            <p><?= formatCurrency($totalSpent) ?></p>
            <small><?= $overallPct ?>% of budget used</small>
        </article>
        <article class="card <?= $remaining > 0 ? 'card--green' : 'card--red' ?>">
            <h3>Remaining</h3>
            <p><?= formatCurrency($remaining) ?></p>
            <small><?= $totalSpent > $totalBudgeted ? 'Over budget' : 'Left to spend' ?></small>
        </article>
        <?php if ($overCount > 0): ?>
        <article class="card card--red">
            <h3>Over budget</h3>
            <p><?= $overCount ?></p>
            <small>categor<?= $overCount === 1 ? 'y' : 'ies' ?> exceeded</small>
        </article>
        <?php endif; ?>
    </section>

    <!-- Budget list -->
    <?php if (!empty($budgets)): ?>
    <section class="module-panel">
        <h2>Budget breakdown — <?= htmlspecialchars($monthName . ' ' . $selectedYear) ?></h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Period</th>
                        <th>Budgeted</th>
                        <th>Spent</th>
                        <th>Remaining</th>
                        <th style="min-width:120px;">Progress</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($budgets as $b):
                        $spent     = (float) $b['spent'];
                        $amount    = (float) $b['amount'];
                        $rem       = $amount - $spent;
                        $pct       = $amount > 0 ? min(100, round($spent / $amount * 100, 1)) : 0;
                        $barColor  = $budgetBarColor($spent, $amount);
                        $isOver    = $spent >= $amount && $amount > 0;
                        $recurring = ($b['month'] === null && $b['year'] === null);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($b['name']) ?></td>
                        <td><?= $b['category_name'] ? htmlspecialchars($b['category_name']) : '<span class="muted">All categories</span>' ?></td>
                        <td>
                            <?php if ($recurring): ?>
                                <span style="font-size:0.78rem;color:var(--muted);">Recurring</span>
                            <?php else: ?>
                                <span style="font-size:0.78rem;color:var(--muted);">
                                    <?= date('M', mktime(0,0,0,(int)$b['month'],1)) ?> <?= $b['year'] ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatCurrency($amount) ?></td>
                        <td style="color:<?= $isOver ? '#f43f5e' : 'inherit' ?>"><?= formatCurrency($spent) ?></td>
                        <td style="color:<?= $rem < 0 ? '#f43f5e' : '#22c55e' ?>"><?= formatCurrency(abs($rem)) ?><?= $rem < 0 ? ' over' : '' ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <div style="flex:1;background:rgba(255,255,255,0.07);border-radius:6px;height:8px;min-width:80px;">
                                    <div style="background:<?= $barColor ?>;border-radius:6px;height:8px;width:<?= $pct ?>%;max-width:100%;"></div>
                                </div>
                                <span style="font-size:0.78rem;color:var(--muted);white-space:nowrap;"><?= $pct ?>%</span>
                            </div>
                        </td>
                        <td>
                            <a href="?module=budget&edit=<?= $b['id'] ?>&bm=<?= $selectedMonth ?>&by=<?= $selectedYear ?>" style="margin-right:0.5rem;">Edit</a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this budget?')">
                                <input type="hidden" name="form"  value="budget_delete">
                                <input type="hidden" name="id"    value="<?= $b['id'] ?>">
                                <input type="hidden" name="month" value="<?= $selectedMonth ?>">
                                <input type="hidden" name="year"  value="<?= $selectedYear ?>">
                                <button type="submit" style="background:none;border:none;color:#f43f5e;cursor:pointer;padding:0;font-size:inherit;">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php else: ?>
    <section class="module-panel">
        <p class="muted">No budgets set for <?= htmlspecialchars($monthName . ' ' . $selectedYear) ?>. Add one below.</p>
    </section>
    <?php endif; ?>

    <!-- Add / Edit form -->
    <section class="module-panel">
        <h2><?= $editBudget ? 'Edit budget' : 'Add a budget' ?></h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form"  value="<?= $editBudget ? 'budget_update' : 'budget' ?>">
            <?php if ($editBudget): ?>
                <input type="hidden" name="id" value="<?= (int) $editBudget['id'] ?>">
            <?php endif; ?>

            <label>
                Name
                <input type="text" name="name" value="<?= htmlspecialchars($editBudget['name'] ?? '') ?>" placeholder="e.g. Groceries limit" required>
            </label>

            <label>
                Category
                <select name="category_id">
                    <option value="">All categories (overall)</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"
                            <?= (int)($editBudget['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Amount (₹)
                <input type="number" name="amount" step="0.01" min="0.01"
                       value="<?= htmlspecialchars((string)($editBudget['amount'] ?? '')) ?>"
                       placeholder="e.g. 5000" required>
            </label>

            <label style="flex-direction:row;align-items:center;gap:0.6rem;cursor:pointer;" id="recurring-label">
                <input type="checkbox" name="recurring" id="recurring-check" value="1"
                       <?= ($editBudget && $editBudget['month'] === null && $editBudget['year'] === null) ? 'checked' : '' ?>>
                Recurring every month
            </label>

            <div id="month-year-fields" style="display:flex;flex-wrap:wrap;gap:0.75rem;">
                <label style="margin:0;flex:1;min-width:150px;">
                    Month
                    <select name="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>"
                                <?= (int)($editBudget['month'] ?? $selectedMonth) === $m ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </label>
                <label style="margin:0;flex:1;min-width:120px;">
                    Year
                    <input type="number" name="year" min="2000" max="2099"
                           value="<?= (int)($editBudget['year'] ?? $selectedYear) ?>">
                </label>
            </div>

            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                <button type="submit"><?= $editBudget ? 'Update budget' : 'Add budget' ?></button>
                <?php if ($editBudget): ?>
                    <a href="?module=budget&bm=<?= $selectedMonth ?>&by=<?= $selectedYear ?>"
                       style="padding:0.7rem 1.2rem;background:rgba(255,255,255,0.06);border-radius:8px;text-decoration:none;color:var(--muted);">
                        Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </section>
</main>

<script>
(function () {
    var check = document.getElementById('recurring-check');
    var fields = document.getElementById('month-year-fields');

    function toggle() {
        fields.style.display = check.checked ? 'none' : 'flex';
    }

    toggle();
    check.addEventListener('change', toggle);
})();
</script>
