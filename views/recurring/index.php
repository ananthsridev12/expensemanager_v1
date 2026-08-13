<?php $activeModule = 'recurring'; ?>
<?php include __DIR__ . '/../partials/nav.php'; ?>

<style>
.pending-badge {
    display:inline-block; background:#ef4444; color:#fff;
    border-radius:9999px; font-size:0.7rem; font-weight:700;
    padding:1px 6px; margin-left:6px; vertical-align:middle;
}
.approve-banner {
    background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.3);
    border-radius:10px; padding:0.9rem 1.2rem; margin-bottom:1.5rem;
    color:#93c5fd; font-size:0.88rem; display:flex; align-items:center; gap:0.6rem;
}
.pending-card {
    background:#0f1a2e; border:1px solid rgba(120,150,210,0.15);
    border-radius:12px; padding:1.2rem 1.4rem; margin-bottom:1rem;
}
.pending-card h4 {
    margin:0 0 0.8rem; font-size:1rem; color:#e5ecff;
    display:flex; align-items:center; gap:0.5rem;
}
.pending-card .due-pill {
    font-size:0.72rem; background:rgba(234,179,8,0.15); color:#fde68a;
    border:1px solid rgba(234,179,8,0.3); border-radius:99px; padding:1px 8px;
    font-weight:600;
}
.pending-grid {
    display:grid; grid-template-columns:1fr 1fr; gap:0.6rem 1rem;
}
@media(max-width:500px){ .pending-grid{ grid-template-columns:1fr; } }
.pending-grid label { font-size:0.75rem; color:#7a94c4; text-transform:uppercase; letter-spacing:.05em; }
.pending-grid input, .pending-grid select, .pending-grid textarea {
    width:100%; box-sizing:border-box;
    background:#060b16; border:1px solid rgba(120,150,210,0.2);
    border-radius:7px; padding:0.5rem 0.7rem; color:#e5ecff; font-size:0.88rem; outline:none;
}
.pending-grid input:focus,.pending-grid select:focus,.pending-grid textarea:focus{border-color:#3b82f6;}
.pending-actions { display:flex; gap:0.6rem; margin-top:0.9rem; }
.btn-approve {
    padding:0.5rem 1.2rem; background:#22c55e; color:#fff;
    border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:0.88rem;
    transition:background .18s;
}
.btn-approve:hover{ background:#16a34a; }
.btn-skip {
    padding:0.5rem 1rem; background:transparent; color:#7a94c4;
    border:1px solid rgba(120,150,210,0.25); border-radius:8px;
    font-size:0.88rem; cursor:pointer; transition:border-color .18s;
}
.btn-skip:hover{ border-color:#7a94c4; color:#c7d8ff; }
.pending-msg { font-size:0.78rem; color:#f87171; margin-left:auto; align-self:center; display:none; }

.rec-table-wrap { overflow-x:auto; margin-top:0.5rem; }
.rec-table { width:100%; border-collapse:collapse; font-size:0.87rem; }
.rec-table th { text-align:left; color:#7a94c4; font-weight:600; padding:0.5rem 0.7rem; font-size:0.74rem; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid rgba(120,150,210,0.12); }
.rec-table td { padding:0.7rem 0.7rem; color:#c7d8ff; border-bottom:1px solid rgba(120,150,210,0.07); }
.rec-table tr:last-child td{ border-bottom:none; }
.rec-table .muted { color:#7a94c4; }
.badge-income { background:rgba(34,197,94,0.12); color:#4ade80; border-radius:99px; padding:1px 7px; font-size:0.74rem; font-weight:600; }
.badge-expense{ background:rgba(239,68,68,0.12); color:#f87171; border-radius:99px; padding:1px 7px; font-size:0.74rem; font-weight:600; }
.btn-deactivate {
    background:transparent; border:1px solid rgba(239,68,68,0.3); color:#f87171;
    border-radius:6px; padding:3px 10px; font-size:0.78rem; cursor:pointer;
    transition:background .15s;
}
.btn-deactivate:hover{ background:rgba(239,68,68,0.1); }

.create-form { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem 1rem; }
@media(max-width:540px){ .create-form{ grid-template-columns:1fr; } }
.create-form label { font-size:0.76rem; color:#7a94c4; text-transform:uppercase; letter-spacing:.05em; }
.create-form input,.create-form select,.create-form textarea {
    display:block; width:100%; box-sizing:border-box;
    background:#060b16; border:1px solid rgba(120,150,210,0.2);
    border-radius:8px; padding:0.65rem 0.85rem; color:#e5ecff; font-size:0.9rem; outline:none;
    transition:border-color .18s;
}
.create-form input:focus,.create-form select:focus,.create-form textarea:focus{border-color:#3b82f6;}
.create-form .full-col{ grid-column:1/-1; }
.create-form select option{ background:#0f1a2e; }
.btn-save {
    grid-column:1/-1; padding:0.82rem; background:#3b82f6; color:#fff;
    border:none; border-radius:10px; font-size:0.95rem; font-weight:600;
    cursor:pointer; transition:background .18s; margin-top:0.3rem;
}
.btn-save:hover{ background:#2563eb; }
.flash-msg {
    background:rgba(34,197,94,0.12); border:1px solid rgba(34,197,94,0.3);
    color:#4ade80; border-radius:9px; padding:0.7rem 1rem; font-size:0.87rem; margin-bottom:1.2rem;
}
</style>

<div class="module-wrapper">
    <h1 class="module-title">
        Recurring Transactions
        <?php if (!empty($pendingItems)): ?>
            <span class="pending-badge"><?= count($pendingItems) ?> pending</span>
        <?php endif; ?>
    </h1>

    <?php if ($msg): ?>
        <div class="flash-msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php // ── Pending approvals ─────────────────────────────────────────────── ?>
    <?php if (!empty($pendingItems)): ?>
    <section class="module-panel">
        <h2>Pending Approval</h2>
        <div class="approve-banner">
            &#128276;&nbsp;Review and approve or skip each due recurring transaction below.
        </div>

        <?php foreach ($pendingItems as $item): ?>
        <div class="pending-card" id="pcard-<?= (int) $item['pending_id'] ?>">
            <h4>
                <?= htmlspecialchars($item['name']) ?>
                <span class="due-pill">Due <?= htmlspecialchars($item['due_date']) ?></span>
                <span class="badge-<?= htmlspecialchars($item['transaction_type']) ?>"><?= ucfirst($item['transaction_type']) ?></span>
            </h4>
            <div class="pending-grid">
                <label>Date<br>
                    <input type="date" class="p-date" value="<?= htmlspecialchars($item['due_date']) ?>">
                </label>
                <label>Amount (&#8377;)<br>
                    <input type="number" class="p-amount" step="0.01" value="<?= number_format((float)$item['amount'], 2, '.', '') ?>">
                </label>
                <label>Category<br>
                    <select class="p-category">
                        <option value="">— none —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === (int)($item['category_id'] ?? 0) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Subcategory<br>
                    <select class="p-subcategory">
                        <option value="">— none —</option>
                        <?php if (!empty($item['category_id'])): ?>
                            <?php foreach ($categories as $cat): ?>
                                <?php if ((int)$cat['id'] === (int)$item['category_id']): ?>
                                    <?php foreach ($cat['subcategories'] ?? [] as $sc): ?>
                                        <option value="<?= (int)$sc['id'] ?>" <?= (int)$sc['id'] === (int)($item['subcategory_id'] ?? 0) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sc['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </label>
                <label class="full-col" style="grid-column:1/-1">Notes<br>
                    <input type="text" class="p-notes" value="<?= htmlspecialchars($item['notes'] ?? '') ?>" placeholder="optional note">
                </label>
            </div>
            <div style="font-size:0.78rem; color:#7a94c4; margin-top:0.5rem;">
                Account: <?= htmlspecialchars(($item['bank_name'] ?? '') . ' — ' . ($item['account_name'] ?? '')) ?>
                <?php if ($item['payment_method_name']): ?>&nbsp;&middot;&nbsp;<?= htmlspecialchars($item['payment_method_name']) ?><?php endif; ?>
                <?php if ($item['contact_name']): ?>&nbsp;&middot;&nbsp;<?= htmlspecialchars($item['contact_name']) ?><?php endif; ?>
            </div>
            <div class="pending-actions">
                <button type="button" class="btn-approve" onclick="approvePending(<?= (int)$item['pending_id'] ?>, this)">
                    &#10003; Approve
                </button>
                <button type="button" class="btn-skip" onclick="skipPending(<?= (int)$item['pending_id'] ?>, this)">
                    Skip
                </button>
                <span class="pending-msg" id="pmsg-<?= (int)$item['pending_id'] ?>"></span>
            </div>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php // ── Active templates ──────────────────────────────────────────────── ?>
    <section class="module-panel">
        <h2>Active Recurring Transactions</h2>
        <?php if (empty($activeTemplates)): ?>
            <p class="muted" style="font-size:0.88rem;">No recurring transactions set up yet.</p>
        <?php else: ?>
        <div class="rec-table-wrap">
            <table class="rec-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Account</th>
                        <th>Category</th>
                        <th>Frequency</th>
                        <th>Next Due</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeTemplates as $rec): ?>
                    <tr>
                        <td><?= htmlspecialchars($rec['name']) ?></td>
                        <td><span class="badge-<?= $rec['transaction_type'] ?>"><?= ucfirst($rec['transaction_type']) ?></span></td>
                        <td>&#8377;&nbsp;<?= number_format((float)$rec['amount'], 2) ?></td>
                        <td class="muted">
                            <?= htmlspecialchars(($rec['bank_name'] ?? '') . ($rec['account_name'] ? ' — ' . $rec['account_name'] : '')) ?>
                        </td>
                        <td class="muted"><?= htmlspecialchars($rec['category_name'] ?? '—') ?></td>
                        <td class="muted">
                            <?= ucfirst($rec['frequency']) ?>
                            <?php if ($rec['frequency'] === 'monthly' && !empty($rec['day_of_month'])): ?>
                                (<?= (int)$rec['day_of_month'] ?><?= in_array((int)$rec['day_of_month'], [1,21,31]) ? 'st' : (in_array((int)$rec['day_of_month'], [2,22]) ? 'nd' : (in_array((int)$rec['day_of_month'], [3,23]) ? 'rd' : 'th')) ?>)
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($rec['next_due_date']) ?></td>
                        <td>
                            <form method="post" style="margin:0;" onsubmit="return confirm('Deactivate this recurring transaction?')">
                                <input type="hidden" name="form" value="deactivate">
                                <input type="hidden" name="id" value="<?= (int)$rec['id'] ?>">
                                <button type="submit" class="btn-deactivate">Deactivate</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <?php // ── Create new recurring transaction ──────────────────────────────── ?>
    <section class="module-panel">
        <h2>Add Recurring Transaction</h2>
        <form method="post" class="create-form">
            <input type="hidden" name="form" value="create">

            <label class="full-col">Name / Description
                <input type="text" name="name" placeholder="e.g. Netflix subscription" required>
            </label>

            <label>Transaction Type
                <select name="transaction_type">
                    <option value="expense" selected>Expense</option>
                    <option value="income">Income</option>
                </select>
            </label>

            <label>Amount (&#8377;)
                <input type="number" name="amount" step="0.01" min="0.01" required>
            </label>

            <label class="full-col">Account
                <select name="account_token" required>
                    <option value="">— select account —</option>
                    <?php foreach (groupAccountsForSelect($accounts) as $grp): ?>
                        <optgroup label="<?= htmlspecialchars($grp['label']) ?>">
                            <?php foreach ($grp['accounts'] as $acc): ?>
                                <option value="<?= htmlspecialchars($acc['account_type'] . ':' . $acc['id']) ?>">
                                    <?= htmlspecialchars($acc['bank_name'] . ' — ' . $acc['account_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Category
                <select name="category_id" id="cr-category">
                    <option value="">— none —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Subcategory
                <select name="subcategory_id" id="cr-subcategory">
                    <option value="">— none —</option>
                </select>
            </label>

            <label>Payment Method
                <select name="payment_method_id">
                    <option value="">— none —</option>
                    <?php foreach ($paymentMethods as $pm): ?>
                        <option value="<?= (int)$pm['id'] ?>"><?= htmlspecialchars($pm['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Frequency
                <select name="frequency" id="cr-frequency" onchange="toggleDayField()">
                    <option value="monthly" selected>Monthly</option>
                    <option value="yearly">Yearly</option>
                    <option value="weekly">Weekly</option>
                    <option value="daily">Daily</option>
                </select>
            </label>

            <label id="cr-dom-wrap">Day of Month (1–31)
                <input type="number" name="day_of_month" id="cr-dom" min="1" max="31" placeholder="e.g. 5">
            </label>

            <label>First Due Date
                <input type="date" name="next_due_date" value="<?= date('Y-m-d') ?>" required>
            </label>

            <label>End Date (optional)
                <input type="date" name="end_date">
            </label>

            <label class="full-col">Notes
                <input type="text" name="notes" placeholder="optional">
            </label>

            <button type="submit" class="btn-save">&#43; Save Recurring Transaction</button>
        </form>
    </section>
</div>

<script>
// ── Subcategory map ─────────────────────────────────────────────────────────
const subcatMap = {
    <?php foreach ($categories as $cat): ?>
    <?= (int)$cat['id'] ?>: [<?php foreach ($cat['subcategories'] ?? [] as $sc): ?>{id:<?= (int)$sc['id'] ?>,name:<?= json_encode($sc['name']) ?>},<?php endforeach; ?>],
    <?php endforeach; ?>
};

document.getElementById('cr-category').addEventListener('change', function() {
    const sub = document.getElementById('cr-subcategory');
    const subs = subcatMap[this.value] || [];
    sub.innerHTML = '<option value="">— none —</option>';
    subs.forEach(s => {
        const o = document.createElement('option');
        o.value = s.id; o.textContent = s.name;
        sub.appendChild(o);
    });
});

function toggleDayField() {
    const freq = document.getElementById('cr-frequency').value;
    const wrap = document.getElementById('cr-dom-wrap');
    wrap.style.display = freq === 'monthly' ? '' : 'none';
}
toggleDayField();

// ── Approve / Skip via AJAX ─────────────────────────────────────────────────
async function approvePending(pendingId, btn) {
    const card   = document.getElementById('pcard-' + pendingId);
    const msgEl  = document.getElementById('pmsg-' + pendingId);
    const data   = new FormData();
    data.append('form',             'approve_pending');
    data.append('pending_id',       pendingId);
    data.append('transaction_date', card.querySelector('.p-date').value);
    data.append('amount',           card.querySelector('.p-amount').value);
    data.append('category_id',      card.querySelector('.p-category').value);
    data.append('subcategory_id',   card.querySelector('.p-subcategory').value);
    data.append('notes',            card.querySelector('.p-notes').value);

    btn.disabled = true; btn.textContent = '…';
    try {
        const res  = await fetch('?module=recurring', {method:'POST', body:data});
        const json = await res.json();
        if (json.ok) {
            card.style.transition = 'opacity .3s';
            card.style.opacity = '0';
            setTimeout(() => card.remove(), 300);
        } else {
            msgEl.textContent = json.error || 'Error';
            msgEl.style.display = 'block';
            btn.disabled = false; btn.textContent = '✓ Approve';
        }
    } catch(e) {
        msgEl.textContent = 'Request failed';
        msgEl.style.display = 'block';
        btn.disabled = false; btn.textContent = '✓ Approve';
    }
}

async function skipPending(pendingId, btn) {
    const card  = document.getElementById('pcard-' + pendingId);
    const msgEl = document.getElementById('pmsg-' + pendingId);
    const data  = new FormData();
    data.append('form',       'skip_pending');
    data.append('pending_id', pendingId);

    btn.disabled = true; btn.textContent = '…';
    try {
        const res  = await fetch('?module=recurring', {method:'POST', body:data});
        const json = await res.json();
        if (json.ok) {
            card.style.transition = 'opacity .3s';
            card.style.opacity = '0';
            setTimeout(() => card.remove(), 300);
        } else {
            msgEl.textContent = json.error || 'Error';
            msgEl.style.display = 'block';
            btn.disabled = false; btn.textContent = 'Skip';
        }
    } catch(e) {
        msgEl.textContent = 'Request failed';
        msgEl.style.display = 'block';
        btn.disabled = false; btn.textContent = 'Skip';
    }
}
</script>
