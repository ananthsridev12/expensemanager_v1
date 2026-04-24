-- Migration 002: Reset credit card outstanding_balance and outstanding_principal
-- to their initial (user-entered) values, so balance can be calculated live
-- from the transactions table going forward.
--
-- Background: outstanding_balance was previously updated on every transaction
-- create/delete via applyTransactionMovement(). This caused balance to be stale
-- after transaction edits or direct DB changes. Going forward, balance is
-- calculated live: opening_outstanding + SUM(expense) - SUM(income).
--
-- NOTE: If you have EMI plans that updated outstanding_balance directly
-- (without a matching expense transaction), those amounts will be lost in this
-- reset. Re-enter them manually as opening_outstanding adjustment if needed.

UPDATE credit_cards cc
JOIN accounts a ON a.id = cc.account_id
SET
    cc.outstanding_balance = GREATEST(0, cc.outstanding_balance -
        COALESCE((
            SELECT SUM(CASE
                WHEN t.transaction_type = 'expense' THEN t.amount
                WHEN t.transaction_type = 'income'  THEN -t.amount
                ELSE 0
            END)
            FROM transactions t
            WHERE t.account_id = a.id
        ), 0)
    ),
    cc.outstanding_principal = GREATEST(0, cc.outstanding_principal -
        COALESCE((
            SELECT SUM(CASE
                WHEN t.transaction_type = 'expense' THEN t.amount
                WHEN t.transaction_type = 'income'  THEN -t.amount
                ELSE 0
            END)
            FROM transactions t
            WHERE t.account_id = a.id
        ), 0)
    );
