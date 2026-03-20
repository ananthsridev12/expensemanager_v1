-- Migration 003: Fuel surcharge tracking
-- Adds fuel surcharge config to credit_cards and fuel flag to categories

ALTER TABLE credit_cards
    ADD COLUMN fuel_surcharge_rate      DECIMAL(5,2)   NOT NULL DEFAULT 1.00
        COMMENT 'Surcharge % charged on fuel transactions (e.g. 1.00 = 1%)',
    ADD COLUMN fuel_surcharge_min_refund DECIMAL(10,2) NOT NULL DEFAULT 400.00
        COMMENT 'Minimum transaction amount (₹) to qualify for surcharge refund';

ALTER TABLE categories
    ADD COLUMN is_fuel TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = fuel category, used for surcharge tracking';
