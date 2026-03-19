CREATE TABLE IF NOT EXISTS lending_repayments (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    lending_record_id   INT            NOT NULL,
    amount              DECIMAL(15,2)  NOT NULL,
    repayment_date      DATE           NOT NULL,
    deposit_account_type VARCHAR(30)   DEFAULT NULL,
    deposit_account_id  INT            DEFAULT NULL,
    notes               TEXT           DEFAULT NULL,
    created_at          TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lr_lending_record FOREIGN KEY (lending_record_id) REFERENCES lending_records(id)
);
