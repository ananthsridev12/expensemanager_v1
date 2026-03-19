USE expensemanager;

CREATE TABLE IF NOT EXISTS credit_card_emi_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    credit_card_id INT UNSIGNED NOT NULL,
    plan_name VARCHAR(180) NOT NULL,
    principal_amount DECIMAL(16,2) NOT NULL,
    outstanding_principal DECIMAL(16,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    tenure_months SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    emi_amount DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    processing_fee DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    start_date DATE NOT NULL,
    next_due_date DATE NOT NULL,
    total_emis SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    paid_emis SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('active','closed','paused') NOT NULL DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cc_emi_plan_card FOREIGN KEY (credit_card_id) REFERENCES credit_cards(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS credit_card_emi_schedule (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    emi_plan_id INT UNSIGNED NOT NULL,
    installment_no SMALLINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    opening_principal DECIMAL(16,2) NOT NULL,
    principal_component DECIMAL(16,2) NOT NULL,
    interest_component DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    processing_fee DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    gst_amount DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    total_due DECIMAL(16,2) NOT NULL,
    status ENUM('upcoming','pending','paid','skipped') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cc_emi_schedule_plan FOREIGN KEY (emi_plan_id) REFERENCES credit_card_emi_plans(id) ON DELETE CASCADE,
    UNIQUE KEY uq_cc_emi_installment (emi_plan_id, installment_no)
) ENGINE=InnoDB;

SET @db_name = DATABASE();

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX idx_cc_emi_plan_card ON credit_card_emi_plans(credit_card_id)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'credit_card_emi_plans'
      AND index_name = 'idx_cc_emi_plan_card'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX idx_cc_emi_plan_status_due ON credit_card_emi_plans(status, next_due_date)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'credit_card_emi_plans'
      AND index_name = 'idx_cc_emi_plan_status_due'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX idx_cc_emi_schedule_due ON credit_card_emi_schedule(due_date, status)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'credit_card_emi_schedule'
      AND index_name = 'idx_cc_emi_schedule_due'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optional cleanup if you do not want legacy payment tables anymore.
-- DROP TABLE IF EXISTS credit_card_emi_payments;
-- DROP TABLE IF EXISTS credit_card_payments;
