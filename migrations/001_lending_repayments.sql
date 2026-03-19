CREATE TABLE IF NOT EXISTS lending_repayments (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    lending_record_id   INT            NOT NULL,
    amount              DECIMAL(15,2)  NOT NULL,
    repayment_date      DATE           NOT NULL,
    deposit_account_type VARCHAR(30)   DEFAULT NULL,
    deposit_account_id  INT            DEFAULT NULL,
    notes               TEXT           DEFAULT NULL,
    created_at          TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
);



 INSERT INTO lending_repayments (lending_record_id, amount, repayment_date, notes) VALUES
  (11, 6441, '2023-08-05', 'AnanthSridev'),
  (11, 6441, '2023-09-05', 'AnanthSridev'),
  (11, 6441, '2023-10-05', 'AnanthSridev'),
  (11, 11441, '2023-11-05', 'AnanthSridev'),
  (11, 6441, '2023-12-05', 'AnanthSridev'),
  (11, 11441, '2024-01-05', 'AnanthSridev'),
  (11, 6441, '2024-02-05', 'AnanthSridev'),
  (11, 6441, '2024-03-05', 'AnanthSridev'),
  (11, 6441, '2024-04-05', 'AnanthSridev'),
  (11, 11441, '2024-05-05', 'AnanthSridev'),
  (11, 11441, '2024-06-05', 'AnanthSridev'),
  (11, 6441, '2024-07-05', 'AnanthSridev'),
  (11, 6441, '2024-08-05', 'AnanthSridev'),
  (11, 6441, '2024-09-05', 'AnanthSridev'),
  (11, 6441, '2024-10-05', 'AnanthSridev'),
  (11, 6441, '2024-11-05', 'AnanthSridev'),
  (11, 6441, '2024-12-05', 'AnanthSridev'),
  (11, 6441, '2025-01-05', 'AnanthSridev'),
  (11, 6441, '2025-02-05', 'AnanthSridev'),
  (11, 6441, '2025-03-05', 'AnanthSridev'),
  (11, 6441, '2025-04-05', 'AnanthSridev'),
  (11, 6441, '2025-05-05', 'AnanthSridev'),
  (11, 6441, '2025-06-05', 'AnanthSridev'),
  (11, 11441, '2025-07-05', 'AnanthSridev'),
  (11, 6441, '2025-08-05', 'AnanthSridev'),
  (11, 6441, '2025-09-05', 'AnanthSridev'),
  (11, 6441, '2025-10-05', 'AnanthSridev'),
  (11, 6441, '2025-11-05', 'AnanthSridev'),
  (11, 6441, '2025-12-05', 'AnanthSridev'),
  (11, 6441, '2026-01-05', 'AnanthSridev'),
  (11, 6441, '2026-02-05', 'AnanthSridev'),
  (11, 11441, '2026-03-05', 'AnanthSridev');

  After running, also run the sync query to update outstanding_amount on the lending record:

  UPDATE lending_records lr
  SET
      total_repaid       = COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = lr.id), 0),
      outstanding_amount = GREATEST(0, lr.principal_amount - COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id =
  lr.id), 0)),
      status             = CASE WHEN GREATEST(0, lr.principal_amount - COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE
  lrp.lending_record_id = lr.id), 0)) <= 0 THEN 'closed' ELSE status END
  WHERE id = 11;
