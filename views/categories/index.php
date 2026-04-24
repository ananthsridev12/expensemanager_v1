<?php
$activeModule = 'categories';
$categories = $categories ?? [];
$editCategory = $editCategory ?? null;
$editSubcategory = $editSubcategory ?? null;

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>Categories</h1>
        <p>Create income, expense, or transfer categories and organize subcategories.</p>
    </header>

    <section class="module-panel">
        <h2><?= $editCategory ? 'Edit category' : 'New category' ?></h2>
        <form method="post" class="module-form">
            <?php if ($editCategory): ?>
                <input type="hidden" name="form" value="category_update">
                <input type="hidden" name="id" value="<?= (int) $editCategory['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="form" value="category">
            <?php endif; ?>
            <label>
                Category name
                <input type="text" name="name" required value="<?= htmlspecialchars($editCategory['name'] ?? '') ?>">
            </label>
            <label>
                Type
                <select name="type">
                    <option value="income" <?= ($editCategory['type'] ?? '') === 'income' ? 'selected' : '' ?>>Income</option>
                    <option value="expense" <?= ($editCategory['type'] ?? 'expense') === 'expense' ? 'selected' : '' ?>>Expense</option>
                    <option value="transfer" <?= ($editCategory['type'] ?? '') === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                </select>
            </label>
            <label style="flex-direction:row;align-items:center;gap:0.5rem;">
                <input type="checkbox" name="is_fuel" value="1" <?= !empty($editCategory['is_fuel']) ? 'checked' : '' ?>>
                Fuel category (for surcharge tracking)
            </label>
            <label style="flex-direction:row;align-items:center;gap:0.5rem;">
                <input type="checkbox" name="exclude_from_analytics" value="1" <?= !empty($editCategory['exclude_from_analytics']) ? 'checked' : '' ?>>
                Exclude from analytics (e.g. credit card payments, internal transfers)
            </label>
            <button type="submit"><?= $editCategory ? 'Update category' : 'Create category' ?></button>
            <?php if ($editCategory): ?>
                <a class="secondary" href="?module=categories">Cancel</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="module-panel">
        <h2><?= $editSubcategory ? 'Edit subcategory' : 'New subcategory' ?></h2>
        <?php if (count($categories) === 0 && !$editSubcategory): ?>
            <p class="muted">Create a category first to add its subcategories.</p>
        <?php else: ?>
            <form method="post" class="module-form">
                <?php if ($editSubcategory): ?>
                    <input type="hidden" name="form" value="subcategory_update">
                    <input type="hidden" name="id" value="<?= (int) $editSubcategory['id'] ?>">
                <?php else: ?>
                    <input type="hidden" name="form" value="subcategory">
                <?php endif; ?>
                <?php if (!$editSubcategory): ?>
                    <label>
                        Parent category
                        <select name="category_id" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?> (<?= $category['type'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <label>
                    Subcategory name
                    <input type="text" name="name" required value="<?= htmlspecialchars($editSubcategory['name'] ?? '') ?>">
                </label>
                <button type="submit"><?= $editSubcategory ? 'Update subcategory' : 'Add subcategory' ?></button>
                <?php if ($editSubcategory): ?>
                    <a class="secondary" href="?module=categories">Cancel</a>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </section>

    <section class="module-panel">
        <h2>Category listing</h2>
        <?php if (count($categories) === 0): ?>
            <p class="muted">No categories defined yet.</p>
        <?php else: ?>
            <div class="category-list">
                <?php foreach ($categories as $category): ?>
                    <article class="category-card" id="cat-card-<?= (int) $category['id'] ?>">
                        <header>
                            <strong><?= htmlspecialchars($category['name']) ?></strong>
                            <span class="pill"><?= ucfirst($category['type']) ?></span>
                            <?php if (!empty($category['is_fuel'])): ?>
                                <span class="pill card--orange">Fuel</span>
                            <?php endif; ?>
                            <?php if (!empty($category['exclude_from_analytics'])): ?>
                                <span class="pill pill--muted cat-excluded-badge-<?= (int) $category['id'] ?>">Excluded from analytics</span>
                            <?php else: ?>
                                <span class="pill pill--muted cat-excluded-badge-<?= (int) $category['id'] ?>" style="display:none;">Excluded from analytics</span>
                            <?php endif; ?>
                            <a class="secondary" href="?module=categories&edit_cat=<?= (int) $category['id'] ?>">Edit</a>
                            <button type="button" class="secondary cat-exclude-toggle"
                                style="font-size:0.75rem;padding:0.2rem 0.6rem;"
                                data-id="<?= (int) $category['id'] ?>"
                                data-excluded="<?= !empty($category['exclude_from_analytics']) ? '1' : '0' ?>">
                                <?= !empty($category['exclude_from_analytics']) ? 'Include in analytics' : 'Exclude from analytics' ?>
                            </button>
                        </header>
                        <?php if (count($category['subcategories']) === 0): ?>
                            <p class="muted">No subcategories.</p>
                        <?php else: ?>
                            <ul>
                                <?php foreach ($category['subcategories'] as $sub): ?>
                                    <li>
                                        <?= htmlspecialchars($sub['name']) ?>
                                        <a class="secondary" href="?module=categories&edit_sub=<?= (int) $sub['id'] ?>">Edit</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<script>
    document.querySelectorAll('.cat-exclude-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id       = btn.dataset.id;
            const excluded = btn.dataset.excluded === '1';
            const badge    = document.querySelector('.cat-excluded-badge-' + id);

            fetch('?module=categories', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'form=toggle_analytics_exclude&category_id=' + id,
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data.ok) return;
                const nowExcluded = !excluded;
                btn.dataset.excluded = nowExcluded ? '1' : '0';
                btn.textContent = nowExcluded ? 'Include in analytics' : 'Exclude from analytics';
                if (badge) badge.style.display = nowExcluded ? '' : 'none';
            });
        });
    });
</script>
</main>
