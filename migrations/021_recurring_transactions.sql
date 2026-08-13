-- Migration 021: Recurring transaction templates + pending approval queue

CREATE TABLE IF NOT EXISTS recurring_transactions (
    id                INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(255)    NOT NULL,
    transaction_type  ENUM('income','expense') NOT NULL DEFAULT 'expense',
    account_type      VARCHAR(50)     NOT NULL DEFAULT 'savings',
    account_id        INT UNSIGNED    DEFAULT NULL,
    amount            DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    category_id       INT UNSIGNED    DEFAULT NULL,
    subcategory_id    INT UNSIGNED    DEFAULT NULL,
    payment_method_id INT UNSIGNED    DEFAULT NULL,
    contact_id        INT UNSIGNED    DEFAULT NULL,
    notes             TEXT            DEFAULT NULL,
    frequency         ENUM('daily','weekly','monthly','yearly') NOT NULL DEFAULT 'monthly',
    day_of_month      TINYINT UNSIGNED DEFAULT NULL COMMENT 'Pin to a specific day for monthly frequency',
    next_due_date     DATE            NOT NULL,
    end_date          DATE            DEFAULT NULL,
    is_active         TINYINT(1)      NOT NULL DEFAULT 1,
    created_at        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active_due (is_active, next_due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recurring_pending (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recurring_id   INT UNSIGNED NOT NULL,
    due_date       DATE         NOT NULL,
    status         ENUM('pending','approved','skipped') NOT NULL DEFAULT 'pending',
    transaction_id INT UNSIGNED DEFAULT NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    reviewed_at    TIMESTAMP    NULL DEFAULT NULL,
    INDEX idx_status (status),
    INDEX idx_recurring_date (recurring_id, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
