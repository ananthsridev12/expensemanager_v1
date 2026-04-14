<?php
// Partial view — no layout, no nav. Rendered inside the global quick-add modal.
$accounts           = $accounts           ?? [];
$loans              = $loans              ?? [];
$categories         = $categories         ?? [];
$paymentMethods     = $paymentMethods     ?? [];
$purchaseChildren   = $purchaseChildren   ?? [];
$openLendingRecords = $openLendingRecords ?? [];

// Build grouped accounts (same logic as main transaction form)
$acctTypeOrder = ['savings' => 'Savings', 'current' => 'Current', 'credit_card' => 'Credit Cards', 'cash' => 'Cash', 'wallet' => 'Wallets', 'other' => 'Other'];
$acctGrouped   = [];
foreach ($accounts as $acct) {
    $sysKey   = $acct['account_type_system_key'] ?? null;
    $typeId   = $acct['account_type_id'] ?? null;
    $isCustom = ($sysKey === null || $sysKey === '') && !empty($typeId);
    $gKey     = $isCustom ? 'custom_' . (int) $typeId : ($acct['account_type'] ?? 'other');
    $acctGrouped[$gKey][] = $acct;
}
$acctGroups = [];
foreach ($acctTypeOrder as $typeKey => $typeLabel) {
    if (!empty($acctGrouped[$typeKey])) {
        $acctGroups[] = ['label' => $typeLabel, 'accounts' => $acctGrouped[$typeKey]];
    }
}
foreach ($acctGrouped as $gKey => $accts) {
    if (!isset($acctTypeOrder[$gKey])) {
        $first = $accts[0];
        $label = !empty($first['account_type_name']) ? $first['account_type_name'] : ucfirst(str_replace('_', ' ', $gKey));
        $acctGroups[] = ['label' => $label, 'accounts' => $accts];
    }
}
?>

