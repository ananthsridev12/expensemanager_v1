# Finance Manager (Prototype)

## Local Setup
1. Place this folder inside `xampp/htdocs` so Apache can serve it (e.g., `C:/xampp/htdocs/finance-manager`).
2. Create a MySQL database named `finance_manager` using phpMyAdmin or CLI.
3. Update `config/database.php` if credentials differ (default: root, empty password).
4. Hit `http://localhost/finance-manager` once XAMPP Apache + MySQL are running.
5. Iterate modules starting from Core System -> Dashboard as defined in the specification.

## Structure
- `config/` ? PDO connection, future environment loader
- `models/` ? ledger-centric models with a shared base
- `controllers/` ? request handlers that depend on the PDO connector
- `views/` ? modules and dashboard views
- `public/` ? static assets (CSS/JS)
- `index.php` ? bootstrap file showing the module roadmap

## Next Steps
- Wire PSR-4 autoloading via Composer or custom loader
- Draft migration SQL for each ledger table (accounts, transactions, loans, etc.)
- Start implementing modules in order: Accounts, Categories, Transactions, Loans, etc.

## Mobile API (Phase 1)

JSON API entrypoint: `api/index.php` (with `api/.htaccess` rewrite).

### Endpoints
- `POST /api/v1/auth/pin-login`
- `GET /api/v1/dashboard/summary`
- `GET /api/v1/accounts`
- `GET /api/v1/transactions?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&page=1&limit=20`
- `POST /api/v1/transactions`
- `GET /api/v1/categories`
- `GET /api/v1/subcategories?category_id=1`

### API migration
Import `sql_migrate_api_mobile.sql` once.

It creates:
- `api_users`
- `api_sessions`

Default seeded mobile PIN is `1234`. Update it after first login.

### Production auth secret
Set environment variable `API_JWT_SECRET` in hosting/server config.

## DB credentials per environment

To avoid git pull overriding hosting DB credentials:

1. Keep `config/database.php` as default/local.
2. Copy `config/database.override.example.php` to `config/database.override.php`.
3. Set hosting DB values in `config/database.override.php`.

`config/database.override.php` is ignored by git.
