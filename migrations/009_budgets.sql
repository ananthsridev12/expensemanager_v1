-- Migration 009: Budgets
-- Run once on local and server

CREATE TABLE IF NOT EXISTS budgets (
    id          INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)        NOT NULL,
    category_id INT UNSIGNED        NULL,
    amount      DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
    month       TINYINT UNSIGNED    NULL COMMENT '1–12; NULL = recurring every month',
    year        SMALLINT UNSIGNED   NULL COMMENT 'NULL = recurring every month',
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_budgets_category
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
