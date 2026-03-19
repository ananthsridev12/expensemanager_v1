<?php
$activeModule = 'reminders';
$upcoming = $upcoming ?? [];
$allReminders = $allReminders ?? [];
$total = $total ?? 0;
$editReminder = $editReminder ?? null;

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>Reminders</h1>
        <p>Keep track of upcoming bills, EMIs, and recurring obligations.</p>
    </header>

    <section class="summary-cards">
        <article class="card">
            <h3>Total reminders</h3>
            <p><?= $total ?></p>
        </article>
        <article class="card">
            <h3>Next due</h3>
            <?php if (!empty($upcoming)): ?>
                <p><?= htmlspecialchars($upcoming[0]['name']) ?></p>
                <small><?= htmlspecialchars($upcoming[0]['next_due_date']) ?></small>
            <?php else: ?>
                <p class="muted">None scheduled</p>
            <?php endif; ?>
        </article>
    </section>

    <section class="module-panel">
        <h2><?= $editReminder ? 'Edit reminder' : 'New reminder' ?></h2>
        <form method="post" class="module-form">
            <?php if ($editReminder): ?>
                <input type="hidden" name="form" value="reminder_update">
                <input type="hidden" name="id" value="<?= (int) $editReminder['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="form" value="reminder">
            <?php endif; ?>
            <label>
                Name
                <input type="text" name="name" required value="<?= htmlspecialchars($editReminder['name'] ?? '') ?>">
            </label>
            <label>
                Amount
                <input type="number" name="amount" step="0.01" min="0" value="<?= htmlspecialchars($editReminder['amount'] ?? '') ?>">
            </label>
            <label>
                Frequency
                <select name="frequency">
                    <option value="once" <?= ($editReminder['frequency'] ?? '') === 'once' ? 'selected' : '' ?>>Once</option>
                    <option value="monthly" <?= ($editReminder['frequency'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    <option value="quarterly" <?= ($editReminder['frequency'] ?? '') === 'quarterly' ? 'selected' : '' ?>>Quarterly</option>
                    <option value="yearly" <?= ($editReminder['frequency'] ?? '') === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                </select>
            </label>
            <label>
                Next due date
                <input type="date" name="next_due_date" value="<?= htmlspecialchars($editReminder['next_due_date'] ?? date('Y-m-d')) ?>" required>
            </label>
            <label>
                Status
                <select name="status">
                    <option value="upcoming" <?= ($editReminder['status'] ?? 'upcoming') === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                    <option value="completed" <?= ($editReminder['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="missed" <?= ($editReminder['status'] ?? '') === 'missed' ? 'selected' : '' ?>>Missed</option>
                </select>
            </label>
            <label>
                Notes
                <textarea name="notes" rows="2"><?= htmlspecialchars($editReminder['notes'] ?? '') ?></textarea>
            </label>
            <button type="submit"><?= $editReminder ? 'Update reminder' : 'Save reminder' ?></button>
            <?php if ($editReminder): ?>
                <a class="secondary" href="?module=reminders">Cancel</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="module-panel">
        <h2>All reminders</h2>
        <?php if (empty($allReminders)): ?>
            <p class="muted">No reminders scheduled yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Due</th>
                            <th>Frequency</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allReminders as $reminder): ?>
                            <tr>
                                <td><?= htmlspecialchars($reminder['name']) ?></td>
                                <td><?= htmlspecialchars($reminder['next_due_date']) ?></td>
                                <td><?= htmlspecialchars($reminder['frequency']) ?></td>
                                <td><?= formatCurrency((float) $reminder['amount']) ?></td>
                                <td><?= htmlspecialchars(ucfirst($reminder['status'])) ?></td>
                                <td><a class="secondary" href="?module=reminders&edit=<?= (int) $reminder['id'] ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
