<?php
$activeModule  = 'borrowing';
$records       = $records       ?? [];
$openRecords   = $openRecords   ?? [];
$allRepayments = $allRepayments ?? [];
$accounts      = $accounts      ?? [];
$summary       = $summary       ?? ['count' => 0, 'outstanding' => 0.0];
$editRecord    = $editRecord    ?? null;
$smtpReady     = $smtpReady     ?? false;
$flash         = $flash         ?? null;

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>Borrowings</h1>
        <p>Track money you have borrowed from friends or relatives, and repayments you've made.</p>
    </header>

    <?php if ($flash): ?>
        <div class="flash-message flash-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <section class="summary-cards">
        <article class="card">
            <h3>Borrowing records</h3>
            <p><?= $summary['count'] ?></p>
        </article>
        <article class="card card--red">
            <h3>I owe (outstanding)</h3>
            <p><?= formatCurrency($summary['outstanding']) ?></p>
            <small>Total across all records</small>
        </article>
    </section>

    <?php if ($editRecord): ?>
    <section class="module-panel">
        <h2>Edit borrowing record</h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="borrowing_update">
            <input type="hidden" name="id"   value="<?= (int) $editRecord['id'] ?>">
            <label>
                Contact (borrowed from)
                <input type="text" value="<?= htmlspecialchars($editRecord['contact_name'] ?? '') ?>" disabled>
            </label>
            <label>
                Borrowed date
                <input type="date" name="borrowed_date" value="<?= htmlspecialchars($editRecord['borrowed_date'] ?? '') ?>" required>
            </label>
            <label>
                Interest rate (%)
                <input type="number" name="interest_rate" step="0.01" value="<?= htmlspecialchars($editRecord['interest_rate'] ?? '0') ?>">
            </label>
            <label>
                Due date
                <input type="date" name="due_date" value="<?= htmlspecialchars($editRecord['due_date'] ?? '') ?>">
            </label>
            <label>
                Status
                <select name="status">
                    <option value="ongoing"  <?= ($editRecord['status'] ?? '') === 'ongoing'  ? 'selected' : '' ?>>Ongoing</option>
                    <option value="closed"   <?= ($editRecord['status'] ?? '') === 'closed'   ? 'selected' : '' ?>>Closed</option>
                </select>
            </label>
            <label>
                Notes
                <textarea name="notes" rows="2"><?= htmlspecialchars($editRecord['notes'] ?? '') ?></textarea>
            </label>
            <button type="submit">Update record</button>
            <a class="secondary" href="?module=borrowing">Cancel</a>
        </form>
    </section>
    <?php endif; ?>

    <!-- Add borrowing -->
    <section class="module-panel">
        <h2>Record a new borrowing</h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="borrowing">
            <label>
                Search contact (who you borrowed from)
                <input type="text" id="contact-search" placeholder="Type name / mobile / email" autocomplete="off">
            </label>
            <input type="hidden" name="contact_id" id="contact-id" required>
            <label>
                Matched contacts
                <div id="contact-results" class="module-placeholder">
                    <small class="muted">Start typing to search contacts.</small>
                </div>
            </label>
            <label>
                Amount borrowed (₹)
                <input type="number" name="principal_amount" step="0.01" min="0.01" required>
            </label>
            <label>
                Interest rate (%)
                <input type="number" name="interest_rate" step="0.01" value="0">
            </label>
            <label>
                Borrowed date
                <input type="date" name="borrowed_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>
                Received into account
                <select name="deposit_account">
                    <option value="">Select account (optional)</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= htmlspecialchars(($account['account_type'] ?? 'savings') . ':' . $account['id']) ?>">
                            <?= htmlspecialchars(($account['bank_name'] ?? '') . ' – ' . ($account['account_name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Due date
                <input type="date" name="due_date">
            </label>
            <label>
                Notes
                <textarea name="notes" rows="2"></textarea>
            </label>
            <button type="submit">Record borrowing</button>
        </form>
    </section>

    <!-- Make repayment -->
    <section class="module-panel">
        <h2>Make a repayment</h2>
        <?php if (empty($openRecords)): ?>
            <p class="muted">No open borrowings to repay.</p>
        <?php else: ?>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="borrowing_repayment">
            <label>
                Borrowing record
                <select name="borrowing_record_id" required>
                    <option value="">Choose record</option>
                    <?php foreach ($openRecords as $open): ?>
                        <option value="<?= (int) $open['id'] ?>">
                            <?= htmlspecialchars($open['contact_name']) ?> — Outstanding <?= formatCurrency((float) $open['outstanding_amount']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Repayment amount (₹)
                <input type="number" name="repayment_amount" step="0.01" min="0.01" required>
            </label>
            <label>
                Repayment date
                <input type="date" name="repayment_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>
                Pay from account
                <select name="payment_account">
                    <option value="">Select account (optional)</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= htmlspecialchars(($account['account_type'] ?? 'savings') . ':' . $account['id']) ?>">
                            <?= htmlspecialchars(($account['bank_name'] ?? '') . ' – ' . ($account['account_name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Notes
                <textarea name="notes" rows="2"></textarea>
            </label>
            <?php if ($smtpReady): ?>
            <label style="display:flex;flex-direction:row;align-items:center;gap:0.5rem;">
                <input type="checkbox" name="send_email" value="1" checked>
                Notify lender by email
            </label>
            <?php endif; ?>
            <button type="submit">Record repayment</button>
        </form>
        <?php endif; ?>
    </section>

    <!-- Borrowings ledger -->
    <section class="module-panel">
        <h2>Borrowings ledger</h2>
        <?php if (empty($records)): ?>
            <p class="muted">No borrowing records yet.</p>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Borrowed from</th>
                        <th>Date</th>
                        <th>Principal</th>
                        <th>Repaid</th>
                        <th>Outstanding</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $rec):
                        $outstanding = (float) $rec['outstanding_amount'];
                        $isOverdue   = !empty($rec['due_date']) && $rec['due_date'] < date('Y-m-d') && $rec['status'] === 'ongoing';
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($rec['contact_name']) ?></strong><br>
                            <small class="muted"><?= htmlspecialchars($rec['mobile'] ?? '') ?></small>
                        </td>
                        <td><?= htmlspecialchars($rec['borrowed_date']) ?></td>
                        <td><?= formatCurrency((float) $rec['principal_amount']) ?></td>
                        <td style="color:var(--green)"><?= formatCurrency((float) $rec['total_repaid']) ?></td>
                        <td style="color:<?= $outstanding > 0 ? 'var(--red,#f43f5e)' : 'var(--green)' ?>">
                            <strong><?= formatCurrency($outstanding) ?></strong>
                        </td>
                        <td>
                            <?php if ($isOverdue): ?>
                                <span style="color:#f43f5e;font-weight:600;"><?= htmlspecialchars($rec['due_date']) ?> ⚠</span>
                            <?php else: ?>
                                <?= htmlspecialchars($rec['due_date'] ?? '—') ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="pill <?= $rec['status'] === 'closed' ? 'pill--green' : 'pill--muted' ?>">
                                <?= htmlspecialchars(ucfirst($rec['status'])) ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap;">
                            <a class="secondary" href="?module=borrowing&edit=<?= (int) $rec['id'] ?>">Edit</a>
                            <?php if ($rec['status'] === 'ongoing' && !empty($rec['mobile'])): ?>
                            <?php
                                $waBal = number_format((float) $rec['outstanding_amount'], 2, '.', ',');
                                $waMsg = 'Hi ' . $rec['contact_name'] . ', just to confirm our outstanding repayment to you is Rs.' . $waBal
                                    . (!empty($rec['due_date']) ? ', due by ' . date('d M Y', strtotime($rec['due_date'])) : '')
                                    . '. We will arrange payment. Thank you.';
                            ?>
                            <a href="<?= htmlspecialchars(whatsappLink($rec['mobile'], $waMsg)) ?>"
                               target="_blank" rel="noopener" class="secondary"
                               style="font-size:0.75rem;padding:0.2rem 0.6rem;margin-left:0.4rem;">WA</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- Repayment history -->
    <section class="module-panel">
        <h2>Repayment history</h2>
        <?php if (empty($allRepayments)): ?>
            <p class="muted">No repayments made yet.</p>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Paid to</th>
                        <th>Amount</th>
                        <th>Paid from</th>
                        <th>Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allRepayments as $rep): ?>
                    <tr>
                        <td><?= htmlspecialchars($rep['repayment_date']) ?></td>
                        <td><?= htmlspecialchars($rep['contact_name']) ?></td>
                        <td style="color:#f43f5e"><?= formatCurrency((float) $rep['amount']) ?></td>
                        <td>
                            <?php if (!empty($rep['payment_account_type'])): ?>
                                <span class="pill pill--muted"><?= htmlspecialchars(ucfirst($rep['payment_account_type'])) ?> #<?= (int) $rep['payment_account_id'] ?></span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($rep['notes'] ?? '—') ?></td>
                        <td style="white-space:nowrap;">
                            <?php if (!empty($rep['mobile'])): ?>
                            <?php
                                $waAmt  = 'Rs.' . number_format((float) $rep['amount'], 2, '.', ',');
                                $waDate = date('d M Y', strtotime($rep['repayment_date']));
                                $waMsg  = 'Hi ' . $rep['contact_name'] . ', we have made a repayment of ' . $waAmt . ' on ' . $waDate . '. Kindly acknowledge receipt. Thank you.';
                            ?>
                            <a href="<?= htmlspecialchars(whatsappLink($rep['mobile'], $waMsg)) ?>"
                               target="_blank" rel="noopener" class="secondary"
                               style="font-size:0.75rem;padding:0.2rem 0.6rem;">WA</a>
                            <?php endif; ?>
                            <?php if ($smtpReady && !empty($rep['email'])): ?>
                            <form method="post" style="display:inline;margin-left:0.3rem;">
                                <input type="hidden" name="form" value="borrowing_resend_email">
                                <input type="hidden" name="repayment_id" value="<?= (int) $rep['repayment_id'] ?>">
                                <button type="submit" class="secondary" style="font-size:0.75rem;padding:0.2rem 0.6rem;">Email</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline;margin-left:0.3rem;" onsubmit="return confirm('Void this repayment? This cannot be undone.');">
                                <input type="hidden" name="form" value="borrowing_void_repayment">
                                <input type="hidden" name="repayment_id" value="<?= (int) $rep['repayment_id'] ?>">
                                <button type="submit" class="secondary" style="font-size:0.75rem;padding:0.2rem 0.6rem;color:#f43f5e;">Void</button>
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
    const searchInput  = document.getElementById('contact-search');
    const contactId    = document.getElementById('contact-id');
    const resultsWrap  = document.getElementById('contact-results');
    if (!searchInput) return;

    let timer = null;

    function renderResults(items) {
        resultsWrap.innerHTML = '';
        if (!items.length) {
            resultsWrap.innerHTML = '<small class="muted">No contacts found.</small>';
            return;
        }
        items.forEach(function (item) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'secondary';
            btn.textContent = item.name + (item.mobile ? ' – ' + item.mobile : '');
            btn.style.cssText = 'margin-right:0.5rem;margin-bottom:0.5rem;';
            btn.addEventListener('click', function () {
                contactId.value   = String(item.id);
                searchInput.value = btn.textContent;
                resultsWrap.innerHTML = '<small class="muted">Selected: ' + btn.textContent + '</small>';
            });
            resultsWrap.appendChild(btn);
        });
    }

    searchInput.addEventListener('input', function () {
        contactId.value = '';
        clearTimeout(timer);
        const q = searchInput.value.trim();
        if (q === '') {
            resultsWrap.innerHTML = '<small class="muted">Start typing to search contacts.</small>';
            return;
        }
        timer = setTimeout(async function () {
            try {
                const res   = await fetch('?module=borrowing&action=contact_search&q=' + encodeURIComponent(q));
                const items = await res.json();
                renderResults(Array.isArray(items) ? items : []);
            } catch (e) {
                resultsWrap.innerHTML = '<small class="muted">Search failed.</small>';
            }
        }, 250);
    });
})();
</script>
