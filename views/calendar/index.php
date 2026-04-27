<?php
$activeModule = 'calendar';
$year         = $year  ?? (int) date('Y');
$month        = $month ?? (int) date('n');
$dailyData    = $dailyData    ?? [];
$monthIncome  = $monthIncome  ?? 0.0;
$monthExpense = $monthExpense ?? 0.0;
$monthNet     = $monthIncome - $monthExpense;

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1)  { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1;  $nextYear++; }

$monthLabel  = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$today       = date('Y-m-d');
$firstDow    = (int) date('w', mktime(0, 0, 0, $month, 1, $year));
$daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
?>
<?php include __DIR__ . '/../partials/nav.php'; ?>
<main class="module-content">
    <header class="module-header">
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
            <h1>Calendar</h1>
            <span style="color:var(--muted);font-size:0.95rem;"><?= htmlspecialchars($monthLabel) ?></span>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
            <a class="secondary" href="?module=calendar&year=<?= $prevYear ?>&month=<?= $prevMonth ?>">&#8592; Prev</a>
            <a class="secondary" href="?module=calendar">Today</a>
            <a class="secondary" href="?module=calendar&year=<?= $nextYear ?>&month=<?= $nextMonth ?>">Next &#8594;</a>
        </div>
    </header>

    <section class="summary-cards">
        <article class="card card--green">
            <h3>Income</h3>
            <p><?= formatCurrency($monthIncome) ?></p>
            <small>This month</small>
        </article>
        <article class="card card--red">
            <h3>Expense</h3>
            <p><?= formatCurrency($monthExpense) ?></p>
            <small>This month</small>
        </article>
        <article class="card <?= $monthNet >= 0 ? 'card--green' : 'card--red' ?>">
            <h3>Net</h3>
            <p><?= ($monthNet >= 0 ? '+' : '') . formatCurrency($monthNet) ?></p>
            <small><?= $monthNet >= 0 ? 'Surplus' : 'Deficit' ?></small>
        </article>
    </section>

    <section class="module-panel" style="overflow-x:auto;">
        <table class="cal-grid">
            <thead>
                <tr>
                    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow): ?>
                        <th><?= $dow ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $cellCount = 0;
            echo '<tr>';

            for ($i = 0; $i < $firstDow; $i++) {
                echo '<td class="cal-cell cal-cell--pad"></td>';
                $cellCount++;
            }

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $row     = $dailyData[$dateStr] ?? ['total_income' => 0, 'total_expense' => 0];
                $inc     = (float) $row['total_income'];
                $exp     = (float) $row['total_expense'];
                $isToday = ($dateStr === $today);

                $cellClass = 'cal-cell';
                if ($inc > 0 && $exp > 0)  $cellClass .= ' cal-cell--both';
                elseif ($inc > 0)           $cellClass .= ' cal-cell--income';
                elseif ($exp > 0)           $cellClass .= ' cal-cell--expense';
                if ($isToday)               $cellClass .= ' cal-cell--today';

                echo '<td class="' . $cellClass . '" data-date="' . $dateStr . '" role="button" tabindex="0">';
                echo '<span class="cal-date' . ($isToday ? ' today' : '') . '">' . $day . '</span>';
                if ($inc > 0) echo '<span class="cal-income">&#9650;&nbsp;' . calFmt($inc) . '</span>';
                if ($exp > 0) echo '<span class="cal-expense">&#9660;&nbsp;' . calFmt($exp) . '</span>';
                echo '</td>';

                $cellCount++;
                if ($cellCount % 7 === 0 && $day < $daysInMonth) echo '</tr><tr>';
            }

            $remaining = (7 - ($cellCount % 7)) % 7;
            for ($i = 0; $i < $remaining; $i++) {
                echo '<td class="cal-cell cal-cell--pad"></td>';
            }
            echo '</tr>';
            ?>
            </tbody>
        </table>
    </section>

    <!-- Day detail panel -->
    <section class="module-panel" id="cal-detail" style="display:none;">
        <div id="cal-detail-inner"></div>
    </section>
</main>

<?php
function calFmt(float $v): string {
    if ($v >= 100000) return '&#8377;' . number_format($v / 100000, 1) . 'L';
    if ($v >= 1000)   return '&#8377;' . number_format($v / 1000, 1) . 'k';
    return '&#8377;' . number_format($v, 0);
}
?>

