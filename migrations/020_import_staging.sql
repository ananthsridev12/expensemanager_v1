-- Migration 020: import staging table for manual review before committing to transactions
CREATE TABLE IF NOT EXISTS import_staging (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id           INT UNSIGNED NOT NULL,
    transaction_date   DATE NOT NULL,
    description        TEXT NOT NULL,
    amount             DECIMAL(12,2) NOT NULL,
    transaction_type   ENUM('income','expense') NOT NULL,
    reference          VARCHAR(255) NOT NULL DEFAULT '',
    closing_balance    DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_duplicate_flag  TINYINT(1) NOT NULL DEFAULT 0,
    -- reviewer fills these before approving:
    notes              TEXT,
    category_id        INT UNSIGNED DEFAULT NULL,
    subcategory_id     INT UNSIGNED DEFAULT NULL,
    payment_method_id  INT UNSIGNED DEFAULT NULL,
    contact_id         INT UNSIGNED DEFAULT NULL,
    -- outcome:
    status             ENUM('pending','approved','skipped') NOT NULL DEFAULT 'pending',
    approved_tx_id     INT UNSIGNED DEFAULT NULL,
    reviewed_at        TIMESTAMP NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch_status (batch_id, status),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
