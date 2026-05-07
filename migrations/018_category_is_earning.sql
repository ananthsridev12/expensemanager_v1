-- Migration 018: add is_earning flag to categories
-- Marks income categories that represent actual earnings (salary, freelance, business)
-- so analytics can distinguish real earnings from pass-through income (refunds, transfers, etc.)
ALTER TABLE categories
    ADD COLUMN is_earning TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = real earnings category (salary, freelance, business income)';
