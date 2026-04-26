-- Reopen lending records that were incorrectly auto-closed by a repayment
-- calculation bug (stored total_repaid/outstanding_amount were used instead of
-- recomputing from lending_repayments). Records where status='closed' but the
-- actual sum of repayments is still less than the principal are restored to
-- 'ongoing' and their cached totals are synced from the repayments table.
UPDATE lending_records lr
JOIN (
    SELECT
        lr2.id,
        COALESCE(SUM(lrp.amount), 0)                                           AS sum_repaid,
        GREATEST(0, lr2.principal_amount - COALESCE(SUM(lrp.amount), 0))       AS computed_outstanding
    FROM lending_records lr2
    LEFT JOIN lending_repayments lrp ON lrp.lending_record_id = lr2.id
    WHERE lr2.status = 'closed'
    GROUP BY lr2.id, lr2.principal_amount
    HAVING computed_outstanding > 0
) AS calc ON calc.id = lr.id
SET
    lr.status             = 'ongoing',
    lr.total_repaid       = calc.sum_repaid,
    lr.outstanding_amount = calc.computed_outstanding;
