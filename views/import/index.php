<?php
$activeModule   = 'import';
$accounts       = $accounts       ?? [];
$categories     = $categories     ?? [];
$paymentMethods = $paymentMethods ?? [];
$contacts       = $contacts       ?? [];
$batches        = $batches        ?? [];
$pendingRows    = $pendingRows    ?? [];
$parsers        = $parsers        ?? [];
$selectedBatch  = (int) ($selectedBatch ?? 0);
$msg            = $msg   ?? null;
$error          = $error ?? null;

// Pre-build account options with data-type attribute
$accountOptions = [];
foreach ($accounts as $acct) {
    $label = ($acct['bank_name'] ?? '') . ' — ' . ($acct['account_name'] ?? '') .
             ' (' . ($acct['account_type'] ?? '') . ')';
    $accountOptions[] = [
        'id'    => (int) $acct['id'],
        'label' => $label,
        'type'  => $acct['account_type'] ?? 'savings',
    ];
}

// Category data for JS subcategory filtering
$catForJs = [];
foreach ($categories as $cat) {
    $catForJs[(int) $cat['id']] = [
        'name' => $cat['name'],
        'type' => $cat['type'],
        'subs' => array_map(fn($s) => ['id' => (int) $s['id'], 'name' => $s['name']], $cat['subcategories']),
    ];
}

