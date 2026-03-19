USE expensemanager;

SET @db_name = DATABASE();

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        "ALTER TABLE loans ADD COLUMN repayment_type ENUM('emi','interest_only') NOT NULL DEFAULT 'emi' AFTER loan_name",
        'SELECT 1'
    )
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'loans'
      AND column_name = 'repayment_type'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE loans
SET repayment_type = 'emi'
WHERE repayment_type IS NULL OR repayment_type = '';