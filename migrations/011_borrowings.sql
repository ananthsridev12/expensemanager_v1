-- Migration 011: Borrowings module
-- Records money the user borrows FROM contacts

CREATE TABLE IF NOT EXISTS borrowing_records (
    id                INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    contact_id        INT UNSIGNED    NOT NULL,
    principal_amount  DECIMAL(12,2)   NOT NULL,
    interest_rate     DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    borrowed_date     DATE            NOT NULL,
    due_date          DATE            NULL,
    total_repaid      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    outstanding_amount DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    status            ENUM('ongoing','closed') NOT NULL DEFAULT 'ongoing',
    notes             TEXT            NULL,
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_borrowing_contact FOREIGN KEY (contact_id) REFERENCES contacts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS borrowing_repayments (
    id                  INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    borrowing_record_id INT UNSIGNED    NOT NULL,
    amount              DECIMAL(12,2)   NOT NULL,
    repayment_date      DATE            NOT NULL,
    payment_account_type VARCHAR(50)    NULL,
    payment_account_id  INT             NULL,
    notes               TEXT            NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_borrep_record FOREIGN KEY (borrowing_record_id) REFERENCES borrowing_records(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
