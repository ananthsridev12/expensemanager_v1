USE expensemanager;

SET @db_name = DATABASE();

-- 1) Accounts must carry account_type so credit cards can live in the same module.
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        "ALTER TABLE accounts ADD COLUMN account_type ENUM('savings','current','credit_card','cash','other') NOT NULL DEFAULT 'savings' AFTER account_name",
        'SELECT 1'
    )
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'accounts'
      AND column_name = 'account_type'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE accounts
SET account_type = 'savings'
WHERE account_type IS NULL OR account_type = '';

-- 2) Ensure credit_cards has a link to the owning account row.
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE credit_cards ADD COLUMN account_id INT UNSIGNED NULL AFTER id',
        'SELECT 1'
    )
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'credit_cards'
      AND column_name = 'account_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Backfill account rows for cards that do not have account_id yet.
INSERT INTO accounts (bank_name, account_name, account_type, account_number, opening_balance)
SELECT cc.bank_name, cc.card_name, 'credit_card', CONCAT('CC-', cc.id), 0.00
FROM credit_cards cc
LEFT JOIN accounts a ON a.id = cc.account_id
WHERE cc.account_id IS NULL OR a.id IS NULL;

UPDATE credit_cards cc
JOIN accounts a
  ON a.account_type = 'credit_card'
 AND a.account_number = CONCAT('CC-', cc.id)
SET cc.account_id = a.id
WHERE cc.account_id IS NULL;

-- Fallback mapping when account_number marker was not available.
UPDATE credit_cards cc
JOIN accounts a
  ON a.account_type = 'credit_card'
 AND a.bank_name = cc.bank_name
 AND a.account_name = cc.card_name
SET cc.account_id = a.id
WHERE cc.account_id IS NULL;

-- 4) Add unique index on credit_cards.account_id if missing.
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE UNIQUE INDEX uq_credit_cards_account_id ON credit_cards(account_id)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'credit_cards'
      AND index_name = 'uq_credit_cards_account_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5) Add FK only once.
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE credit_cards ADD CONSTRAINT fk_credit_cards_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = @db_name
      AND constraint_name = 'fk_credit_cards_account'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;