-- Add subcategory_id to budgets table (0 = category-level budget, >0 = subcategory-level)
ALTER TABLE budgets
    ADD COLUMN subcategory_id INT NOT NULL DEFAULT 0 AFTER category_id;

-- Replace old unique key with new one that includes subcategory_id
ALTER TABLE budgets
    DROP INDEX idx_budget_cat_period,
    ADD UNIQUE KEY idx_budget_cat_sub_period (category_id, subcategory_id, month, year);
