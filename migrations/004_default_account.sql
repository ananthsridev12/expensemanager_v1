-- Migration 004: Add is_default flag to accounts
-- Run once in phpMyAdmin

ALTER TABLE accounts
    ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0;