<form method="post" action="?module=transactions" class="module-form" id="qa-form">
    <input type="hidden" name="form" value="transaction">
    <input type="hidden" name="redirect_to" id="qa-redirect-to" value="">

    <label>
        Date
        <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
    </label>

    <label>
        From account
        <select name="account_id" id="qa-from-account" required>
            <?php foreach ($acctGroups as $grp): ?>
                <optgroup label="<?= htmlspecialchars($grp['label']) ?>">
                    <?php foreach ($grp['accounts'] as $account): ?>
                        <option value="<?= htmlspecialchars($account['account_type'] . ':' . $account['id']) ?>"
                                data-type="<?= htmlspecialchars($account['account_type']) ?>"
                                <?= !empty($account['is_default']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($account['bank_name'] . ' – ' . $account['account_name']) ?><?= !empty($account['is_default']) ? ' ★' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
            <?php if (!empty($loans)): ?>
                <optgroup label="Loans">
                    <?php foreach ($loans as $loan): ?>
                        <option value="loan:<?= (int) $loan['id'] ?>" data-type="loan">
                            <?= htmlspecialchars($loan['loan_name'] ?? 'Loan #' . $loan['id']) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endif; ?>
        </select>
    </label>

    <label>
        Transaction type
        <select name="transaction_type" id="qa-tx-type">
            <option value="income">Income</option>
            <option value="expense" selected>Expense</option>
            <option value="transfer">Transfer</option>
        </select>
    </label>

    <label>
        Amount (₹)
        <input type="number" name="amount" step="0.01" min="0" required>
    </label>

    <label>
        Payment method
        <select name="payment_method_id" id="qa-pay-method">
            <option value="">Select method</option>
            <?php foreach ($paymentMethods as $pm): ?>
                <option value="<?= (int) $pm['id'] ?>"><?= htmlspecialchars($pm['name']) ?></option>
            <?php endforeach; ?>
            <option value="other">Other (add new)</option>
        </select>
    </label>
    <label id="qa-new-pm-wrap" style="display:none;">
        New payment method name
        <input type="text" name="new_payment_method" placeholder="e.g. UPI Lite">
    </label>

    <label>
        Category
        <select name="category_id" id="qa-category">
            <option value="">Uncategorized</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?> (<?= $cat['type'] ?>)</option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        Subcategory
        <select name="subcategory_id" id="qa-subcategory">
            <option value="">None</option>
            <?php foreach ($categories as $cat): ?>
                <?php foreach ($cat['subcategories'] as $sub): ?>
                    <option value="<?= (int) $sub['id'] ?>" data-category="<?= (int) $cat['id'] ?>">
                        <?= htmlspecialchars($cat['name'] . ' – ' . $sub['name']) ?>
                    </option>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        To whom (Contact)
        <input type="text" id="qa-contact-search" placeholder="Type name / mobile / email" autocomplete="off">
        <input type="hidden" name="contact_id" id="qa-contact-id">
    </label>
    <div class="module-placeholder" id="qa-contact-results" style="grid-column:1/-1;">
        <small class="muted">Start typing to search contacts.</small>
    </div>

    <label>
        Purchased from
        <select name="purchase_source_id" id="qa-purchase-source">
            <option value="">Select source</option>
            <?php foreach ($purchaseChildren as $src): ?>
                <option value="<?= (int) $src['id'] ?>" data-parent="<?= (int) $src['parent_id'] ?>">
                    <?= htmlspecialchars($src['name']) ?>
                </option>
            <?php endforeach; ?>
            <option value="other">Other (add new)</option>
        </select>
    </label>
    <label id="qa-new-src-wrap" style="display:none;">
        New purchase source
        <input type="text" name="new_purchase_source" placeholder="e.g. Local store">
    </label>

    <!-- Transfer sub-panel -->
    <div id="qa-transfer-panel" style="display:none;grid-column:1/-1;">
        <div class="module-form" style="padding:0;margin:0;">
            <label>
                Transfer to
                <select name="transfer_target" id="qa-transfer-target">
                    <option value="account">Account / Loan</option>
                    <option value="lending">Lending (lend / repayment)</option>
                </select>
            </label>
        </div>
        <div class="module-form" id="qa-transfer-account-panel" style="padding:0;margin:0;">
            <label>
                To account
                <select name="transfer_to_account_id">
                    <option value="">Select target account</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= htmlspecialchars($account['account_type'] . ':' . $account['id']) ?>">
                            <?= htmlspecialchars($account['bank_name'] . ' – ' . $account['account_name']) ?>
                        </option>
                    <?php endforeach; ?>
                    <?php foreach ($loans as $loan): ?>
                        <option value="loan:<?= (int) $loan['id'] ?>">Loan: <?= htmlspecialchars($loan['loan_name'] ?? 'Loan #' . $loan['id']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div id="qa-transfer-lending-panel" style="display:none;">
            <div class="module-form" style="padding:0;margin:0;">
                <label>
                    Mode
                    <select name="lending_mode" id="qa-lending-mode">
                        <option value="new">New lending record</option>
                        <option value="repayment">Repayment from contact</option>
                        <option value="topup">Top-up existing record</option>
                    </select>
                </label>
            </div>
            <div class="module-form" id="qa-lending-new-fields" style="padding:0;margin:0;">
                <label>
                    Interest rate (% p.a.)
                    <input type="number" name="lending_interest_rate" step="0.01" min="0" value="0">
                </label>
                <label>
                    Due date (optional)
                    <input type="date" name="lending_due_date">
                </label>
            </div>
            <div class="module-form" id="qa-lending-repayment-fields" style="display:none;padding:0;margin:0;">
                <label>
                    Lending record
                    <select name="lending_record_id">
                        <option value="">Select record</option>
                        <?php foreach ($openLendingRecords as $lr): ?>
                            <option value="<?= (int) $lr['id'] ?>"><?= htmlspecialchars($lr['contact_name']) ?> — Outstanding: <?= formatCurrency((float) $lr['outstanding_amount']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="module-form" id="qa-lending-topup-fields" style="display:none;padding:0;margin:0;">
                <label>
                    Lending record
                    <select name="lending_record_id">
                        <option value="">Select record</option>
                        <?php foreach ($openLendingRecords as $lr): ?>
                            <option value="<?= (int) $lr['id'] ?>"><?= htmlspecialchars($lr['contact_name']) ?> — Outstanding: <?= formatCurrency((float) $lr['outstanding_amount']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </div>
    </div>

    <label style="grid-column:1/-1;">
        Notes
        <textarea name="notes" rows="2" placeholder="Optional note"></textarea>
    </label>

    <div style="grid-column:1/-1;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
        <button type="submit" id="qa-submit-btn" style="background:#22c55e;border-color:#22c55e;">Add transaction</button>
        <span id="qa-status" style="font-size:0.85rem;color:var(--muted);display:none;"></span>
    </div>
</form>

<script>
(function () {
    const form         = document.getElementById('qa-form');
    const txType       = document.getElementById('qa-tx-type');
    const transferPnl  = document.getElementById('qa-transfer-panel');
    const acctPnl      = document.getElementById('qa-transfer-account-panel');
    const lendingPnl   = document.getElementById('qa-transfer-lending-panel');
    const transferTgt  = document.getElementById('qa-transfer-target');
    const lendingMode  = document.getElementById('qa-lending-mode');
    const lNewFields   = document.getElementById('qa-lending-new-fields');
    const lRepFields   = document.getElementById('qa-lending-repayment-fields');
    const lTopFields   = document.getElementById('qa-lending-topup-fields');
    const catSel       = document.getElementById('qa-category');
    const subSel       = document.getElementById('qa-subcategory');
    const pmSel        = document.getElementById('qa-pay-method');
    const newPmWrap    = document.getElementById('qa-new-pm-wrap');
    const srcSel       = document.getElementById('qa-purchase-source');
    const newSrcWrap   = document.getElementById('qa-new-src-wrap');
    const submitBtn    = document.getElementById('qa-submit-btn');
    const statusEl     = document.getElementById('qa-status');

    // Set redirect_to to current page (path + query)
    const redir = document.getElementById('qa-redirect-to');
    if (redir) redir.value = window.location.pathname + window.location.search;

    // Transaction type → transfer panel
    function onTxTypeChange() {
        const isTransfer = txType.value === 'transfer';
        transferPnl.style.display = isTransfer ? 'block' : 'none';
    }
    txType.addEventListener('change', onTxTypeChange);
    onTxTypeChange();

    // Transfer target → sub panels
    function onTransferTargetChange() {
        const v = transferTgt.value;
        acctPnl.style.display    = v === 'account'  ? 'block' : 'none';
        lendingPnl.style.display = v === 'lending'  ? 'block' : 'none';
    }
    transferTgt.addEventListener('change', onTransferTargetChange);
    onTransferTargetChange();

    // Lending mode → sub panels
    function onLendingModeChange() {
        const v = lendingMode.value;
        lNewFields.style.display  = v === 'new'       ? 'block' : 'none';
        lRepFields.style.display  = v === 'repayment' ? 'block' : 'none';
        lTopFields.style.display  = v === 'topup'     ? 'block' : 'none';
    }
    lendingMode.addEventListener('change', onLendingModeChange);
    onLendingModeChange();

    // Category → filter subcategory
    function filterSubcategories() {
        const catId = catSel.value;
        Array.from(subSel.options).forEach(opt => {
            if (!opt.value || opt.value === '') { opt.style.display = ''; return; }
            opt.style.display = (!catId || opt.dataset.category === catId) ? '' : 'none';
        });
        if (subSel.selectedOptions[0]?.style.display === 'none') subSel.value = '';
    }
    catSel.addEventListener('change', filterSubcategories);
    filterSubcategories();

    // Payment method → new input
    pmSel.addEventListener('change', function () {
        newPmWrap.style.display = pmSel.value === 'other' ? 'flex' : 'none';
    });

    // Purchase source → new input
    srcSel.addEventListener('change', function () {
        newSrcWrap.style.display = srcSel.value === 'other' ? 'flex' : 'none';
    });

    // Contact search
    const contactSearch  = document.getElementById('qa-contact-search');
    const contactId      = document.getElementById('qa-contact-id');
    const contactResults = document.getElementById('qa-contact-results');
    let contactTimer = null;

    function renderContacts(items) {
        contactResults.innerHTML = '';
        if (!items.length) { contactResults.innerHTML = '<small class="muted">No contacts found.</small>'; return; }
        items.forEach(function (item) {
            const btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'secondary';
            btn.textContent = item.name + (item.mobile ? ' – ' + item.mobile : '');
            btn.style.cssText = 'margin-right:0.5rem;margin-bottom:0.5rem;font-size:0.82rem;';
            btn.addEventListener('click', function () {
                contactId.value    = String(item.id);
                contactSearch.value = btn.textContent;
                contactResults.innerHTML = '<small class="muted">Selected: ' + btn.textContent + '</small>';
            });
            contactResults.appendChild(btn);
        });
    }

    contactSearch.addEventListener('input', function () {
        contactId.value = '';
        clearTimeout(contactTimer);
        const q = contactSearch.value.trim();
        if (!q) { contactResults.innerHTML = '<small class="muted">Start typing to search contacts.</small>'; return; }
        contactTimer = setTimeout(async function () {
            try {
                const res = await fetch('?module=transactions&action=contact_search&q=' + encodeURIComponent(q));
                renderContacts(await res.json());
            } catch (e) {
                contactResults.innerHTML = '<small class="muted">Search failed.</small>';
            }
        }, 250);
    });

    // AJAX submit — stay on current page, show toast
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving…';
        statusEl.style.display = 'none';

        try {
            const res = await fetch('?module=transactions', { method: 'POST', body: new FormData(form) });
            if (res.ok || res.redirected) {
                // Success — fire event so layout can close modal + show toast
                document.dispatchEvent(new CustomEvent('qa-success'));
                form.reset();
                filterSubcategories();
                onTxTypeChange();
            } else {
                throw new Error('Server error ' + res.status);
            }
        } catch (err) {
            statusEl.style.display = 'inline';
            statusEl.style.color   = '#f43f5e';
            statusEl.textContent   = 'Failed to save. Please try again.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Add transaction';
        }
    });
})();
</script>