$pendingTotal = count($pendingRows);

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>Import Transactions</h1>
        <p>Upload a bank or credit-card statement, then review each transaction before it enters the ledger.</p>
    </header>

    <?php if ($msg): ?>
        <div style="background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.35);
                    border-radius:var(--radius);padding:0.75rem 1rem;margin-bottom:1rem;color:var(--green);">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.35);
                    border-radius:var(--radius);padding:0.75rem 1rem;margin-bottom:1rem;color:var(--red);">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- ── Upload ──────────────────────────────────────────────────────────── -->
    <section class="module-panel">
        <h2>Upload statement</h2>
        <?php if (empty($parsers)): ?>
            <p class="muted" style="margin-bottom:1rem;">No bank parsers installed yet.</p>
        <?php else: ?>
            <p style="color:var(--muted);font-size:0.88rem;margin-bottom:1rem;">
                Supported: <strong><?= htmlspecialchars(implode(', ', array_map(fn($c) => $c::name(), $parsers))) ?></strong>
            </p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="module-form">
            <input type="hidden" name="form" value="upload">
            <input type="hidden" name="account_type" id="account-type-hidden" value="savings">
            <label>
                Account
                <select name="account_id" id="account-select" required>
                    <option value="">— Select account —</option>
                    <?php foreach ($accountOptions as $opt): ?>
                        <option value="<?= $opt['id'] ?>" data-type="<?= htmlspecialchars($opt['type']) ?>">
                            <?= htmlspecialchars($opt['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Statement file <small class="muted">(.csv or .xlsx)</small>
                <input type="file" name="csv_file" accept=".csv,.xlsx" required>
            </label>
            <button type="submit">Stage for Review</button>
        </form>
    </section>

    <!-- ── Review queue ────────────────────────────────────────────────────── -->
    <section class="module-panel">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem;">
            <h2 style="margin:0;">
                Review Queue
                <span id="pending-badge" class="pill pill--<?= $pendingTotal > 0 ? 'yellow' : 'green' ?>"
                      style="font-size:0.75rem;margin-left:0.5rem;"><?= $pendingTotal ?> pending</span>
            </h2>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                <!-- Batch filter -->
                <form method="get" style="display:flex;gap:0.5rem;align-items:center;">
                    <input type="hidden" name="module" value="import">
                    <select name="batch" style="font-size:0.875rem;padding:0.3rem 0.6rem;" onchange="this.form.submit()">
                        <option value="0" <?= $selectedBatch === 0 ? 'selected' : '' ?>>All pending</option>
                        <?php foreach ($batches as $b): ?>
                            <?php if ((int)$b['pending_count'] > 0): ?>
                                <option value="<?= (int)$b['id'] ?>" <?= $selectedBatch === (int)$b['id'] ? 'selected' : '' ?>>
                                    #<?= (int)$b['id'] ?> — <?= htmlspecialchars($b['label']) ?> (<?= (int)$b['pending_count'] ?> pending)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if ($pendingTotal > 0): ?>
                    <button type="button" id="skip-all-dups-btn" class="secondary"
                            style="font-size:0.8rem;padding:0.3rem 0.75rem;">
                        Skip all duplicates
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($pendingTotal === 0): ?>
            <p class="muted">No pending transactions to review.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table id="review-table" style="min-width:1100px;">
                    <thead>
                        <tr>
                            <th style="min-width:120px;">Date</th>
                            <th style="min-width:220px;">Notes <small class="muted">(editable)</small></th>
                            <th style="min-width:100px;">Amount</th>
                            <th style="min-width:90px;">Type</th>
                            <th style="min-width:160px;">Category</th>
                            <th style="min-width:160px;">Subcategory</th>
                            <th style="min-width:140px;">Payment method</th>
                            <th style="min-width:140px;">Contact</th>
                            <th style="width:40px;"></th>
                            <th style="min-width:140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingRows as $row): ?>
                            <?php $sid = (int) $row['id']; ?>
                            <tr id="staging-row-<?= $sid ?>"
                                style="<?= $row['is_duplicate_flag'] ? 'background:rgba(234,179,8,0.06);' : '' ?>">
                                <!-- Date -->
                                <td>
                                    <input type="date" class="row-date" data-sid="<?= $sid ?>"
                                           value="<?= htmlspecialchars($row['transaction_date']) ?>"
                                           style="width:120px;font-size:0.82rem;padding:0.2rem 0.4rem;">
                                </td>
                                <!-- Notes (pre-filled with raw description) -->
                                <td>
                                    <textarea class="row-notes" data-sid="<?= $sid ?>"
                                              rows="2" style="width:100%;font-size:0.82rem;padding:0.2rem 0.4rem;resize:vertical;"
                                              title="<?= htmlspecialchars($row['description']) ?>"
                                              ><?= htmlspecialchars($row['notes'] ?? $row['description']) ?></textarea>
                                </td>
                                <!-- Amount -->
                                <td>
                                    <input type="number" class="row-amount" data-sid="<?= $sid ?>"
                                           value="<?= number_format((float)$row['amount'], 2, '.', '') ?>"
                                           step="0.01" min="0.01"
                                           style="width:90px;font-size:0.82rem;padding:0.2rem 0.4rem;">
                                </td>
                                <!-- Type -->
                                <td>
                                    <select class="row-type" data-sid="<?= $sid ?>"
                                            style="font-size:0.82rem;padding:0.2rem 0.4rem;">
                                        <option value="expense" <?= $row['transaction_type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                                        <option value="income"  <?= $row['transaction_type'] === 'income'  ? 'selected' : '' ?>>Income</option>
                                    </select>
                                </td>
                                <!-- Category -->
                                <td>
                                    <select class="row-category" data-sid="<?= $sid ?>"
                                            style="font-size:0.82rem;padding:0.2rem 0.4rem;width:100%;">
                                        <option value="">— Category —</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= (int)$cat['id'] ?>">
                                                <?= htmlspecialchars($cat['name']) ?> (<?= $cat['type'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <!-- Subcategory (populated by JS) -->
                                <td>
                                    <select class="row-subcategory" data-sid="<?= $sid ?>"
                                            style="font-size:0.82rem;padding:0.2rem 0.4rem;width:100%;">
                                        <option value="">— Subcat —</option>
                                    </select>
                                </td>
                                <!-- Payment method -->
                                <td>
                                    <select class="row-pm" data-sid="<?= $sid ?>"
                                            style="font-size:0.82rem;padding:0.2rem 0.4rem;width:100%;">
                                        <option value="">— Method —</option>
                                        <?php foreach ($paymentMethods as $pm): ?>
                                            <option value="<?= (int)$pm['id'] ?>"><?= htmlspecialchars($pm['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <!-- Contact -->
                                <td>
                                    <select class="row-contact" data-sid="<?= $sid ?>"
                                            style="font-size:0.82rem;padding:0.2rem 0.4rem;width:100%;">
                                        <option value="">— Contact —</option>
                                        <?php foreach ($contacts as $ct): ?>
                                            <option value="<?= (int)$ct['id'] ?>"><?= htmlspecialchars($ct['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <!-- Duplicate flag -->
                                <td style="text-align:center;">
                                    <?php if ($row['is_duplicate_flag']): ?>
                                        <span title="Possible duplicate — same date, amount and type already exists in this account"
                                              style="color:var(--yellow);font-size:1rem;cursor:help;">⚠</span>
                                    <?php endif; ?>
                                </td>
                                <!-- Actions -->
                                <td style="white-space:nowrap;">
                                    <button type="button" class="approve-btn"
                                            data-sid="<?= $sid ?>"
                                            style="font-size:0.78rem;padding:0.25rem 0.6rem;background:var(--green);color:#fff;border:none;border-radius:6px;cursor:pointer;">
                                        Approve
                                    </button>
                                    <button type="button" class="skip-btn"
                                            data-sid="<?= $sid ?>"
                                            style="font-size:0.78rem;padding:0.25rem 0.6rem;margin-left:0.25rem;"
                                            class="secondary">
                                        Skip
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- ── Import history ──────────────────────────────────────────────────── -->
    <section class="module-panel">
        <h2>Import history</h2>
        <?php if (empty($batches)): ?>
            <p class="muted">No imports yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Statement</th>
                            <th>Account</th>
                            <th style="color:var(--yellow);">Pending</th>
                            <th style="color:var(--green);">Approved</th>
                            <th class="muted">Skipped</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batches as $b): ?>
                            <tr>
                                <td><?= (int)$b['id'] ?></td>
                                <td><?= htmlspecialchars($b['label']) ?></td>
                                <td><?= htmlspecialchars(($b['acct_bank'] ?? '') . ' — ' . ($b['acct_name'] ?? '')) ?></td>
                                <td style="color:var(--yellow);"><?= (int)$b['pending_count'] ?></td>
                                <td style="color:var(--green);"><?= (int)$b['approved_count'] ?></td>
                                <td class="muted"><?= (int)$b['skipped_count'] ?></td>
                                <td><?= htmlspecialchars($b['created_at']) ?></td>
                                <td>
                                    <?php if ((int)$b['pending_count'] > 0): ?>
                                        <a class="secondary" style="font-size:0.78rem;padding:0.2rem 0.6rem;"
                                           href="?module=import&batch=<?= (int)$b['id'] ?>">Review</a>
                                    <?php endif; ?>
                                    <form method="post" style="display:inline;"
                                          onsubmit="return confirm('Roll back batch #<?= (int)$b['id'] ?>?\nThis deletes all <?= (int)$b['approved_count'] ?> approved transaction(s) and clears <?= (int)$b['pending_count'] + (int)$b['skipped_count'] ?> staged row(s).')">
                                        <input type="hidden" name="form" value="rollback">
                                        <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
                                        <button type="submit" class="secondary"
                                                style="font-size:0.78rem;padding:0.2rem 0.6rem;color:var(--red);">
                                            Rollback
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
(function () {
    // ── Category → subcategory mapping ──────────────────────────────────────
    const catData = <?= json_encode($catForJs, JSON_UNESCAPED_UNICODE) ?>;

    function fillSubcategories(catSelect, subSelect) {
        const catId = parseInt(catSelect.value, 10);
        subSelect.innerHTML = '<option value="">— Subcat —</option>';
        if (catData[catId] && catData[catId].subs.length) {
            catData[catId].subs.forEach(function (s) {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                subSelect.appendChild(opt);
            });
        }
    }

    document.querySelectorAll('.row-category').forEach(function (catSel) {
        const sid    = catSel.dataset.sid;
        const subSel = document.querySelector('.row-subcategory[data-sid="' + sid + '"]');
        catSel.addEventListener('change', function () { fillSubcategories(catSel, subSel); });
    });

    // ── Account type hidden field ────────────────────────────────────────────
    var accSel    = document.getElementById('account-select');
    var accHidden = document.getElementById('account-type-hidden');
    if (accSel && accHidden) {
        accSel.addEventListener('change', function () {
            var opt = accSel.options[accSel.selectedIndex];
            accHidden.value = opt ? (opt.dataset.type || 'savings') : 'savings';
        });
    }

    // ── Pending count badge ──────────────────────────────────────────────────
    var pendingCount = <?= $pendingTotal ?>;
    function updateBadge() {
        var badge = document.getElementById('pending-badge');
        if (!badge) return;
        badge.textContent = pendingCount + ' pending';
        badge.className = 'pill pill--' + (pendingCount > 0 ? 'yellow' : 'green');
    }

    function removeRow(sid) {
        var row = document.getElementById('staging-row-' + sid);
        if (row) row.remove();
        pendingCount = Math.max(0, pendingCount - 1);
        updateBadge();
        if (pendingCount === 0) {
            var tbody = document.querySelector('#review-table tbody');
            if (tbody && tbody.children.length === 0) {
                tbody.closest('.table-wrapper').innerHTML = '<p class="muted" style="padding:1rem 0;">All rows reviewed.</p>';
            }
        }
    }

    // ── Approve ──────────────────────────────────────────────────────────────
    document.querySelectorAll('.approve-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var sid = btn.dataset.sid;
            var fd  = new FormData();
            fd.append('form',              'approve_staging');
            fd.append('staging_id',        sid);
            fd.append('transaction_date',  document.querySelector('.row-date[data-sid="' + sid + '"]').value);
            fd.append('notes',             document.querySelector('.row-notes[data-sid="' + sid + '"]').value);
            fd.append('amount',            document.querySelector('.row-amount[data-sid="' + sid + '"]').value);
            fd.append('transaction_type',  document.querySelector('.row-type[data-sid="' + sid + '"]').value);
            fd.append('category_id',       document.querySelector('.row-category[data-sid="' + sid + '"]').value);
            fd.append('subcategory_id',    document.querySelector('.row-subcategory[data-sid="' + sid + '"]').value);
            fd.append('payment_method_id', document.querySelector('.row-pm[data-sid="' + sid + '"]').value);
            fd.append('contact_id',        document.querySelector('.row-contact[data-sid="' + sid + '"]').value);

            btn.disabled = true;
            btn.textContent = '…';

            fetch('?module=import', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        removeRow(sid);
                    } else {
                        alert(data.error || 'Approve failed.');
                        btn.disabled = false;
                        btn.textContent = 'Approve';
                    }
                })
                .catch(function () {
                    alert('Network error.');
                    btn.disabled = false;
                    btn.textContent = 'Approve';
                });
        });
    });

    // ── Skip ─────────────────────────────────────────────────────────────────
    document.querySelectorAll('.skip-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var sid = btn.dataset.sid;
            var fd  = new FormData();
            fd.append('form',       'skip_staging');
            fd.append('staging_id', sid);

            btn.disabled = true;

            fetch('?module=import', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        removeRow(sid);
                    } else {
                        alert(data.error || 'Skip failed.');
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    alert('Network error.');
                    btn.disabled = false;
                });
        });
    });

    // ── Skip all duplicates ───────────────────────────────────────────────────
    var skipDupsBtn = document.getElementById('skip-all-dups-btn');
    if (skipDupsBtn) {
        skipDupsBtn.addEventListener('click', function () {
            var dupRows = document.querySelectorAll('#review-table tbody tr[id^="staging-row-"] td span[title*="duplicate"]');
            if (dupRows.length === 0) { alert('No duplicate-flagged rows visible.'); return; }
            if (!confirm('Skip all ' + dupRows.length + ' duplicate-flagged row(s)?')) return;

            skipDupsBtn.disabled = true;
            var promises = [];
            dupRows.forEach(function (span) {
                var sid = span.closest('tr').id.replace('staging-row-', '');
                var fd  = new FormData();
                fd.append('form', 'skip_staging');
                fd.append('staging_id', sid);
                promises.push(
                    fetch('?module=import', { method: 'POST', body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (d) { if (d.ok) removeRow(sid); })
                );
            });
            Promise.all(promises).then(function () { skipDupsBtn.disabled = false; });
        });
    }
})();
</script>
