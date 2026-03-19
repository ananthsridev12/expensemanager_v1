# Project Notes — PersonalFin (ExpenseManager v5)

## Deployment
- Repo: https://github.com/ananthsridev12/expensemanager_v1.git
- Server: /home1/de2shrnx/personalfin.easi7.in/
- Deploy: cPanel Git Version Control → Deploy HEAD Commit
- `config/database.php` is NOT in git — maintain manually on server
- `config/pin.php` IS in git and deployed

## Active branch
- `feature/analytics-phase2-3` — all recent work is here, not yet merged to master

## Key accounting rules
- Account balance = `opening_balance + SUM(income) - SUM(expense)`
- `transfer` type does NOT affect account balance
- Lending disbursal → `expense` on account side + `transfer` on lending side
- Lending repayment → `income` on account side + `transfer` on lending side
- Investment buy → `expense` on account side + `transfer` on investment side
- Investment sell → `income` on account side + `transfer` on investment side
- Group spend: creates expense (your share) + lending record (remainder) automatically

## Lending module
- `lending_records` — master record with principal, status, outstanding (stored as cache)
- `lending_repayments` — individual repayment rows (source of truth)
- Outstanding is calculated LIVE from `SUM(lending_repayments.amount)` in all queries
- `lending_records.outstanding_amount` is synced on each `recordRepayment()` call
- After manual inserts into `lending_repayments`, run the sync query:
```sql
UPDATE lending_records lr
SET
    total_repaid       = COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = lr.id), 0),
    outstanding_amount = GREATEST(0, lr.principal_amount - COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = lr.id), 0)),
    status             = CASE WHEN GREATEST(0, lr.principal_amount - COALESCE((SELECT SUM(lrp.amount) FROM lending_repayments lrp WHERE lrp.lending_record_id = lr.id), 0)) <= 0 THEN 'closed' ELSE status END;
```

## Lending record #11
- Contains repayments from: AnanthSridev, Vijayakumar, Arunika, Sriram
- All inserted manually via migrations/001_lending_repayments.sql

## DB migrations run on server
- `migrations/001_lending_repayments.sql` — creates lending_repayments table + seed data

## Analytics features (feature/analytics-phase2-3)
- Phase 1: Monthly income vs expense bar, cashflow line, income/expense donuts, monthly table
- Phase 2: Account-wise expense horizontal bar, day-of-week spend bar
- Phase 3: Dashboard "This month at a glance" — income/expense/net with % vs last month + 6-month sparkline
- Drill-down: Filter by date, type, category, subcategory, purchased from → charts + transaction list

## Transaction types
- `income` / `expense` — affect account balance
- `transfer` — does NOT affect account balance (used for module mirrors: lending, investment)
- Income is only from category "Earnings" (id=1) for earnings-specific reports
- All other analytics use all income/expense transactions

## Group spend flow
- Transaction type must be **Expense**
- Enter total amount paid, check group spend, enter your share
- System creates: expense of your share + lending record for remainder
