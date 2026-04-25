<?php
$activeModule  = 'lending';
$records       = $records ?? [];
$openRecords   = $openRecords ?? [];
$allRepayments = $allRepayments ?? [];
$accounts      = $accounts ?? [];
$summary       = $summary ?? ['count' => 0, 'outstanding' => 0.0];
$editRecord    = $editRecord ?? null;
$allLoans      = $allLoans ?? [];
$smtpReady     = $smtpReady ?? false;
$flash         = $flash ?? null;

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>Lending</h1>
        <p>Track funds you have lent and the outstanding amounts for friends or relatives.</p>
    </header>

    <?php if ($flash): ?>
        <div class="flash-message flash-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <section class="summary-cards">
        <article class="card">
            <h3>Lending records</h3>
            <p><?= $summary['count'] ?></p>
        </article>
        <article class="card">
            <h3>Outstanding</h3>
            <p><?= formatCurrency($summary['outstanding']) ?></p>
        </article>
    </section>

    <?php if ($editRecord): ?>
    <section class="module-panel">
        <h2>Edit lending record</h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="lending_update">
            <input type="hidden" name="id" value="<?= (int) $editRecord['id'] ?>">
            <label>
                Contact
                <input type="text" value="<?= htmlspecialchars($editRecord['contact_name'] ?? '') ?>" disabled>
            </label>
            <label>
                Lending date
                <input type="date" name="lending_date" value="<?= htmlspecialchars($editRecord['lending_date'] ?? '') ?>" required>
            </label>
            <label>
                Interest rate
                <input type="number" name="interest_rate" step="0.01" value="<?= htmlspecialchars($editRecord['interest_rate'] ?? '0') ?>">
            </label>
            <label>
                Due date
                <input type="date" name="due_date" value="<?= htmlspecialchars($editRecord['due_date'] ?? '') ?>">
            </label>
            <label>
                Status
                <select name="status">
                    <option value="ongoing" <?= ($editRecord['status'] ?? 'ongoing') === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                    <option value="closed" <?= ($editRecord['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                    <option value="defaulted" <?= ($editRecord['status'] ?? '') === 'defaulted' ? 'selected' : '' ?>>Defaulted</option>
                </select>
            </label>
            <label>
                Notes
                <textarea name="notes" rows="2"><?= htmlspecialchars($editRecord['notes'] ?? '') ?></textarea>
            </label>
            <button type="submit">Update lending record</button>
            <a class="secondary" href="?module=lending">Cancel</a>
        </form>
    </section>
    <?php endif; ?>

    <section class="module-panel">
        <h2>New lending record</h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="lending">
            <label>
                Search contact
                <input type="text" id="contact-search" placeholder="Type name/mobile/email" autocomplete="off">
            </label>
            <input type="hidden" name="contact_id" id="contact-id" required>
            <label>
                Matched contacts
                <div id="contact-results" class="module-placeholder">
                    <small class="muted">Start typing to search contacts.</small>
                </div>
            </label>
            <label>
                Lending amount (₹)
                <input type="number" name="principal_amount" step="0.01" required>
            </label>
            <label>
                Interest rate (%)
                <input type="number" name="interest_rate" step="0.01" value="0">
            </label>
            <label>
                Lending date
                <input type="date" name="lending_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>
                Due date
                <input type="date" name="due_date">
            </label>
            <label>
                Funding account
                <select name="funding_account">
                    <option value="">Select account (optional)</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= htmlspecialchars(($account['account_type'] ?? 'savings') . ':' . $account['id']) ?>">
                            <?= htmlspecialchars(($account['bank_name'] ?? '') . ' - ' . ($account['account_name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Status
                <select name="status">
                    <option value="ongoing" selected>Ongoing</option>
                    <option value="closed">Closed</option>
                    <option value="defaulted">Defaulted</option>
                </select>
            </label>
            <label>
                Notes
                <textarea name="notes" rows="2"></textarea>
            </label>
            <button type="submit">Record lending</button>
        </form>
    </section>

    <section class="module-panel">
        <h2>Collect repayment</h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="repayment">
            <label>
                Lending record
                <select name="lending_record_id" required>
                    <option value="">Choose record</option>
                    <?php foreach ($openRecords as $open): ?>
                        <option value="<?= (int) $open['id'] ?>">
                            <?= htmlspecialchars(($open['contact_name'] ?? 'Contact') . ' - Outstanding ' . formatCurrency((float) ($open['outstanding_amount'] ?? 0))) ?>
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
                Deposit to account
                <select name="deposit_account">
                    <option value="">Select account (optional)</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= htmlspecialchars(($account['account_type'] ?? 'savings') . ':' . $account['id']) ?>">
                            <?= htmlspecialchars(($account['bank_name'] ?? '') . ' - ' . ($account['account_name'] ?? '')) ?>
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
                Send receipt email to contact
            </label>
            <?php endif; ?>
            <button type="submit">Record repayment</button>
        </form>
    </section>

    <section class="module-panel">
        <h2>Lending ledger</h2>
        <?php if (empty($records)): ?>
            <p class="muted">No lending records yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Contact</th>
                            <th>Principal</th>
                            <th>Interest</th>
                            <th>Outstanding</th>
                            <th>Due</th>
                            <th>Linked loan</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record):
                            $isOverdue = !empty($record['due_date']) && $record['due_date'] < date('Y-m-d') && $record['status'] === 'ongoing';
                        ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($record['contact_name']) ?></strong><br>
                                    <small><?= htmlspecialchars($record['mobile'] ?? '') ?></small>
                                </td>
                                <td><?= formatCurrency((float) $record['principal_amount']) ?></td>
                                <td><?= number_format((float) $record['interest_rate'], 2) ?>%</td>
                                <td><?= formatCurrency((float) $record['outstanding_amount']) ?></td>
                                <td>
                                    <?php if ($isOverdue): ?>
                                        <span style="color:#f43f5e;font-weight:600;"><?= htmlspecialchars($record['due_date']) ?> ⚠</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($record['due_date'] ?? '—') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($record['linked_loan_id'])): ?>
                                        <span class="pill pill--green"><?= htmlspecialchars($record['linked_loan_name'] ?? 'Loan #' . $record['linked_loan_id']) ?></span>
                                    <?php else: ?>
                                        <button type="button" class="secondary link-lending-btn" style="font-size:0.75rem;padding:0.2rem 0.6rem;"
                                            data-lending-id="<?= (int) $record['id'] ?>"
                                            data-contact="<?= htmlspecialchars($record['contact_name']) ?>">
                                            + Link
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space:nowrap;">
                                    <a class="secondary" href="?module=lending&edit=<?= (int) $record['id'] ?>">Edit</a>
                                    <?php if ($smtpReady && $record['status'] === 'ongoing' && !empty($record['email'])): ?>
                                    <form method="post" style="display:inline;margin-left:0.4rem;">
                                        <input type="hidden" name="form" value="lending_reminder">
                                        <input type="hidden" name="lending_record_id" value="<?= (int) $record['id'] ?>">
                                        <button type="submit" class="secondary" style="font-size:0.75rem;padding:0.2rem 0.6rem;">Remind</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($record['status'] === 'ongoing' && !empty($record['mobile'])): ?>
                                    <?php
                                        $waBal = number_format((float) $record['outstanding_amount'], 2, '.', ',');
                                        $waMsg = 'Hi ' . $record['contact_name'] . ', this is a reminder that Rs.' . $waBal . ' is outstanding'
                                            . (!empty($record['due_date']) ? ', due by ' . date('d M Y', strtotime($record['due_date'])) : '')
                                            . '. Please arrange repayment. Thank you.';
                                    ?>
                                    <a href="<?= htmlspecialchars(whatsappLink($record['mobile'], $waMsg)) ?>"
                                       target="_blank" rel="noopener" class="secondary"
                                       style="font-size:0.75rem;padding:0.2rem 0.6rem;margin-left:0.4rem;">WA</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Link to loan inline form -->
            <form method="post" class="module-form" id="link-lending-form" style="display:none;margin-top:1rem;border-top:1px solid var(--line);padding-top:1rem;">
                <input type="hidden" name="form" value="lending_link_loan">
                <input type="hidden" name="lending_record_id" id="link-lending-id">
                <p id="link-lending-label" style="grid-column:1/-1;margin:0;font-weight:500;"></p>
                <label style="grid-column:1/-1;">
                    Select loan to link
                    <select name="loan_id" required>
                        <option value="">Choose…</option>
                        <?php foreach ($allLoans as $loan): ?>
                            <option value="<?= (int) $loan['id'] ?>">
                                <?= htmlspecialchars($loan['loan_name']) ?> — Outstanding <?= formatCurrency((float) $loan['outstanding_principal']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Link</button>
                <button type="button" class="secondary" id="link-lending-cancel">Cancel</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Repayment history</h2>
        <?php if (empty($allRepayments)): ?>
            <p class="muted">No repayments recorded yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Contact</th>
                            <th>Amount</th>
                            <th>Deposited to</th>
                            <th>Notes</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allRepayments as $rep): ?>
                            <tr>
                                <td><?= htmlspecialchars($rep['repayment_date']) ?></td>
                                <td><?= htmlspecialchars($rep['contact_name']) ?></td>
                                <td style="color:var(--green)"><?= formatCurrency((float) $rep['amount']) ?></td>
                                <td>
                                    <?php if (!empty($rep['deposit_account_type'])): ?>
                                        <span class="pill pill--green"><?= htmlspecialchars(ucfirst($rep['deposit_account_type'])) ?> #<?= (int) $rep['deposit_account_id'] ?></span>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($rep['notes'] ?? '—') ?></td>
                                <td style="white-space:nowrap;">
                                    <?php if ($smtpReady && !empty($rep['email'])): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="form" value="lending_resend_email">
                                        <input type="hidden" name="repayment_id" value="<?= (int) $rep['repayment_id'] ?>">
                                        <button type="submit" class="secondary" style="font-size:0.75rem;padding:0.2rem 0.6rem;">Email</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="post" style="display:inline;margin-left:0.3rem;" onsubmit="return confirm('Void this repayment? This cannot be undone.');">
                                        <input type="hidden" name="form" value="lending_void_repayment">
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

    <script>
        (function () {
            const form      = document.getElementById('link-lending-form');
            const lendingId = document.getElementById('link-lending-id');
            const label     = document.getElementById('link-lending-label');
            const cancel    = document.getElementById('link-lending-cancel');

            document.querySelectorAll('.link-lending-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    lendingId.value = btn.dataset.lendingId;
                    label.textContent = 'Linking record for: ' + btn.dataset.contact;
                    form.style.display = 'grid';
                    form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
            });

            cancel && cancel.addEventListener('click', function () {
                form.style.display = 'none';
            });
        })();

        (function () {
            const searchInput    = document.getElementById('contact-search');
            const contactIdInput = document.getElementById('contact-id');
            const resultsWrap    = document.getElementById('contact-results');
            let debounceTimer    = null;

            function renderResults(items) {
                resultsWrap.innerHTML = '';
                if (!items.length) {
                    resultsWrap.innerHTML = '<small class="muted">No contacts found.</small>';
                    return;
                }
                items.forEach(function (item) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'secondary';
                    button.textContent = item.name + (item.mobile ? ' - ' + item.mobile : '');
                    button.style.marginRight = '0.5rem';
                    button.style.marginBottom = '0.5rem';
                    button.addEventListener('click', function () {
                        contactIdInput.value = String(item.id);
                        searchInput.value    = button.textContent;
                        resultsWrap.innerHTML = '<small class="muted">Selected: ' + button.textContent + '</small>';
                    });
                    resultsWrap.appendChild(button);
                });
            }

            searchInput.addEventListener('input', function () {
                contactIdInput.value = '';
                clearTimeout(debounceTimer);
                const query = searchInput.value.trim();
                debounceTimer = setTimeout(function () {
                    if (query === '') {
                        resultsWrap.innerHTML = '<small class="muted">Start typing to search contacts.</small>';
                        return;
                    }
                    fetch('?module=lending&action=contact_search&q=' + encodeURIComponent(query))
                        .then(function (r) { return r.json(); })
                        .then(function (items) { renderResults(Array.isArray(items) ? items : []); })
                        .catch(function () { resultsWrap.innerHTML = '<small class="muted">Search failed. Try again.</small>'; });
                }, 250);
            });
        })();
    </script>
</main>
