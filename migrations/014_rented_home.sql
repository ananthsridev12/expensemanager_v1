-- Migration 014: Rented Home module (tenant perspective)
-- Creates tables for tracking homes the user rents, with expense tracking
-- for advance, rent, maintenance, electricity, and other payments.

CREATE TABLE rented_homes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(150) NOT NULL,
  landlord_name VARCHAR(150),
  address TEXT,
  monthly_rent DECIMAL(16,2) NOT NULL DEFAULT 0,
  advance_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
  maintenance_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
  start_date DATE,
  notes TEXT,
  status ENUM('active','vacated') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rented_home_expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  home_id INT UNSIGNED NOT NULL,
  expense_type ENUM('advance','rent','maintenance','electricity','other') NOT NULL,
  amount DECIMAL(16,2) NOT NULL,
  expense_date DATE NOT NULL,
  account_id INT UNSIGNED,
  period_month DATE,
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (home_id) REFERENCES rented_homes(id) ON DELETE CASCADE,
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
