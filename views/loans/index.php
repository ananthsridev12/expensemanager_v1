<?php
$activeModule    = 'loans';
$loans           = $loans ?? [];
$accounts        = $accounts ?? [];
$upcomingEmis    = $upcomingEmis ?? [];
$linkedPairs     = $linkedPairs ?? [];
$lendingOptions  = $lendingOptions ?? [];
$summary         = $summary ?? ['count' => 0, 'total_outstanding' => 0.0];
$editLoan        = $editLoan ?? null;

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>Loans</h1>
        <p>Track principal, EMI schedules, and repayments while keeping the ledger immutable.</p>
    </header>

    <section class="summary-cards">
        <article class="card">
            <h3>Active loans</h3>
            <p><?= $summary['count'] ?></p>
        </article>
        <article class="card card--red">
            <h3>Total outstanding</h3>
            <p><?= formatCurrency($summary['total_outstanding']) ?></p>
        </article>
    </section>

    <?php if (!empty($linkedPairs)): ?>
    <section class="module-panel">
        <h2>Loan-Lending tracker</h2>
        <p class="muted">How much you've paid out vs recovered — your net out-of-pocket cost per linked pair.</p>
        <?php foreach ($linkedPairs as $pair): ?>
            <?php
                $emiPaid   = (float) $pair['total_emi_paid'];
                $recovered = (float) $pair['total_recovered'];
                $gap       = $emiPaid - $recovered;
            ?>
            <div style="border:1px solid var(--border); border-radius:6px; padding:1rem; margin-bottom:1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                    <strong><?= htmlspecialchars($pair['loan_name']) ?> ↔ <?= htmlspecialchars($pair['contact_name']) ?></strong>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="form" value="loan_link">
                        <input type="hidden" name="loan_id" value="<?= (int) $pair['loan_id'] ?>">
                        <input type="hidden" name="lending_record_id" value="">
                        <button type="submit" class="secondary" style="font-size:0.75rem; padding:0.2rem 0.6rem;"
                            onclick="return confirm('Unlink this pair?')">Unlink</button>
                    </form>
                </div>
                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:0.5rem 1.5rem;">
                    <div>
                        <small class="muted">Total EMI paid by you</small>
                        <div style="font-size:1.1rem; font-weight:600;"><?= formatCurrency($emiPaid) ?></div>
                        <?php if ((float)($pair['prior_payments'] ?? 0) > 0): ?>
                            <small class="muted">incl. <?= formatCurrency((float)$pair['prior_payments']) ?> before tracking</small>
                        <?php endif; ?>
                    </div>
                    <div>
                        <small class="muted">Recovered from <?= htmlspecialchars($pair['contact_name']) ?></small>
                        <div style="font-size:1.1rem; font-weight:600; color:var(--green);"><?= formatCurrency($recovered) ?></div>
                    </div>
                    <div>
                        <small class="muted">Out-of-pocket gap</small>
                        <div style="font-size:1.15rem; font-weight:700; color:<?= $gap > 0 ? 'var(--red)' : 'var(--green)' ?>;">
                            <?= $gap > 0 ? '−' : '+' ?><?= formatCurrency(abs($gap)) ?>
                        </div>
                    </div>
                    <div>
                        <small class="muted">Loan still owed to bank</small>
                        <div><?= formatCurrency((float) $pair['loan_outstanding']) ?></div>
                    </div>
                    <div>
                        <small class="muted"><?= htmlspecialchars($pair['contact_name']) ?> still owes you</small>
                        <div><?= formatCurrency((float) $pair['lending_outstanding']) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php if ($editLoan): ?>
    <section class="module-panel">
        <h2>Edit loan</h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="loan_update">
            <input type="hidden" name="id" value="<?= (int) $editLoan['id'] ?>">
            <label>
                Loan name
                <input type="text" name="loan_name" required value="<?= htmlspecialchars($editLoan['loan_name'] ?? '') ?>">
            </label>
            <label>
                Loan type
                <select name="loan_type">
                    <option value="personal" <?= ($editLoan['loan_type'] ?? 'personal') === 'personal' ? 'selected' : '' ?>>Personal Loan</option>
                    <option value="home" <?= ($editLoan['loan_type'] ?? '') === 'home' ? 'selected' : '' ?>>Home Loan</option>
                    <option value="car" <?= ($editLoan['loan_type'] ?? '') === 'car' ? 'selected' : '' ?>>Car Loan</option>
                    <option value="gold" <?= ($editLoan['loan_type'] ?? '') === 'gold' ? 'selected' : '' ?>>Gold Loan</option>
                </select>
            </label>
            <label>
                Interest rate (% annual)
                <input type="number" name="interest_rate" step="0.01" value="<?= htmlspecialchars($editLoan['interest_rate'] ?? '0') ?>">
            </label>
            <label>
                EMI amount
                <input type="number" name="emi_amount" step="0.01" value="<?= htmlspecialchars($editLoan['emi_amount'] ?? '0') ?>">
            </label>
            <label>
                Outstanding principal
                <input type="number" name="outstanding_principal" step="0.01" value="<?= htmlspecialchars($editLoan['outstanding_principal'] ?? '0') ?>">
            </label>
            <label style="grid-column:1/-1;">
                Total paid before tracking (historical EMIs)
                <input type="number" name="prior_payments" id="edit-prior-payments" step="0.01" min="0" value="<?= htmlspecialchars($editLoan['prior_payments'] ?? '0') ?>">
                <small class="muted">Total paid to the bank before this loan was added to the system. Used for the Loan-Lending tracker gap.</small>
            </label>
            <!-- Auto-calculator -->
            <div style="grid-column:1/-1; background:var(--surface-alt,#f5f5f5); border-radius:6px; padding:0.75rem; display:grid; grid-template-columns:repeat(2,1fr); gap:0.5rem;">
                <div style="grid-column:1/-1;"><small><strong>Auto-calculate from first EMI date</strong></small></div>
                <label style="margin:0;">
                    First EMI date
                    <input type="date" id="calc-first-date" value="<?= htmlspecialchars($editLoan['start_date'] ?? date('Y-m-d')) ?>">
                </label>
                <label style="margin:0;">
                    Last EMI paid date
                    <input type="date" id="calc-last-date" value="<?= date('Y-m-d') ?>">
                </label>
                <div style="grid-column:1/-1;">
                    <button type="button" class="secondary" id="calc-prior-btn">Calculate &amp; fill</button>
                    <small class="muted" id="calc-prior-result" style="margin-left:0.75rem;"></small>
                </div>
            </div>
            <button type="submit">Update loan</button>
            <a class="secondary" href="?module=loans">Cancel</a>
        </form>
    </section>
    <?php endif; ?>

    <section class="module-panel">
        <h2>Add existing loan</h2>
        <p class="muted">Already repaying a loan? Enter the current state — no disbursement entry will be created.</p>
        <form method="post" class="module-form" id="existing-loan-form">
            <input type="hidden" name="form" value="loan_existing">
            <label>
                Loan type
                <select name="loan_type">
                    <option value="personal" selected>Personal Loan</option>
                    <option value="home">Home Loan</option>
                    <option value="car">Car Loan</option>
                    <option value="gold">Gold Loan</option>
                </select>
            </label>
            <label>
                Loan name
                <input type="text" name="loan_name" required placeholder="e.g. HDFC Personal Loan">
            </label>
            <label>
                Repayment type
                <select name="repayment_type">
                    <option value="emi" selected>EMI (Principal + Interest)</option>
                    <option value="interest_only">Interest Only</option>
                </select>
            </label>
            <label>
                Original principal
                <input type="number" name="principal_amount" step="0.01" min="0" placeholder="Total loan amount when taken">
                <small class="muted">For reference only. Leave 0 if unknown.</small>
            </label>
            <label>
                Current outstanding balance
                <input type="number" name="outstanding_principal" id="el-outstanding" step="0.01" min="0" required placeholder="Amount still owed today">
            </label>
            <label>
                Interest rate (% annual)
                <input type="number" name="interest_rate" id="el-rate" step="0.01" min="0" required>
            </label>
            <label>
                Remaining tenure (months)
                <input type="number" name="remaining_tenure_months" id="el-tenure" min="1" required>
            </label>
            <label>
                Next EMI date
                <input type="date" name="next_emi_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>
                Loan start date (original)
                <input type="date" name="start_date" value="<?= date('Y-m-d') ?>">
                <small class="muted">When the loan was originally taken.</small>
            </label>
            <label>
                EMI amount
                <input type="number" name="emi_amount" id="el-emi" step="0.01" min="0" placeholder="Auto-calculated — override if needed">
                <small class="muted">Leave blank to auto-calculate from outstanding, rate, and tenure.</small>
            </label>
            <button type="submit">Add existing loan</button>
        </form>
    </section>

    <section class="module-panel">
        <h2>New loan</h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="loan">
            <label>
                Loan type
                <select name="loan_type">
                    <option value="personal" selected>Personal Loan</option>
                    <option value="home">Home Loan</option>
                    <option value="car">Car Loan</option>
                    <option value="gold">Gold Loan</option>
                </select>
            </label>
            <label>
                Loan name
                <input type="text" name="loan_name" required>
            </label>
            <label>
                Repayment type
                <select name="repayment_type">
                    <option value="emi" selected>EMI (Principal + Interest monthly)</option>
                    <option value="interest_only">Interest Only (Principal at end)</option>
                </select>
            </label>
            <label>
                Principal amount
                <input type="number" name="principal_amount" step="0.01" required>
            </label>
            <label>
                Interest rate (% annual)
                <input type="number" name="interest_rate" step="0.01" required>
            </label>
            <label>
                Tenure (months)
                <input type="number" name="tenure_months" min="1" required>
            </label>
            <label>
                Processing fee
                <input type="number" name="processing_fee" step="0.01">
            </label>
            <label>
                GST on processing fee (%)
                <input type="number" name="gst" step="0.01" value="18">
            </label>
            <label>
                Start date
                <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>
                Disburse funds to account
                <select name="disbursement_account">
                    <option value="">Select account (optional)</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= htmlspecialchars(($account['account_type'] ?? 'savings') . ':' . $account['id']) ?>">
                            <?= htmlspecialchars(($account['bank_name'] ?? '') . ' - ' . ($account['account_name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Create loan</button>
        </form>
    </section>

    <section class="module-panel">
        <h2>Loan list</h2>
        <?php if (empty($loans)): ?>
            <p class="muted">No loans recorded yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Repayment</th>
                            <th>Principal</th>
                            <th>Outstanding</th>
                            <th>EMI</th>
                            <th>Start date</th>
                            <th>Linked lending</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loans as $loan): ?>
                            <tr>
                                <td><?= htmlspecialchars($loan['loan_name']) ?></td>
                                <td><?= htmlspecialchars(ucfirst($loan['loan_type'])) ?></td>
                                <td><?= htmlspecialchars(($loan['repayment_type'] ?? 'emi') === 'interest_only' ? 'Interest Only' : 'EMI') ?></td>
                                <td><?= formatCurrency((float) $loan['principal_amount']) ?></td>
                                <td><?= formatCurrency((float) $loan['outstanding_principal']) ?></td>
                                <td><?= formatCurrency((float) $loan['emi_amount']) ?></td>
                                <td><?= htmlspecialchars($loan['start_date']) ?></td>
                                <td>
                                    <?php
                                        $linkedLendingId = (int) ($loan['linked_lending_id'] ?? 0);
                                        $linkedPair = null;
                                        foreach ($linkedPairs as $p) {
                                            if ((int) $p['loan_id'] === (int) $loan['id']) { $linkedPair = $p; break; }
                                        }
                                    ?>
                                    <?php if ($linkedPair): ?>
                                        <span class="pill pill--green"><?= htmlspecialchars($linkedPair['contact_name']) ?></span>
                                    <?php else: ?>
                                        <button type="button" class="secondary link-loan-btn" style="font-size:0.75rem; padding:0.2rem 0.6rem;"
                                            data-loan-id="<?= (int) $loan['id'] ?>"
                                            data-loan-name="<?= htmlspecialchars($loan['loan_name']) ?>">
                                            + Link
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td><a class="secondary" href="?module=loans&edit=<?= (int) $loan['id'] ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Link to lending inline form -->
            <form method="post" class="module-form" id="link-loan-form" style="display:none; margin-top:1rem; border-top:1px solid var(--border); padding-top:1rem;">
                <input type="hidden" name="form" value="loan_link">
                <input type="hidden" name="loan_id" id="link-loan-id">
                <p id="link-loan-label" style="grid-column:1/-1; margin:0; font-weight:500;"></p>
                <label style="grid-column:1/-1;">
                    Select lending record to link
                    <select name="lending_record_id" required>
                        <option value="">Choose…</option>
                        <?php foreach ($lendingOptions as $opt): ?>
                            <option value="<?= (int) $opt['id'] ?>">
                                <?= htmlspecialchars($opt['contact_name']) ?> — Outstanding <?= formatCurrency((float) $opt['outstanding_amount']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Link</button>
                <button type="button" class="secondary" id="link-loan-cancel">Cancel</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Upcoming EMIs</h2>
        <?php if (empty($upcomingEmis)): ?>
            <p class="muted">Loan EMIs will appear here once created.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Loan</th>
                            <th>Due date</th>
                            <th>Principal</th>
                            <th>Interest</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingEmis as $emi): ?>
                            <?php $total = (float) $emi['principal_component'] + (float) $emi['interest_component']; ?>
                            <tr>
                                <td><?= htmlspecialchars($emi['loan_name']) ?></td>
                                <td><?= htmlspecialchars($emi['emi_date']) ?></td>
                                <td><?= formatCurrency((float) $emi['principal_component']) ?></td>
                                <td><?= formatCurrency((float) $emi['interest_component']) ?></td>
                                <td><strong><?= formatCurrency($total) ?></strong></td>
                                <td>
                                    <button type="button" class="secondary pay-emi-btn"
                                        data-emi-id="<?= (int) $emi['id'] ?>"
                                        data-loan-id="<?= (int) $emi['loan_id'] ?>"
                                        data-loan-name="<?= htmlspecialchars($emi['loan_name']) ?>"
                                        data-total="<?= $total ?>">
                                        Pay
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pay EMI form (revealed when Pay is clicked) -->
            <form method="post" class="module-form" id="pay-emi-form" style="display:none; margin-top:1rem; border-top:1px solid var(--border); padding-top:1rem;">
                <input type="hidden" name="form" value="emi_pay">
                <input type="hidden" name="emi_id" id="pay-emi-id">
                <input type="hidden" name="loan_id" id="pay-loan-id">
                <p id="pay-emi-label" style="grid-column:1/-1; margin:0; font-weight:500;"></p>
                <label>
                    Pay from account
                    <select name="payment_account" required>
                        <option value="">Select account</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= htmlspecialchars(($account['account_type'] ?? 'savings') . ':' . $account['id']) ?>">
                                <?= htmlspecialchars(($account['bank_name'] ?? '') . ' — ' . ($account['account_name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Payment date
                    <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                </label>
                <button type="submit">Confirm payment</button>
                <button type="button" class="secondary" id="pay-emi-cancel">Cancel</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<script>
    (function () {
        const outstanding = document.getElementById('el-outstanding');
        const rate = document.getElementById('el-rate');
        const tenure = document.getElementById('el-tenure');
        const emiInput = document.getElementById('el-emi');

        function calcEmi() {
            const p = parseFloat(outstanding.value || '0');
            const r = parseFloat(rate.value || '0') / 12 / 100;
            const n = parseInt(tenure.value || '0', 10);
            if (p <= 0 || n <= 0) { emiInput.placeholder = 'Auto-calculated'; return; }
            let emi;
            if (r === 0) {
                emi = p / n;
            } else {
                emi = (p * r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
            }
            emiInput.placeholder = emi.toFixed(2) + ' (auto)';
        }

        outstanding.addEventListener('input', calcEmi);
        rate.addEventListener('input', calcEmi);
        tenure.addEventListener('input', calcEmi);
    })();

    // Prior payments auto-calculator
    (function () {
        const btn        = document.getElementById('calc-prior-btn');
        const resultEl   = document.getElementById('calc-prior-result');
        const priorInput = document.getElementById('edit-prior-payments');
        const emiInput   = document.querySelector('[name="emi_amount"]');
        if (!btn) return;

        btn.addEventListener('click', function () {
            const firstDate = new Date(document.getElementById('calc-first-date').value);
            const lastDate  = new Date(document.getElementById('calc-last-date').value);
            const emi       = parseFloat(emiInput ? emiInput.value : '0');

            if (isNaN(firstDate) || isNaN(lastDate) || emi <= 0) {
                resultEl.textContent = 'Enter valid dates and EMI amount above.';
                return;
            }

            const months = (lastDate.getFullYear() - firstDate.getFullYear()) * 12
                         + (lastDate.getMonth() - firstDate.getMonth()) + 1;

            if (months <= 0) {
                resultEl.textContent = 'Last date must be after first date.';
                return;
            }

            const total = months * emi;
            priorInput.value = total.toFixed(2);
            resultEl.textContent = months + ' months × ₹' + emi.toLocaleString('en-IN') + ' = ₹' + total.toLocaleString('en-IN', { minimumFractionDigits: 2 });
        });
    })();

    // Link loan to lending inline form
    (function () {
        const form   = document.getElementById('link-loan-form');
        const loanId = document.getElementById('link-loan-id');
        const label  = document.getElementById('link-loan-label');
        const cancel = document.getElementById('link-loan-cancel');

        document.querySelectorAll('.link-loan-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                loanId.value = btn.dataset.loanId;
                label.textContent = 'Linking: ' + btn.dataset.loanName;
                form.style.display = 'grid';
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        });

        cancel && cancel.addEventListener('click', function () {
            form.style.display = 'none';
        });
    })();

    // Pay EMI inline form
    (function () {
        const form    = document.getElementById('pay-emi-form');
        const emiId   = document.getElementById('pay-emi-id');
        const loanId  = document.getElementById('pay-loan-id');
        const label   = document.getElementById('pay-emi-label');
        const cancel  = document.getElementById('pay-emi-cancel');

        document.querySelectorAll('.pay-emi-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                emiId.value  = btn.dataset.emiId;
                loanId.value = btn.dataset.loanId;
                const total  = parseFloat(btn.dataset.total).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                label.textContent = 'Paying EMI for: ' + btn.dataset.loanName + ' — ₹' + total;
                form.style.display = 'grid';
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        });

        cancel && cancel.addEventListener('click', function () {
            form.style.display = 'none';
        });
    })();
</script>
