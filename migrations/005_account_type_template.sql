-- Migration 005: Add template field to account_types
-- Run once in phpMyAdmin

ALTER TABLE account_types
    ADD COLUMN template VARCHAR(50) NULL DEFAULT NULL
        COMMENT 'System type key this custom type is based on (savings, current, credit_card, cash, wallet, other)';
