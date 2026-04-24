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

## DB migrations
- `migrations/001_lending_repayments.sql` — creates lending_repayments table + seed data — **already run on server**
- `migrations/002_cc_live_balance.sql` — resets credit_cards.outstanding_balance/principal to opening values — **run once in phpMyAdmin, do NOT run again**

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

## Credit card balance (important design decision)
- `credit_cards.outstanding_balance` = **opening/initial value only** (what was owed before using the app)
- Live outstanding is calculated in `Account::getAllWithBalances()`:
  `GREATEST(0, cc.outstanding_balance + SUM(expense) - SUM(income))`
- `applyTransactionMovement()` is intentionally a no-op — do NOT restore it
- `outstanding_principal` = EMI principal tracking only, separate from balance
- If live balance shows 0 unexpectedly: check if income transactions > expense transactions
  Fix = set opening to: actual_outstanding - net_transactions (run diagnostic query in NOTES below)

## Credit card diagnostic query
```sql
SELECT
    a.account_name,
    cc.outstanding_balance AS opening_set,
    COALESCE(SUM(CASE
        WHEN t.transaction_type = 'expense' THEN t.amount
        WHEN t.transaction_type = 'income'  THEN -t.amount
        ELSE 0
    END), 0) AS net_transactions,
    GREATEST(0, cc.outstanding_balance + COALESCE(SUM(CASE
        WHEN t.transaction_type = 'expense' THEN t.amount
        WHEN t.transaction_type = 'income'  THEN -t.amount
        ELSE 0
    END), 0)) AS live_outstanding_now
FROM credit_cards cc
JOIN accounts a ON a.id = cc.account_id
LEFT JOIN transactions t ON t.account_id = a.id
GROUP BY cc.id;
```
To fix a card: set opening = actual_bank_outstanding - net_transactions

## Accounts page
- Accounts grouped by type: Savings / Current / Credit Cards / Cash / Wallets / Other
- Credit card section shows: Outstanding | Limit | Available as separate columns
- Groups with 2+ accounts show a totals row

## Auto-deploy
- GitHub Actions triggers on push to `master` or `feature/analytics-phase2-3`
- Calls cPanel UAPI VersionControlDeployment/create with API token
- Secrets: CPANEL_USERNAME, CPANEL_API_TOKEN, CPANEL_DOMAIN, CPANEL_REPO_ROOT
