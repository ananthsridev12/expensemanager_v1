-- Migration 019: bank CSV import batch tracking
CREATE TABLE IF NOT EXISTS import_batches (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label         VARCHAR(255) NOT NULL,
    bank_parser   VARCHAR(150) NOT NULL,
    account_id    INT UNSIGNED NOT NULL,
    account_type  VARCHAR(50)  NOT NULL DEFAULT 'savings',
    row_count     INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