<script>
(function () {
    var detail     = document.getElementById('cal-detail');
    var detailBody = document.getElementById('cal-detail-inner');
    var selected   = null;

    function fmtCurrency(v) {
        return '₹ ' + parseFloat(v).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function fmtDate(d) {
        var parts = d.split('-');
        var dt = new Date(parts[0], parts[1] - 1, parts[2]);
        return dt.toLocaleDateString('en-IN', {weekday:'long', year:'numeric', month:'long', day:'numeric'});
    }

    function typeLabel(t) {
        if (t === 'income')   return '<span style="color:var(--green);font-weight:600;">&#9650; Income</span>';
        if (t === 'expense')  return '<span style="color:var(--red);font-weight:600;">&#9660; Expense</span>';
        return '<span style="color:var(--muted);">&#8644; Transfer</span>';
    }

    function renderDetail(data) {
        var tx = data.transactions || [];
        var net = data.total_income - data.total_expense;

        var html = '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;">';
        html += '<strong style="font-size:1rem;">' + fmtDate(data.date) + '</strong>';
        html += '<div style="display:flex;gap:1rem;font-size:0.85rem;">';
        if (data.total_income  > 0) html += '<span style="color:var(--green);">&#9650; ' + fmtCurrency(data.total_income)  + '</span>';
        if (data.total_expense > 0) html += '<span style="color:var(--red);">&#9660; '   + fmtCurrency(data.total_expense) + '</span>';
        if (data.total_income > 0 && data.total_expense > 0) {
            var netColor = net >= 0 ? 'var(--green)' : 'var(--red)';
            html += '<span style="color:' + netColor + ';">Net: ' + (net >= 0 ? '+' : '') + fmtCurrency(net) + '</span>';
        }
        html += '</div></div>';

        if (tx.length === 0) {
            html += '<p style="color:var(--muted);text-align:center;padding:1.5rem 0;">No transactions on this day.</p>';
        } else {
            html += '<div class="table-wrap"><table class="data-table">';
            html += '<thead><tr><th>Type</th><th>Category</th><th>Notes</th><th>Account</th><th style="text-align:right;">Amount</th></tr></thead><tbody>';
            tx.forEach(function (r) {
                var amtColor = r.transaction_type === 'income' ? 'var(--green)' : r.transaction_type === 'expense' ? 'var(--red)' : 'var(--muted)';
                html += '<tr>';
                html += '<td>' + typeLabel(r.transaction_type) + '</td>';
                html += '<td>' + (r.category_name ? esc(r.category_name) : '<span style="color:var(--muted)">—</span>') + '</td>';
                html += '<td style="color:var(--muted);font-size:0.85rem;">' + (r.notes ? esc(r.notes) : '—') + '</td>';
                html += '<td style="font-size:0.82rem;color:var(--muted);">' + (r.account_display ? esc(r.account_display) : '—') + '</td>';
                html += '<td style="text-align:right;font-weight:600;color:' + amtColor + ';">' + fmtCurrency(r.amount) + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
        }

        detailBody.innerHTML = html;
        detail.style.display = '';
        detail.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function loadDay(dateStr, cell) {
        if (selected) selected.classList.remove('cal-cell--selected');
        selected = cell;
        cell.classList.add('cal-cell--selected');

        detail.style.display = '';
        detailBody.innerHTML = '<p style="color:var(--muted);text-align:center;padding:1.5rem 0;">Loading&hellip;</p>';
        detail.scrollIntoView({behavior: 'smooth', block: 'nearest'});

        fetch('?module=calendar&action=day_detail&date=' + encodeURIComponent(dateStr))
            .then(function (r) { return r.json(); })
            .then(renderDetail)
            .catch(function () {
                detailBody.innerHTML = '<p style="color:var(--red);text-align:center;padding:1rem 0;">Failed to load. Please try again.</p>';
            });
    }

    document.querySelectorAll('.cal-cell[data-date]').forEach(function (cell) {
        cell.addEventListener('click', function () {
            loadDay(cell.dataset.date, cell);
        });
        cell.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); loadDay(cell.dataset.date, cell); }
        });
    });

    // Auto-load today if present
    var todayCell = document.querySelector('.cal-cell--today');
    if (todayCell && todayCell.dataset.date) loadDay(todayCell.dataset.date, todayCell);
})();
</script>
