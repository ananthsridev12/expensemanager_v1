USE expensemanager;

SET @db_name = DATABASE();

CREATE TABLE IF NOT EXISTS payment_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_system TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS purchase_sources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_purchase_sources_parent FOREIGN KEY (parent_id) REFERENCES purchase_sources(id) ON DELETE CASCADE,
    UNIQUE KEY uq_purchase_sources_parent_name (parent_id, name)
) ENGINE=InnoDB;

INSERT IGNORE INTO payment_methods (name, is_system) VALUES
('Gpay', 1),
('Phonepe', 1),
('Amazon Pay', 1),
('Cred', 1),
('POS Card Swipe/Tap', 1),
('Payment Gateway', 1),
('PayTM Wallet', 1),
('Other', 1);

INSERT IGNORE INTO purchase_sources (parent_id, name) VALUES
(NULL, 'Daily Essentials'),
(NULL, 'Fuel & Utilities'),
(NULL, 'Online & Digital'),
(NULL, 'Retail & Lifestyle'),
(NULL, 'Food & Leisure'),
(NULL, 'Business & Services'),
(NULL, 'Other');

SET @p_daily = (SELECT id FROM purchase_sources WHERE parent_id IS NULL AND name = 'Daily Essentials' LIMIT 1);
SET @p_fuel = (SELECT id FROM purchase_sources WHERE parent_id IS NULL AND name = 'Fuel & Utilities' LIMIT 1);
SET @p_online = (SELECT id FROM purchase_sources WHERE parent_id IS NULL AND name = 'Online & Digital' LIMIT 1);
SET @p_retail = (SELECT id FROM purchase_sources WHERE parent_id IS NULL AND name = 'Retail & Lifestyle' LIMIT 1);
SET @p_food = (SELECT id FROM purchase_sources WHERE parent_id IS NULL AND name = 'Food & Leisure' LIMIT 1);
SET @p_business = (SELECT id FROM purchase_sources WHERE parent_id IS NULL AND name = 'Business & Services' LIMIT 1);
SET @p_other = (SELECT id FROM purchase_sources WHERE parent_id IS NULL AND name = 'Other' LIMIT 1);

INSERT IGNORE INTO purchase_sources (parent_id, name) VALUES
(@p_daily, 'Vegetable Store'),
(@p_daily, 'Fruit Shop'),
(@p_daily, 'Maligai Kadai'),
(@p_daily, 'Aavin Milk'),
(@p_daily, 'Rajan Stores'),
(@p_daily, 'Maavu Kadai'),
(@p_daily, 'Medplus'),
(@p_daily, 'Water Can'),
(@p_daily, 'Local Stores'),
(@p_daily, 'Watercan Vehicle'),
(@p_fuel, 'Bharat Petroleum'),
(@p_fuel, 'Shell Petrol Bunk'),
(@p_fuel, 'TANGEDCO'),
(@p_fuel, 'Indane'),
(@p_fuel, 'Jio Fibre'),
(@p_fuel, 'Hathway'),
(@p_online, 'Flipkart'),
(@p_online, 'Amazon'),
(@p_online, 'Google Ads'),
(@p_online, 'IRCTC'),
(@p_online, 'Zepto'),
(@p_online, 'Ajio/Trends'),
(@p_retail, 'Saravana Stores'),
(@p_retail, 'The Chennai Silks'),
(@p_retail, 'Clothing Stores'),
(@p_retail, 'DMart'),
(@p_retail, 'Toys Shop'),
(@p_food, 'Hotel'),
(@p_food, 'Snacks Shop'),
(@p_food, 'Sweets and Bakery'),
(@p_food, 'Tea Kadai'),
(@p_business, 'Zerotha'),
(@p_business, 'DhanLAP - Office Spent'),
(@p_other, 'Question type'),
(@p_other, 'Other');

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE transactions ADD COLUMN payment_method_id INT UNSIGNED NULL AFTER subcategory_id',
        'SELECT 1'
    )
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'transactions'
      AND column_name = 'payment_method_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE transactions ADD COLUMN contact_id INT UNSIGNED NULL AFTER payment_method_id',
        'SELECT 1'
    )
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'transactions'
      AND column_name = 'contact_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE transactions ADD COLUMN purchase_source_id INT UNSIGNED NULL AFTER contact_id',
        'SELECT 1'
    )
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'transactions'
      AND column_name = 'purchase_source_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE transactions ADD CONSTRAINT fk_transactions_payment_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = @db_name
      AND constraint_name = 'fk_transactions_payment_method'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE transactions ADD CONSTRAINT fk_transactions_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = @db_name
      AND constraint_name = 'fk_transactions_contact'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE transactions ADD CONSTRAINT fk_transactions_purchase_source FOREIGN KEY (purchase_source_id) REFERENCES purchase_sources(id) ON DELETE SET NULL',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = @db_name
      AND constraint_name = 'fk_transactions_purchase_source'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX idx_transactions_payment_method ON transactions(payment_method_id)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'transactions'
      AND index_name = 'idx_transactions_payment_method'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX idx_transactions_contact ON transactions(contact_id)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'transactions'
      AND index_name = 'idx_transactions_contact'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX idx_transactions_purchase_source ON transactions(purchase_source_id)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'transactions'
      AND index_name = 'idx_transactions_purchase_source'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
