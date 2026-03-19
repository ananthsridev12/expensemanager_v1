<?php
$activeModule = 'rental';
$properties = $properties ?? [];
$tenants = $tenants ?? [];
$contacts = $contacts ?? [];
$accounts = $accounts ?? [];
$contracts = $contracts ?? [];
$transactions = $transactions ?? [];
$upcoming = $upcoming ?? [];
$summary = $summary ?? ['properties' => 0, 'tenants' => 0, 'contracts' => 0];
$editProperty = $editProperty ?? null;
$editTenant = $editTenant ?? null;

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>Rental management</h1>
        <p>Manage properties, tenants, contracts, and rental income in one ledger.</p>
    </header>

    <section class="summary-cards">
        <article class="card">
            <h3>Properties</h3>
            <p><?= $summary['properties'] ?></p>
        </article>
        <article class="card">
            <h3>Tenants</h3>
            <p><?= $summary['tenants'] ?></p>
        </article>
        <article class="card">
            <h3>Contracts</h3>
            <p><?= $summary['contracts'] ?></p>
        </article>
    </section>

    <section class="module-panel">
        <h2><?= $editProperty ? 'Edit property' : 'New property' ?></h2>
        <form method="post" class="module-form">
            <?php if ($editProperty): ?>
                <input type="hidden" name="form" value="property_update">
                <input type="hidden" name="id" value="<?= (int) $editProperty['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="form" value="property">
            <?php endif; ?>
            <label>
                Name
                <input type="text" name="property_name" required value="<?= htmlspecialchars($editProperty['property_name'] ?? '') ?>">
            </label>
            <label>
                Monthly rent
                <input type="number" name="monthly_rent" step="0.01" required value="<?= htmlspecialchars($editProperty['monthly_rent'] ?? '') ?>">
            </label>
            <label>
                Security deposit
                <input type="number" name="security_deposit" step="0.01" value="<?= htmlspecialchars($editProperty['security_deposit'] ?? '') ?>">
            </label>
            <label>
                Address
                <input type="text" name="address" value="<?= htmlspecialchars($editProperty['address'] ?? '') ?>">
            </label>
            <button type="submit"><?= $editProperty ? 'Update property' : 'Add property' ?></button>
            <?php if ($editProperty): ?>
                <a class="secondary" href="?module=rental">Cancel</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="module-panel">
        <h2><?= $editTenant ? 'Edit tenant' : 'New tenant' ?></h2>
        <?php if ($editTenant): ?>
            <form method="post" class="module-form">
                <input type="hidden" name="form" value="tenant_update">
                <input type="hidden" name="id" value="<?= (int) $editTenant['id'] ?>">
                <label>
                    Name
                    <input type="text" name="name" required value="<?= htmlspecialchars($editTenant['name'] ?? '') ?>">
                </label>
                <label>
                    Mobile
                    <input type="text" name="mobile" value="<?= htmlspecialchars($editTenant['mobile'] ?? '') ?>">
                </label>
                <label>
                    Email
                    <input type="email" name="email" value="<?= htmlspecialchars($editTenant['email'] ?? '') ?>">
                </label>
                <label>
                    Address
                    <input type="text" name="address" value="<?= htmlspecialchars($editTenant['address'] ?? '') ?>">
                </label>
                <button type="submit">Update tenant</button>
                <a class="secondary" href="?module=rental">Cancel</a>
            </form>
        <?php else: ?>
            <form method="post" class="module-form">
                <input type="hidden" name="form" value="tenant">
                <label>
                    Search contact
                    <input type="text" id="tenant-contact-search" placeholder="Type name/mobile/email" autocomplete="off" required>
                </label>
                <input type="hidden" name="contact_id" id="tenant-contact-id" required>
                <label>
                    Matched contacts
                    <div id="tenant-contact-results" class="module-placeholder">
                        <small class="muted">Start typing to search contacts.</small>
                    </div>
                </label>
                <label>
                    ID proof
                    <input type="text" name="tenant_id_proof">
                </label>
                <button type="submit">Add tenant</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Create rental contract</h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="contract">
            <label>
                Property
                <select name="property_id" required>
                    <?php foreach ($properties as $property): ?>
                        <option value="<?= $property['id'] ?>"><?= htmlspecialchars($property['property_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Tenant
                <select name="tenant_id" required>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Start date
                <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>
                End date
                <input type="date" name="end_date">
            </label>
            <label>
                Rent amount
                <input type="number" name="rent_amount" step="0.01" required>
            </label>
            <label>
                Deposit amount
                <input type="number" name="deposit_amount" step="0.01">
            </label>
            <button type="submit">Create contract</button>
        </form>
    </section>

    <section class="module-panel">
        <h2>Record rent</h2>
        <form method="post" class="module-form">
            <input type="hidden" name="form" value="transaction">
            <label>
                Contract
                <select name="contract_id" required>
                    <?php foreach ($contracts as $contract): ?>
                        <option value="<?= $contract['id'] ?>"><?= htmlspecialchars($contract['property_name'] . ' / ' . $contract['tenant_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Rent month
                <input type="month" name="rent_month" value="<?= date('Y-m') ?>">
            </label>
            <label>
                Due date
                <input type="date" name="due_date" value="<?= date('Y-m-d') ?>">
            </label>
            <label>
                Paid amount
                <input type="number" name="paid_amount" step="0.01" required>
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
                Status
                <select name="payment_status">
                    <option value="paid">Paid</option>
                    <option value="pending" selected>Pending</option>
                    <option value="overdue">Overdue</option>
                </select>
            </label>
            <label>
                Notes
                <textarea name="notes" rows="2"></textarea>
            </label>
            <button type="submit">Save rent</button>
        </form>
    </section>

    <section class="module-panel">
        <h2>Properties</h2>
        <?php if (empty($properties)): ?>
            <p class="muted">No properties yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Monthly rent</th>
                            <th>Deposit</th>
                            <th>Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($properties as $property): ?>
                            <tr>
                                <td><?= htmlspecialchars($property['property_name']) ?></td>
                                <td><?= formatCurrency((float) $property['monthly_rent']) ?></td>
                                <td><?= formatCurrency((float) $property['security_deposit']) ?></td>
                                <td><?= htmlspecialchars($property['address'] ?? '') ?></td>
                                <td><a class="secondary" href="?module=rental&edit_property=<?= (int) $property['id'] ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Tenants</h2>
        <?php if (empty($tenants)): ?>
            <p class="muted">No tenants yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $tenant): ?>
                            <tr>
                                <td><?= htmlspecialchars($tenant['name']) ?></td>
                                <td><?= htmlspecialchars($tenant['mobile'] ?? '') ?></td>
                                <td><?= htmlspecialchars($tenant['email'] ?? '') ?></td>
                                <td><a class="secondary" href="?module=rental&edit_tenant=<?= (int) $tenant['id'] ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Contracts</h2>
        <?php if (empty($contracts)): ?>
            <p class="muted">No rental contracts yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Property</th>
                            <th>Tenant</th>
                            <th>Rent</th>
                            <th>Period</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $contract): ?>
                            <tr>
                                <td><?= htmlspecialchars($contract['property_name']) ?></td>
                                <td><?= htmlspecialchars($contract['tenant_name']) ?></td>
                                <td><?= formatCurrency((float) $contract['rent_amount']) ?></td>
                                <td><?= htmlspecialchars($contract['start_date']) ?> → <?= htmlspecialchars($contract['end_date'] ?? 'ongoing') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Transactions</h2>
        <?php if (empty($transactions)): ?>
            <p class="muted">No rental payments yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Property</th>
                            <th>Paid</th>
                            <th>Status</th>
                            <th>Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td><?= htmlspecialchars($txn['tenant_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($txn['property_name'] ?? '') ?></td>
                                <td><?= formatCurrency((float) $txn['paid_amount']) ?></td>
                                <td><?= htmlspecialchars(ucfirst($txn['payment_status'])) ?></td>
                                <td><?= htmlspecialchars($txn['due_date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Upcoming rent due</h2>
        <?php if (empty($upcoming)): ?>
            <p class="muted">Nothing upcoming for now.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Property</th>
                            <th>Due date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming as $due): ?>
                            <tr>
                                <td><?= htmlspecialchars($due['tenant_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($due['property_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($due['due_date']) ?></td>
                                <td><?= htmlspecialchars(ucfirst($due['payment_status'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!$editTenant): ?>
    <script>
        (function () {
            const searchInput = document.getElementById('tenant-contact-search');
            const contactIdInput = document.getElementById('tenant-contact-id');
            const resultsWrap = document.getElementById('tenant-contact-results');
            let debounceTimer = null;

            function renderResults(items) {
                resultsWrap.innerHTML = '';
                if (!items.length) {
                    resultsWrap.innerHTML = '<small class="muted">No contacts found.</small>';
                    return;
                }

                items.forEach(item => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'secondary';
                    button.textContent = item.name + (item.mobile ? ' - ' + item.mobile : '');
                    button.style.marginRight = '0.5rem';
                    button.style.marginBottom = '0.5rem';
                    button.addEventListener('click', function () {
                        contactIdInput.value = String(item.id);
                        searchInput.value = item.name + (item.mobile ? ' - ' + item.mobile : '');
                        resultsWrap.innerHTML = '<small class="muted">Selected: ' + button.textContent + '</small>';
                    });
                    resultsWrap.appendChild(button);
                });
            }

            async function searchContacts(query) {
                const response = await fetch('?module=rental&action=contact_search&q=' + encodeURIComponent(query));
                if (!response.ok) {
                    resultsWrap.innerHTML = '<small class="muted">Search failed. Try again.</small>';
                    return;
                }
                const items = await response.json();
                renderResults(Array.isArray(items) ? items : []);
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
                    searchContacts(query);
                }, 250);
            });
        })();
    </script>
    <?php endif; ?>
</main>
