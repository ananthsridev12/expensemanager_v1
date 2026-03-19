<?php
$activeModule = 'sip';
$schedules = $schedules ?? [];
$upcoming = $upcoming ?? [];
$investments = $investments ?? [];
$accounts = $accounts ?? [];
$editSip = $editSip ?? null;

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>SIP</h1>
        <p>Schedule recurring investments so the ledger records each SIP debit automatically.</p>
    </header>

    <section class="module-panel">
        <h2><?= $editSip ? 'Edit SIP schedule' : 'New SIP schedule' ?></h2>
        <form method="post" class="module-form">
            <?php if ($editSip): ?>
                <input type="hidden" name="form" value="sip_update">
                <input type="hidden" name="id" value="<?= (int) $editSip['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="form" value="sip">
            <?php endif; ?>
            <?php if (!$editSip): ?>
                <label>
                    Investment
                    <select name="investment_id" required>
                        <?php foreach ($investments as $investment): ?>
                            <option value="<?= $investment['id'] ?>"><?= htmlspecialchars($investment['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Account
                    <select name="account_id" required>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['bank_name'] . ' - ' . $account['account_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Start date
                    <input type="date" name="start_date" value="<?= date('Y-m-d') ?>">
                </label>
            <?php endif; ?>
            <label>
                SIP amount
                <input type="number" name="sip_amount" step="0.01" required value="<?= htmlspecialchars($editSip['sip_amount'] ?? '') ?>">
            </label>
            <label>
                SIP day
                <input type="number" name="sip_day" min="1" max="28" value="<?= htmlspecialchars($editSip['sip_day'] ?? '1') ?>">
            </label>
            <label>
                Frequency
                <select name="frequency">
                    <option value="monthly" <?= ($editSip['frequency'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    <option value="quarterly" <?= ($editSip['frequency'] ?? '') === 'quarterly' ? 'selected' : '' ?>>Quarterly</option>
                    <option value="yearly" <?= ($editSip['frequency'] ?? '') === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                </select>
            </label>
            <label>
                End date
                <input type="date" name="end_date" value="<?= htmlspecialchars($editSip['end_date'] ?? '') ?>">
            </label>
            <label>
                Next run date
                <input type="date" name="next_run_date" value="<?= htmlspecialchars($editSip['next_run_date'] ?? date('Y-m-d')) ?>">
            </label>
            <label>
                Status
                <select name="status">
                    <option value="active" <?= ($editSip['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="paused" <?= ($editSip['status'] ?? '') === 'paused' ? 'selected' : '' ?>>Paused</option>
                    <option value="ended" <?= ($editSip['status'] ?? '') === 'ended' ? 'selected' : '' ?>>Ended</option>
                </select>
            </label>
            <button type="submit"><?= $editSip ? 'Update SIP' : 'Schedule SIP' ?></button>
            <?php if ($editSip): ?>
                <a class="secondary" href="?module=sip">Cancel</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="module-panel">
        <h2>Schedules</h2>
        <?php if (empty($schedules)): ?>
            <p class="muted">No SIP schedules created yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Investment</th>
                            <th>Amount</th>
                            <th>Frequency</th>
                            <th>Next run</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <td><?= htmlspecialchars($schedule['investment_name'] ?? '?') ?></td>
                                <td><?= formatCurrency((float) $schedule['sip_amount']) ?></td>
                                <td><?= htmlspecialchars(ucfirst($schedule['frequency'])) ?></td>
                                <td><?= htmlspecialchars($schedule['next_run_date'] ?? '?') ?></td>
                                <td><?= htmlspecialchars(ucfirst($schedule['status'])) ?></td>
                                <td><a class="secondary" href="?module=sip&edit=<?= (int) $schedule['id'] ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Upcoming SIP run</h2>
        <?php if (empty($upcoming)): ?>
            <p class="muted">No upcoming SIP runs yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Investment</th>
                            <th>Next run</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming as $schedule): ?>
                            <tr>
                                <td><?= htmlspecialchars($schedule['investment_name'] ?? '?') ?></td>
                                <td><?= htmlspecialchars($schedule['next_run_date']) ?></td>
                                <td><?= formatCurrency((float) $schedule['sip_amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
