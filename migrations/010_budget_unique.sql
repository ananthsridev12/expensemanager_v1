-- Migration 010: Unique constraint on budgets for upsert support
-- Run once — safe if budgets table is empty (just created in 009)

ALTER TABLE budgets
    ADD UNIQUE KEY idx_budget_cat_period (category_id, month, year);
