USE expensemanager;

SET @db_name = DATABASE();

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        "ALTER TABLE contacts ADD COLUMN contact_type ENUM('tenant','lending','both','other') NOT NULL DEFAULT 'other' AFTER state",
        'SELECT 1'
    )
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'contacts'
      AND column_name = 'contact_type'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE tenants ADD COLUMN contact_id INT UNSIGNED NULL AFTER id',
        'SELECT 1'
    )
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'tenants'
      AND column_name = 'contact_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX idx_tenants_contact_id ON tenants(contact_id)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'tenants'
      AND index_name = 'idx_tenants_contact_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE tenants ADD CONSTRAINT fk_tenants_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = @db_name
      AND constraint_name = 'fk_tenants_contact'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;