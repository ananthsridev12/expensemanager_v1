-- Migration 017: add deposit_refund to rented_home_expenses expense_type ENUM
ALTER TABLE rented_home_expenses
  MODIFY COLUMN expense_type
    ENUM('advance','rent','maintenance','electricity','other','deposit_refund')
    NOT NULL;
