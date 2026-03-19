USE expensemanager;

SET @db_name = DATABASE();

SET @sql = (
    SELECT IF(
        LOCATE('wallet', COLUMN_TYPE) = 0,
        "ALTER TABLE accounts MODIFY COLUMN account_type ENUM('savings','current','credit_card','cash','wallet','other') NOT NULL DEFAULT 'savings'",
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
