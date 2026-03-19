-- Personal Finance Manager schema generated from the technical specification.
-- Run: mysql -u root -p finance_manager < schema.sql

CREATE DATABASE IF NOT EXISTS de2shrnx_expensemanager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE de2shrnx_expensemanager;

-- Accounts represent bank or wallet ledgers; balances are computed from transactions.
CREATE TABLE accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bank_name VARCHAR(150) NOT NULL,
    account_name VARCHAR(150) NOT NULL,
    account_number VARCHAR(64),
    ifsc VARCHAR(32),
    opening_balance DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Credit card cards, limits, billing cycle info.
CREATE TABLE credit_cards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bank_name VARCHAR(150) NOT NULL,
    card_name VARCHAR(150) NOT NULL,
    credit_limit DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    billing_date TINYINT UNSIGNED NOT NULL,
    due_date TINYINT UNSIGNED NOT NULL,
    outstanding_balance DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Loans and associated financial parameters.
CREATE TABLE loans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_type VARCHAR(64) NOT NULL,
    loan_name VARCHAR(150) NOT NULL,
    repayment_type ENUM('emi','interest_only') NOT NULL DEFAULT 'emi',
    principal_amount DECIMAL(16,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    tenure_months SMALLINT UNSIGNED NOT NULL,
    emi_amount DECIMAL(16,2) NOT NULL,
    processing_fee DECIMAL(16,2) DEFAULT 0.00,
    gst DECIMAL(5,2) DEFAULT 0.00,
    start_date DATE NOT NULL,
    outstanding_principal DECIMAL(16,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE loan_emi_schedule (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id INT UNSIGNED NOT NULL,
    emi_date DATE NOT NULL,
    principal_component DECIMAL(16,2) NOT NULL,
    interest_component DECIMAL(16,2) NOT NULL,
    total_amount DECIMAL(16,2) GENERATED ALWAYS AS (principal_component + interest_component) STORED,
    status ENUM('pending','paid','missed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Categories and subcategories for transactions.
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    type ENUM('income','expense','transfer') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE subcategories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Contacts for lending and rental.
CREATE TABLE contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    mobile VARCHAR(20),
    email VARCHAR(150),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    contact_type ENUM('tenant','lending','both','other') NOT NULL DEFAULT 'other',
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Lending exposures.
CREATE TABLE lending_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id INT UNSIGNED NOT NULL,
    principal_amount DECIMAL(16,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    lending_date DATE NOT NULL,
    due_date DATE,
    total_repaid DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    outstanding_amount DECIMAL(16,2) NOT NULL,
    status ENUM('ongoing','closed','defaulted') NOT NULL DEFAULT 'ongoing',
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Investments and their transactions without ever overwriting history.
CREATE TABLE investments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('mutual_fund','equity','fd','rd','other') NOT NULL,
    name VARCHAR(150) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE investment_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    investment_id INT UNSIGNED NOT NULL,
    transaction_type ENUM('buy','sell','dividend') NOT NULL,
    amount DECIMAL(16,2) NOT NULL,
    units DECIMAL(20,8) DEFAULT 0.00000000,
    transaction_date DATE NOT NULL,
    account_id INT UNSIGNED,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (investment_id) REFERENCES investments(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- SIP automation schedule and tracking.
CREATE TABLE sip_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    investment_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    sip_amount DECIMAL(16,2) NOT NULL,
    sip_day TINYINT UNSIGNED NOT NULL,
    frequency ENUM('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
    start_date DATE NOT NULL,
    end_date DATE,
    next_run_date DATE,
    status ENUM('active','paused','ended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (investment_id) REFERENCES investments(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Properties and rental management.
CREATE TABLE properties (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    property_name VARCHAR(200) NOT NULL,
    address TEXT,
    monthly_rent DECIMAL(16,2) NOT NULL,
    security_deposit DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id INT UNSIGNED,
    name VARCHAR(150) NOT NULL,
    mobile VARCHAR(20),
    email VARCHAR(150),
    id_proof VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE rental_contracts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    property_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    deposit_amount DECIMAL(16,2) DEFAULT 0.00,
    rent_amount DECIMAL(16,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rental_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id INT UNSIGNED NOT NULL,
    rent_month DATE NOT NULL,
    due_date DATE NOT NULL,
    paid_amount DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('pending','partial','paid','overdue') NOT NULL DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES rental_contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Reminder engine for bills/EMIs/etc.
CREATE TABLE reminders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    amount DECIMAL(16,2),
    frequency ENUM('once','monthly','quarterly','yearly') NOT NULL,
    next_due_date DATE NOT NULL,
    status ENUM('upcoming','completed','missed') NOT NULL DEFAULT 'upcoming',
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE bills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    amount DECIMAL(16,2) NOT NULL,
    due_date DATE NOT NULL,
    paid_amount DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','paid','missed') NOT NULL DEFAULT 'pending',
    reminder_id INT UNSIGNED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reminder_id) REFERENCES reminders(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Central ledger for every transaction.
CREATE TABLE transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_date DATE NOT NULL,
    account_type VARCHAR(32) NOT NULL,
    account_id INT UNSIGNED,
    transaction_type ENUM('income','expense','transfer') NOT NULL,
    category_id INT UNSIGNED,
    subcategory_id INT UNSIGNED,
    amount DECIMAL(16,2) NOT NULL,
    reference_type VARCHAR(64),
    reference_id INT UNSIGNED,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Additional helper indexes for performance.
CREATE INDEX idx_transactions_account ON transactions(account_id);
CREATE INDEX idx_transactions_category ON transactions(category_id);
CREATE INDEX idx_transactions_subcategory ON transactions(subcategory_id);
CREATE INDEX idx_loan_schedule_loan ON loan_emi_schedule(loan_id);
CREATE INDEX idx_rental_tx_contract ON rental_transactions(contract_id);
CREATE INDEX idx_investment_tx_investment ON investment_transactions(investment_id);
CREATE INDEX idx_investment_tx_account ON investment_transactions(account_id);
CREATE INDEX idx_sip_schedule_investment ON sip_schedules(investment_id);
CREATE INDEX idx_sip_schedule_account ON sip_schedules(account_id);

COMMIT;
