USE expensemanager;

SET @db_name = DATABASE();

CREATE TABLE IF NOT EXISTS account_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    system_key VARCHAR(40) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO account_types (name, system_key) VALUES
('Savings', 'savings'),
('Current', 'current'),
('Credit Card', 'credit_card'),
('Cash', 'cash'),
('Wallet', 'wallet'),
('Other', 'other');

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE accounts ADD COLUMN account_type_id INT UNSIGNED NULL AFTER account_type',
        'SELECT 1'
    )
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'accounts'
      AND column_name = 'account_type_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE accounts ADD CONSTRAINT fk_accounts_type FOREIGN KEY (account_type_id) REFERENCES account_types(id) ON DELETE SET NULL',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = @db_name
      AND constraint_name = 'fk_accounts_type'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX idx_accounts_type_id ON accounts(account_type_id)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'accounts'
      AND index_name = 'idx_accounts_type_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE credit_cards ADD COLUMN points_balance DECIMAL(16,2) NOT NULL DEFAULT 0.00',
        'SELECT 1'
    )
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'credit_cards'
      AND column_name = 'points_balance'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS credit_card_rewards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    credit_card_id INT UNSIGNED NOT NULL,
    points_redeemed DECIMAL(16,2) NOT NULL,
    rate_per_point DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
    cash_value DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    redemption_date DATE NOT NULL,
    deposit_account_id INT UNSIGNED NULL,
    deposit_account_type VARCHAR(32) NOT NULL DEFAULT 'savings',
    transaction_id INT UNSIGNED NULL,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rewards_credit_card FOREIGN KEY (credit_card_id) REFERENCES credit_cards(id) ON DELETE CASCADE,
    CONSTRAINT fk_rewards_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX idx_rewards_card_date ON credit_card_rewards(credit_card_id, redemption_date)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'credit_card_rewards'
      AND index_name = 'idx_rewards_card_date'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
