<?php
$activeModule = 'loans';
$loans = $loans ?? [];
$accounts = $accounts ?? [];
$upcomingEmis = $upcomingEmis ?? [];
$summary = $summary ?? ['count' => 0, 'total_principal' => 0.0];
$editLoan = $editLoan ?? null;

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
        <article class="card">
            <h3>Total principal</h3>
            <p><?= formatCurrency($summary['total_principal']) ?></p>
        </article>
    </section>

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
                                <td><a class="secondary" href="?module=loans&edit=<?= (int) $loan['id'] ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingEmis as $emi): ?>
                            <tr>
                                <td><?= htmlspecialchars($emi['loan_name']) ?></td>
                                <td><?= htmlspecialchars($emi['emi_date']) ?></td>
                                <td><?= formatCurrency((float) $emi['principal_component']) ?></td>
                                <td><?= formatCurrency((float) $emi['interest_component']) ?></td>
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
</script>
