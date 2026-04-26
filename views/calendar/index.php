<?php
$activeModule = 'calendar';
$year         = $year  ?? (int) date('Y');
$month        = $month ?? (int) date('n');
$dailyData    = $dailyData    ?? [];
$monthIncome  = $monthIncome  ?? 0.0;
$monthExpense = $monthExpense ?? 0.0;
$monthNet     = $monthIncome - $monthExpense;

// Month navigation
$prevMonth = $month - 1;
$prevYear  = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1;
$nextYear  = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$monthLabel    = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$today         = date('Y-m-d');
$firstDow      = (int) date('w', mktime(0, 0, 0, $month, 1, $year)); // 0=Sun
$daysInMonth   = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
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

            // Leading blank cells
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

                // Determine cell class
                $cellClass = 'cal-cell';
                if ($inc > 0 && $exp > 0) {
                    $cellClass .= ' cal-cell--both';
                } elseif ($inc > 0) {
                    $cellClass .= ' cal-cell--income';
                } elseif ($exp > 0) {
                    $cellClass .= ' cal-cell--expense';
                }
                if ($isToday) {
                    $cellClass .= ' cal-cell--today';
                }

                $txLink = '?module=all_transactions&start_date=' . urlencode($dateStr) . '&end_date=' . urlencode($dateStr);

                echo '<td class="' . $cellClass . '">';
                echo '<a href="' . htmlspecialchars($txLink) . '">';
                echo '<span class="cal-date' . ($isToday ? ' today' : '') . '">' . $day . '</span>';
                if ($inc > 0) {
                    echo '<span class="cal-income">&#9650; ' . formatCurrency($inc) . '</span>';
                }
                if ($exp > 0) {
                    echo '<span class="cal-expense">&#9660; ' . formatCurrency($exp) . '</span>';
                }
                echo '</a></td>';

                $cellCount++;
                if ($cellCount % 7 === 0 && $day < $daysInMonth) {
                    echo '</tr><tr>';
                }
            }

            // Trailing blank cells to complete the last row
            $remaining = (7 - ($cellCount % 7)) % 7;
            for ($i = 0; $i < $remaining; $i++) {
                echo '<td class="cal-cell cal-cell--pad"></td>';
            }
            echo '</tr>';
            ?>
            </tbody>
        </table>
    </section>
</main>
