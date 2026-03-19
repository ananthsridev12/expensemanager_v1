<?php
$activeModule = 'contacts';
$contacts = $contacts ?? [];
$editContact = $editContact ?? null;

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>Contacts</h1>
        <p>Master contact book for lending, rental, and future person-based transactions.</p>
    </header>

    <section class="module-panel">
        <h2><?= $editContact ? 'Edit contact' : 'Add contact' ?></h2>
        <form method="post" class="module-form">
            <?php if ($editContact): ?>
                <input type="hidden" name="form" value="contact_update">
                <input type="hidden" name="id" value="<?= (int) $editContact['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="form" value="contact">
            <?php endif; ?>
            <label>
                Name
                <input type="text" name="name" required value="<?= htmlspecialchars($editContact['name'] ?? '') ?>">
            </label>
            <label>
                Mobile
                <input type="text" name="mobile" value="<?= htmlspecialchars($editContact['mobile'] ?? '') ?>">
            </label>
            <label>
                Email
                <input type="email" name="email" value="<?= htmlspecialchars($editContact['email'] ?? '') ?>">
            </label>
            <label>
                Address
                <input type="text" name="address" value="<?= htmlspecialchars($editContact['address'] ?? '') ?>">
            </label>
            <label>
                City
                <input type="text" name="city" value="<?= htmlspecialchars($editContact['city'] ?? '') ?>">
            </label>
            <label>
                State
                <input type="text" name="state" value="<?= htmlspecialchars($editContact['state'] ?? '') ?>">
            </label>
            <label>
                Contact type
                <select name="contact_type">
                    <option value="other" <?= ($editContact['contact_type'] ?? 'other') === 'other' ? 'selected' : '' ?>>Other</option>
                    <option value="tenant" <?= ($editContact['contact_type'] ?? '') === 'tenant' ? 'selected' : '' ?>>Tenant</option>
                    <option value="lending" <?= ($editContact['contact_type'] ?? '') === 'lending' ? 'selected' : '' ?>>Lending</option>
                    <option value="both" <?= ($editContact['contact_type'] ?? '') === 'both' ? 'selected' : '' ?>>Both</option>
                </select>
            </label>
            <label>
                Notes
                <textarea name="notes" rows="2"><?= htmlspecialchars($editContact['notes'] ?? '') ?></textarea>
            </label>
            <button type="submit"><?= $editContact ? 'Update contact' : 'Save contact' ?></button>
            <?php if ($editContact): ?>
                <a class="secondary" href="?module=contacts">Cancel</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="module-panel">
        <h2>Contact list</h2>
        <?php if (empty($contacts)): ?>
            <p class="muted">No contacts yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>City</th>
                            <th>State</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): ?>
                            <tr>
                                <td><?= htmlspecialchars($contact['name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($contact['mobile'] ?? '') ?></td>
                                <td><?= htmlspecialchars($contact['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars(ucfirst((string) ($contact['contact_type'] ?? 'other'))) ?></td>
                                <td><?= htmlspecialchars($contact['city'] ?? '') ?></td>
                                <td><?= htmlspecialchars($contact['state'] ?? '') ?></td>
                                <td><a class="secondary" href="?module=contacts&edit=<?= (int) $contact['id'] ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
