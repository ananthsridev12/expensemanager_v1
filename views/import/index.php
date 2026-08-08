<?php
$activeModule = 'import';
$accounts     = $accounts   ?? [];
$categories   = $categories ?? [];
$batches      = $batches    ?? [];
$parsers      = $parsers    ?? [];
$preview      = $preview    ?? null;
$msg          = $msg        ?? null;
$error        = $error      ?? null;

include __DIR__ . '/../partials/nav.php';

// Build account options with data-type for JS
$accountOptions = [];
foreach ($accounts as $acct) {
    $label = htmlspecialchars(
        ($acct['bank_name'] ?? '') . ' — ' . ($acct['account_name'] ?? '') .
        ' (' . ($acct['account_type'] ?? '') . ')'
    );
    $type = htmlspecialchars($acct['account_type'] ?? 'savings');
    $accountOptions[] = ['id' => (int)$acct['id'], 'label' => $label, 'type' => $type];
}
?>
<main class="module-content">
    <header class="module-header">
        <h1>Import Transactions</h1>
        <p>Upload a bank or credit-card CSV statement to bulk-import transactions.</p>
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

    <?php if ($preview): ?>
    <!-- ── Preview step ──────────────────────────────────────────────────── -->
    <?php
        $rows       = $preview['rows'];
        $total      = $preview['counts']['total'];
        $dups       = $preview['counts']['duplicates'];
        $bankName   = htmlspecialchars($preview['bank_name']);
        $acctId     = (int)$preview['account_id'];
        $acctLabel  = '';
        foreach ($accounts as $a) {
            if ((int)$a['id'] === $acctId) {
                $acctLabel = ($a['bank_name'] ?? '') . ' — ' . ($a['account_name'] ?? '');
                break;
            }
        }
    ?>
    <section class="module-panel">
        <h2>Preview — <?= $bankName ?></h2>
        <p style="color:var(--muted);margin-bottom:1rem;">
            Account: <strong><?= htmlspecialchars($acctLabel) ?></strong> &nbsp;|&nbsp;
            <?= $total ?> row(s) parsed &nbsp;|&nbsp;
            <span style="color:var(--<?= $dups > 0 ? 'yellow' : 'green' ?>)">
                <?= $dups ?> possible duplicate(s)
            </span>
        </p>

        <form method="post">
            <input type="hidden" name="form" value="confirm">
            <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem;">
                <label style="flex:1;min-width:200px;">
                    Default category <small class="muted">(applied to all rows — edit individually after import)</small>
                    <select name="category_id">
                        <option value="">— None —</option>
                        <?php foreach ($categories as $cat): ?>
                            <optgroup label="<?= htmlspecialchars($cat['name']) ?> (<?= $cat['type'] ?>)">
                                <?php foreach ($cat['subcategories'] as $sub): ?>
                                    <option value="<?= (int)$sub['id'] ?>">
                                        <?= htmlspecialchars($sub['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="">— <?= htmlspecialchars($cat['name']) ?> (parent) —</option>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="flex-direction:row;align-items:center;gap:0.5rem;white-space:nowrap;">
                    <input type="checkbox" name="skip_duplicates" value="1" checked>
                    Skip possible duplicates (<?= $dups ?>)
                </label>
            </div>
            <div style="display:flex;gap:0.75rem;margin-bottom:1.25rem;">
                <button type="submit">Confirm Import</button>
                <button type="submit" form="cancel-form" class="secondary">Cancel</button>
            </div>
        </form>
        <form id="cancel-form" method="post" style="display:none;">
            <input type="hidden" name="form" value="cancel_preview">
        </form>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $row): ?>
                        <tr style="<?= !empty($row['is_duplicate']) ? 'opacity:0.5;' : '' ?>">
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($row['date'] ?? '') ?></td>
                            <td style="max-width:260px;word-break:break-word;">
                                <?= htmlspecialchars($row['description'] ?? '') ?>
                            </td>
                            <td style="text-align:right;"><?= formatCurrency((float)($row['amount'] ?? 0)) ?></td>
                            <td>
                                <span class="pill pill--<?= ($row['type'] ?? 'debit') === 'credit' ? 'green' : 'red' ?>">
                                    <?= ($row['type'] ?? 'debit') === 'credit' ? 'Credit' : 'Debit' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['reference'] ?? '') ?></td>
                            <td style="text-align:right;"><?= ($row['balance'] ?? 0) > 0 ? formatCurrency((float)$row['balance']) : '—' ?></td>
                            <td>
                                <?php if (!empty($row['is_duplicate'])): ?>
                                    <span class="pill pill--muted" style="font-size:0.72rem;">Duplicate</span>
                                <?php else: ?>
                                    <span class="pill pill--green" style="font-size:0.72rem;">New</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php else: ?>
    <!-- ── Upload step ───────────────────────────────────────────────────── -->
    <section class="module-panel">
        <h2>Upload statement</h2>
        <?php if (empty($parsers)): ?>
            <p class="muted" style="margin-bottom:1rem;">
                No bank parsers installed yet — parsers are added to the <code>parsers/</code> folder
                as you share CSV files from each bank.
            </p>
        <?php else: ?>
            <p style="color:var(--muted);font-size:0.88rem;margin-bottom:1rem;">
                Supported formats: <strong><?= htmlspecialchars(implode(', ', array_map(fn($c) => $c::name(), $parsers))) ?></strong>
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
                        <option value="<?= $opt['id'] ?>" data-type="<?= $opt['type'] ?>">
                            <?= $opt['label'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                CSV statement
                <input type="file" name="csv_file" accept=".csv" required>
            </label>
            <button type="submit">Parse &amp; Preview</button>
        </form>
    </section>
    <?php endif; ?>

    <!-- ── Import history ────────────────────────────────────────────────── -->
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
                            <th>Label</th>
                            <th>Account</th>
                            <th>Imported</th>
                            <th>Skipped</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batches as $batch): ?>
                            <tr>
                                <td><?= (int)$batch['id'] ?></td>
                                <td><?= htmlspecialchars($batch['label']) ?></td>
                                <td><?= htmlspecialchars(($batch['acct_bank'] ?? '') . ' — ' . ($batch['acct_name'] ?? '')) ?></td>
                                <td><?= (int)$batch['row_count'] ?></td>
                                <td><?= (int)$batch['skipped_count'] ?></td>
                                <td><?= htmlspecialchars($batch['created_at']) ?></td>
                                <td>
                                    <form method="post" style="display:inline;"
                                          onsubmit="return confirm('Roll back batch #<?= (int)$batch['id'] ?>? This deletes all <?= (int)$batch['row_count'] ?> imported transactions.')">
                                        <input type="hidden" name="form" value="rollback">
                                        <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
                                        <button type="submit" class="secondary"
                                                style="font-size:0.75rem;padding:0.2rem 0.6rem;color:var(--red);">
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

    <script>
        (function () {
            var sel    = document.getElementById('account-select');
            var hidden = document.getElementById('account-type-hidden');
            if (!sel || !hidden) return;
            function sync() {
                var opt = sel.options[sel.selectedIndex];
                hidden.value = opt ? (opt.dataset.type || 'savings') : 'savings';
            }
            sel.addEventListener('change', sync);
            sync();
        })();
    </script>
</main>
