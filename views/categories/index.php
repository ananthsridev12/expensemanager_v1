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
                    <article class="category-card">
                        <header>
                            <strong><?= htmlspecialchars($category['name']) ?></strong>
                            <span class="pill"><?= ucfirst($category['type']) ?></span>
                            <?php if (!empty($category['is_fuel'])): ?>
                                <span class="pill card--orange">Fuel</span>
                            <?php endif; ?>
                            <a class="secondary" href="?module=categories&edit_cat=<?= (int) $category['id'] ?>">Edit</a>
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
</main>
