<?php

namespace Mcp;

use Config\Database;
use Models\Account;
use Models\Category;
use Models\Transaction;
use Models\Contact;
use Models\PaymentMethod;
use Models\CreditCard;
use Models\Budget;
use Models\Loan;
use Models\Lending;
use Models\Borrowing;
use Models\Rental;
use Models\RentedHome;
use Models\Investment;
use Models\Recurring;
use Models\Import;
use Models\Reminder;
use Models\Note;
use PDO;

class Tools
{
    private Database $database;
    private PDO      $pdo;

    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->pdo      = $database->connect();
    }

    // ── Dispatch ──────────────────────────────────────────────────────────────

    public function call(string $name, array $args): mixed
    {
        return match ($name) {
            // Core
            'get_overview'                  => $this->getOverview(),
            'list_accounts'                 => $this->listAccounts(),
            'list_categories'               => $this->listCategories(),
            'list_payment_methods'          => $this->listPaymentMethods(),
            'search_contacts'               => $this->searchContacts($args),
            'create_contact'                => $this->createContact($args),
            // Transactions
            'list_transactions'             => $this->listTransactions($args),
            'get_transaction'               => $this->getTransaction($args),
            'create_transaction'            => $this->createTransaction($args),
            'delete_transaction'            => $this->deleteTransaction($args),
            // Credit cards
            'list_credit_cards'             => $this->listCreditCards(),
            // Budget
            'list_budgets'                  => $this->listBudgets($args),
            // Loans
            'list_loans'                    => $this->listLoans(),
            'create_loan'                   => $this->createLoan($args),
            'mark_loan_emi_paid'            => $this->markLoanEmiPaid($args),
            // Lending
            'list_lending'                  => $this->listLending(),
            'create_lending'                => $this->createLending($args),
            'record_lending_repayment'      => $this->recordLendingRepayment($args),
            // Borrowing
            'list_borrowing'                => $this->listBorrowing(),
            'create_borrowing'              => $this->createBorrowing($args),
            'record_borrowing_repayment'    => $this->recordBorrowingRepayment($args),
            // Rental
            'list_rental_properties'        => $this->listRentalProperties(),
            'list_rental_contracts'         => $this->listRentalContracts(),
            'record_rental_payment'         => $this->recordRentalPayment($args),
            // Rented home (tenant side)
            'list_rented_homes'             => $this->listRentedHomes(),
            'add_rented_home_expense'       => $this->addRentedHomeExpense($args),
            // Investments
            'list_investments'              => $this->listInvestments(),
            'create_investment_transaction' => $this->createInvestmentTransaction($args),
            // Recurring
            'list_recurring_templates'      => $this->listRecurringTemplates(),
            'list_recurring_pending'        => $this->listRecurringPending(),
            'approve_recurring_pending'     => $this->approveRecurringPending($args),
            'skip_recurring_pending'        => $this->skipRecurringPending($args),
            // Import staging
            'list_import_staging'           => $this->listImportStaging(),
            'approve_import_staging'        => $this->approveImportStaging($args),
            'skip_import_staging'           => $this->skipImportStaging($args),
            // Reminders
            'list_reminders'                => $this->listReminders(),
            'create_reminder'               => $this->createReminder($args),
            // Notes
            'list_notes'                    => $this->listNotes(),
            'get_note'                      => $this->getNote($args),
            'create_note'                   => $this->createNote($args),
            'update_note'                   => $this->updateNote($args),
            'delete_note'                   => $this->deleteNote($args),
            default                         => throw new ToolError("Unknown tool '{$name}'. Use tools/list to see available tools."),
        };
    }

    // ════════════════════════════════════════════════════════════════════════
    // TOOL DEFINITIONS
    // ════════════════════════════════════════════════════════════════════════

    public function getDefinitions(): array
    {
        return [
            // ── Core ─────────────────────────────────────────────────────────
            $this->def('get_overview',
                "Today's overview: server date, account balance totals, pending recurring count, upcoming reminders and EMIs.",
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('list_accounts',
                'List all active accounts with live balances, grouped by type.',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('list_categories',
                'List all expense and income categories with their subcategories and IDs.',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('list_payment_methods',
                'List all payment methods (UPI, cash, NEFT, etc.) with their IDs.',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('search_contacts',
                'Search contacts by name or phone. Returns id, name, mobile, email.',
                ['type' => 'object', 'properties' => [
                    'q' => ['type' => 'string', 'description' => 'Search term (name or mobile)'],
                ], 'required' => ['q']],
                readOnly: true
            ),
            $this->def('create_contact',
                'Create a new contact.',
                ['type' => 'object', 'properties' => [
                    'name'   => ['type' => 'string'],
                    'mobile' => ['type' => 'string'],
                    'email'  => ['type' => 'string'],
                ], 'required' => ['name']],
                readOnly: false
            ),
            // ── Transactions ─────────────────────────────────────────────────
            $this->def('list_transactions',
                'List transactions with optional filters. Returns up to limit rows newest-first.',
                ['type' => 'object', 'properties' => [
                    'start_date'       => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'end_date'         => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'account_id'       => ['type' => 'integer'],
                    'category_id'      => ['type' => 'integer'],
                    'transaction_type' => ['type' => 'string', 'enum' => ['income', 'expense', 'transfer']],
                    'limit'            => ['type' => 'integer', 'default' => 50, 'maximum' => 200],
                ], 'required' => []],
                readOnly: true
            ),
            $this->def('get_transaction',
                'Get full details of a single transaction by ID.',
                ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer'],
                ], 'required' => ['id']],
                readOnly: true
            ),
            $this->def('create_transaction',
                'Create an income, expense, or transfer transaction.',
                ['type' => 'object', 'properties' => [
                    'transaction_date'  => ['type' => 'string', 'description' => 'YYYY-MM-DD (default today)'],
                    'transaction_type'  => ['type' => 'string', 'enum' => ['income', 'expense', 'transfer']],
                    'amount'            => ['type' => 'number'],
                    'account_id'        => ['type' => 'integer', 'description' => 'Source account id from list_accounts'],
                    'to_account_id'     => ['type' => 'integer', 'description' => 'Destination account id (transfers only)'],
                    'category_id'       => ['type' => 'integer'],
                    'subcategory_id'    => ['type' => 'integer'],
                    'payment_method_id' => ['type' => 'integer'],
                    'contact_id'        => ['type' => 'integer'],
                    'notes'             => ['type' => 'string'],
                ], 'required' => ['transaction_type', 'amount', 'account_id']],
                readOnly: false, destructive: false
            ),
            $this->def('delete_transaction',
                'Delete a transaction by ID. Also removes any linked fuel-surcharge companion transactions.',
                ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer'],
                ], 'required' => ['id']],
                readOnly: false, destructive: true
            ),
            // ── Credit cards ─────────────────────────────────────────────────
            $this->def('list_credit_cards',
                'List all credit cards with live outstanding balance, due date and credit limit.',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            // ── Budget ───────────────────────────────────────────────────────
            $this->def('list_budgets',
                'List category budgets with amount spent for a given month/year.',
                ['type' => 'object', 'properties' => [
                    'month' => ['type' => 'integer', 'description' => '1-12 (default current month)'],
                    'year'  => ['type' => 'integer', 'description' => 'e.g. 2025 (default current year)'],
                ], 'required' => []],
                readOnly: true
            ),
            // ── Loans ────────────────────────────────────────────────────────
            $this->def('list_loans',
                'List all active loans with outstanding principal and upcoming EMI schedule.',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('create_loan',
                'Create a new loan record. EMI amount is calculated automatically from principal, rate, tenure.',
                ['type' => 'object', 'properties' => [
                    'lender_name'      => ['type' => 'string'],
                    'principal'        => ['type' => 'number'],
                    'interest_rate'    => ['type' => 'number', 'description' => 'Annual % rate'],
                    'tenure_months'    => ['type' => 'integer'],
                    'disbursement_date'=> ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'emi_day'          => ['type' => 'integer', 'description' => 'Day of month for EMI'],
                    'account_id'       => ['type' => 'integer', 'description' => 'Account to debit EMIs from'],
                    'notes'            => ['type' => 'string'],
                ], 'required' => ['lender_name', 'principal', 'interest_rate', 'tenure_months', 'disbursement_date', 'account_id']],
                readOnly: false
            ),
            $this->def('mark_loan_emi_paid',
                'Mark the next pending EMI as paid and debit the configured account.',
                ['type' => 'object', 'properties' => [
                    'loan_id'    => ['type' => 'integer'],
                    'account_id' => ['type' => 'integer', 'description' => 'Account to debit (overrides default)'],
                    'amount'     => ['type' => 'number',  'description' => 'Override EMI amount if needed'],
                    'paid_date'  => ['type' => 'string',  'description' => 'YYYY-MM-DD (default today)'],
                ], 'required' => ['loan_id']],
                readOnly: false
            ),
            // ── Lending ──────────────────────────────────────────────────────
            $this->def('list_lending',
                'List money lent to contacts with outstanding balance and repayment history.',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('create_lending',
                'Record money lent to a contact.',
                ['type' => 'object', 'properties' => [
                    'contact_id'    => ['type' => 'integer'],
                    'amount'        => ['type' => 'number'],
                    'lent_date'     => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'due_date'      => ['type' => 'string', 'description' => 'YYYY-MM-DD (optional)'],
                    'interest_rate' => ['type' => 'number'],
                    'account_id'    => ['type' => 'integer', 'description' => 'Account funds came from'],
                    'notes'         => ['type' => 'string'],
                ], 'required' => ['contact_id', 'amount', 'lent_date', 'account_id']],
                readOnly: false
            ),
            $this->def('record_lending_repayment',
                'Record a repayment received from a contact for a lending record.',
                ['type' => 'object', 'properties' => [
                    'lending_id'       => ['type' => 'integer'],
                    'amount'           => ['type' => 'number'],
                    'repayment_date'   => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'account_id'       => ['type' => 'integer', 'description' => 'Account money was deposited into'],
                    'notes'            => ['type' => 'string'],
                ], 'required' => ['lending_id', 'amount', 'repayment_date', 'account_id']],
                readOnly: false
            ),
            // ── Borrowing ────────────────────────────────────────────────────
            $this->def('list_borrowing',
                'List money borrowed from contacts with outstanding balance.',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('create_borrowing',
                'Record money borrowed from a contact.',
                ['type' => 'object', 'properties' => [
                    'contact_id'    => ['type' => 'integer'],
                    'amount'        => ['type' => 'number'],
                    'borrowed_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'due_date'      => ['type' => 'string', 'description' => 'YYYY-MM-DD (optional)'],
                    'interest_rate' => ['type' => 'number'],
                    'account_id'    => ['type' => 'integer', 'description' => 'Account money was deposited into'],
                    'notes'         => ['type' => 'string'],
                ], 'required' => ['contact_id', 'amount', 'borrowed_date', 'account_id']],
                readOnly: false
            ),
            $this->def('record_borrowing_repayment',
                'Record a repayment made to a contact for borrowed money.',
                ['type' => 'object', 'properties' => [
                    'borrowing_id'     => ['type' => 'integer'],
                    'amount'           => ['type' => 'number'],
                    'repayment_date'   => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'account_id'       => ['type' => 'integer', 'description' => 'Account debited'],
                    'notes'            => ['type' => 'string'],
                ], 'required' => ['borrowing_id', 'amount', 'repayment_date', 'account_id']],
                readOnly: false
            ),
            // ── Rental ───────────────────────────────────────────────────────
            $this->def('list_rental_properties',
                'List rental properties you own/manage with tenant and contract info.',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('list_rental_contracts',
                'List active rental contracts with rent amount, tenant and property.',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('record_rental_payment',
                'Record a rent payment received from a tenant.',
                ['type' => 'object', 'properties' => [
                    'contract_id'    => ['type' => 'integer'],
                    'amount'         => ['type' => 'number'],
                    'payment_date'   => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'account_id'     => ['type' => 'integer', 'description' => 'Account money deposited into'],
                    'period_month'   => ['type' => 'integer', 'description' => '1-12'],
                    'period_year'    => ['type' => 'integer'],
                    'notes'          => ['type' => 'string'],
                ], 'required' => ['contract_id', 'amount', 'payment_date', 'account_id']],
                readOnly: false
            ),
            // ── Rented home ──────────────────────────────────────────────────
            $this->def('list_rented_homes',
                'List homes you rent as a tenant with expense history.',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('add_rented_home_expense',
                'Add an expense for a rented home (rent, maintenance, electricity, etc.).',
                ['type' => 'object', 'properties' => [
                    'rented_home_id' => ['type' => 'integer'],
                    'expense_type'   => ['type' => 'string', 'enum' => ['advance','rent','maintenance','electricity','other','deposit_refund']],
                    'amount'         => ['type' => 'number'],
                    'expense_date'   => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'account_id'     => ['type' => 'integer'],
                    'period_month'   => ['type' => 'integer'],
                    'period_year'    => ['type' => 'integer'],
                    'notes'          => ['type' => 'string'],
                ], 'required' => ['rented_home_id', 'expense_type', 'amount', 'expense_date', 'account_id']],
                readOnly: false
            ),
            // ── Investments ──────────────────────────────────────────────────
            $this->def('list_investments',
                'List investment portfolio with total invested, current value and recent transactions.',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('create_investment_transaction',
                'Record a buy or sell transaction for an investment.',
                ['type' => 'object', 'properties' => [
                    'investment_id'    => ['type' => 'integer'],
                    'transaction_type' => ['type' => 'string', 'enum' => ['buy', 'sell']],
                    'amount'           => ['type' => 'number'],
                    'units'            => ['type' => 'number', 'description' => 'Number of units (optional)'],
                    'transaction_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'account_id'       => ['type' => 'integer', 'description' => 'Funding account'],
                    'notes'            => ['type' => 'string'],
                ], 'required' => ['investment_id', 'transaction_type', 'amount', 'transaction_date', 'account_id']],
                readOnly: false
            ),
            // ── Recurring ────────────────────────────────────────────────────
            $this->def('list_recurring_templates',
                'List active recurring transaction templates with next due date.',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('list_recurring_pending',
                'List recurring items pending user approval (due but not yet confirmed as transactions).',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('approve_recurring_pending',
                'Approve a pending recurring item — creates the actual transaction. Optionally override amount or notes.',
                ['type' => 'object', 'properties' => [
                    'pending_id'       => ['type' => 'integer'],
                    'transaction_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD (default: due date)'],
                    'amount'           => ['type' => 'number', 'description' => 'Override amount if needed'],
                    'notes'            => ['type' => 'string'],
                ], 'required' => ['pending_id']],
                readOnly: false
            ),
            $this->def('skip_recurring_pending',
                'Skip a pending recurring item (advances the schedule without creating a transaction).',
                ['type' => 'object', 'properties' => [
                    'pending_id' => ['type' => 'integer'],
                ], 'required' => ['pending_id']],
                readOnly: false
            ),
            // ── Import staging ───────────────────────────────────────────────
            $this->def('list_import_staging',
                'List bank import staging rows pending review (approve or skip each one).',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('approve_import_staging',
                'Approve a bank import staging row — creates the transaction. Optionally set category, notes etc.',
                ['type' => 'object', 'properties' => [
                    'staging_id'        => ['type' => 'integer'],
                    'category_id'       => ['type' => 'integer'],
                    'subcategory_id'    => ['type' => 'integer'],
                    'payment_method_id' => ['type' => 'integer'],
                    'contact_id'        => ['type' => 'integer'],
                    'notes'             => ['type' => 'string'],
                ], 'required' => ['staging_id']],
                readOnly: false
            ),
            $this->def('skip_import_staging',
                'Skip a bank import staging row (marks as skipped, no transaction created).',
                ['type' => 'object', 'properties' => [
                    'staging_id' => ['type' => 'integer'],
                ], 'required' => ['staging_id']],
                readOnly: false
            ),
            // ── Reminders ────────────────────────────────────────────────────
            $this->def('list_reminders',
                'List upcoming reminders sorted by due date.',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('create_reminder',
                'Create a reminder.',
                ['type' => 'object', 'properties' => [
                    'title'    => ['type' => 'string'],
                    'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'notes'    => ['type' => 'string'],
                ], 'required' => ['title', 'due_date']],
                readOnly: false
            ),
            // ── Notes ────────────────────────────────────────────────────────
            $this->def('list_notes',
                'List all notes (title + snippet).',
                ['type' => 'object', 'properties' => [], 'required' => []],
                readOnly: true
            ),
            $this->def('get_note',
                'Get full content of a note by ID.',
                ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer'],
                ], 'required' => ['id']],
                readOnly: true
            ),
            $this->def('create_note',
                'Create a new note.',
                ['type' => 'object', 'properties' => [
                    'title'   => ['type' => 'string'],
                    'content' => ['type' => 'string'],
                ], 'required' => ['content']],
                readOnly: false
            ),
            $this->def('update_note',
                'Update an existing note.',
                ['type' => 'object', 'properties' => [
                    'id'      => ['type' => 'integer'],
                    'title'   => ['type' => 'string'],
                    'content' => ['type' => 'string'],
                ], 'required' => ['id']],
                readOnly: false
            ),
            $this->def('delete_note',
                'Delete a note by ID.',
                ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer'],
                ], 'required' => ['id']],
                readOnly: false, destructive: true
            ),
        ];
    }

    // ════════════════════════════════════════════════════════════════════════
    // CORE HANDLERS
    // ════════════════════════════════════════════════════════════════════════

    private function getOverview(): array
    {
        $today      = date('Y-m-d');
        $month      = (int) date('n');
        $year       = (int) date('Y');

        // Account balances
        $acctModel  = new Account($this->database);
        $summary    = $acctModel->getSummary();

        // Pending recurring
        $recModel   = new Recurring($this->database);
        $recModel->generatePending();
        $pendingRec = $recModel->countPending();

        // Upcoming reminders
        $reminders  = $this->pdo->query(
            "SELECT title, due_date FROM reminders ORDER BY due_date ASC LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Upcoming loan EMIs
        $emis = $this->pdo->query(
            "SELECT l.id, l.lender_name, le.emi_date, le.emi_amount
               FROM loan_emi_schedule le
               JOIN loans l ON l.id = le.loan_id
              WHERE le.status = 'pending' AND le.emi_date >= CURDATE()
              ORDER BY le.emi_date ASC LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Budget: categories over budget this month
        $budgetModel = new Budget($this->database);
        $budgets     = $budgetModel->getAllCategoriesWithBudgets($month, $year);
        $overBudget  = count(array_filter($budgets, fn($r) => (float)$r['budget_amount'] > 0 && (float)$r['spent'] >= (float)$r['budget_amount']));

        return [
            'today'             => $today,
            'account_summary'   => $summary,
            'pending_recurring' => $pendingRec,
            'categories_over_budget_this_month' => $overBudget,
            'upcoming_reminders'=> $reminders,
            'upcoming_loan_emis'=> $emis,
        ];
    }

    private function listAccounts(): array
    {
        $model = new Account($this->database);
        return $model->getAllWithBalances();
    }

    private function listCategories(): array
    {
        $model = new Category($this->database);
        return $model->getAllWithSubcategories();
    }

    private function listPaymentMethods(): array
    {
        $model = new PaymentMethod($this->database);
        return $model->getAll();
    }

    private function searchContacts(array $args): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        if ($q === '') throw new ToolError('q (search term) is required.');
        $model = new Contact($this->database);
        return $model->search($q, 20);
    }

    private function createContact(array $args): array
    {
        $name = trim((string) ($args['name'] ?? ''));
        if ($name === '') throw new ToolError('name is required.');
        $stmt = $this->pdo->prepare(
            "INSERT INTO contacts (name, mobile, email) VALUES (:name, :mobile, :email)"
        );
        $stmt->execute([
            ':name'   => $name,
            ':mobile' => trim((string) ($args['mobile'] ?? '')),
            ':email'  => trim((string) ($args['email']  ?? '')),
        ]);
        return ['id' => (int) $this->pdo->lastInsertId(), 'name' => $name];
    }

    // ════════════════════════════════════════════════════════════════════════
    // TRANSACTIONS
    // ════════════════════════════════════════════════════════════════════════

    private function listTransactions(array $args): array
    {
        $model   = new Transaction($this->database);
        $filters = [
            'start_date'       => $args['start_date']       ?? null,
            'end_date'         => $args['end_date']         ?? null,
            'account_id'       => !empty($args['account_id'])  ? (int) $args['account_id']  : null,
            'category_id'      => !empty($args['category_id']) ? (int) $args['category_id'] : null,
            'transaction_type' => $args['transaction_type'] ?? null,
            'limit'            => min((int) ($args['limit'] ?? 50), 200),
        ];
        // Remove null values to avoid filtering issues
        $filters = array_filter($filters, fn($v) => $v !== null);
        return $model->getFiltered($filters);
    }

    private function getTransaction(array $args): array
    {
        $id  = (int) ($args['id'] ?? 0);
        if ($id <= 0) throw new ToolError('id is required.');
        $model = new Transaction($this->database);
        $row   = $model->getById($id);
        if (!$row) throw new ToolError("Transaction {$id} not found. Use list_transactions to look up IDs.");
        return $row;
    }

    private function createTransaction(array $args): array
    {
        $type   = (string) ($args['transaction_type'] ?? 'expense');
        $amount = is_numeric($args['amount'] ?? null) ? (float) $args['amount'] : 0.0;
        $acctId = (int) ($args['account_id'] ?? 0);

        if (!in_array($type, ['income', 'expense', 'transfer'], true)) {
            throw new ToolError('transaction_type must be income, expense, or transfer.');
        }
        if ($amount <= 0) throw new ToolError('amount must be positive.');
        if ($acctId <= 0) throw new ToolError('account_id is required. Use list_accounts to get IDs.');

        $acctType = $this->resolveAccountType($acctId);
        $date     = $args['transaction_date'] ?? date('Y-m-d');

        $input = [
            'transaction_date'  => $date,
            'transaction_type'  => $type,
            'amount'            => $amount,
            'account_type'      => $acctType,
            'account_id'        => $acctId,
            'category_id'       => !empty($args['category_id'])       ? (int) $args['category_id']       : null,
            'subcategory_id'    => !empty($args['subcategory_id'])     ? (int) $args['subcategory_id']    : null,
            'payment_method_id' => !empty($args['payment_method_id']) ? (int) $args['payment_method_id'] : null,
            'contact_id'        => !empty($args['contact_id'])        ? (int) $args['contact_id']        : null,
            'notes'             => trim((string) ($args['notes'] ?? '')),
        ];

        if ($type === 'transfer') {
            $toId = (int) ($args['to_account_id'] ?? 0);
            if ($toId <= 0) throw new ToolError('to_account_id is required for transfers.');
            $toType = $this->resolveAccountType($toId);
            $model  = new Transaction($this->database);
            // Expense leg
            $expenseId = $model->create(array_merge($input, ['notes' => $input['notes'] ?: 'Transfer to ' . $this->accountDisplayName($toId)]));
            // Income leg
            $model->create([
                'transaction_date'  => $date,
                'transaction_type'  => 'income',
                'amount'            => $amount,
                'account_type'      => $toType,
                'account_id'        => $toId,
                'category_id'       => null,
                'subcategory_id'    => null,
                'payment_method_id' => null,
                'contact_id'        => null,
                'notes'             => $input['notes'] ?: 'Transfer from ' . $this->accountDisplayName($acctId),
                'reference_type'    => 'transfer',
                'reference_id'      => $expenseId,
            ]);
            return ['created' => true, 'expense_leg_id' => $expenseId, 'message' => 'Transfer created.'];
        }

        $model = new Transaction($this->database);
        $id    = $model->create($input);
        if ($id <= 0) throw new ToolError('Failed to create transaction.');
        return ['created' => true, 'id' => $id];
    }

    private function deleteTransaction(array $args): array
    {
        $id  = (int) ($args['id'] ?? 0);
        if ($id <= 0) throw new ToolError('id is required.');
        $model = new Transaction($this->database);
        $ok    = $model->delete($id);
        if (!$ok) throw new ToolError("Transaction {$id} not found.");
        return ['deleted' => true, 'id' => $id];
    }

    // ════════════════════════════════════════════════════════════════════════
    // CREDIT CARDS
    // ════════════════════════════════════════════════════════════════════════

    private function listCreditCards(): array
    {
        $model = new CreditCard($this->database);
        return $model->getAll();
    }

    // ════════════════════════════════════════════════════════════════════════
    // BUDGET
    // ════════════════════════════════════════════════════════════════════════

    private function listBudgets(array $args): array
    {
        $month = (int) ($args['month'] ?? date('n'));
        $year  = (int) ($args['year']  ?? date('Y'));
        $model = new Budget($this->database);
        $rows  = $model->getAllCategoriesWithBudgets($month, $year);
        return array_filter($rows, fn($r) => (float) $r['budget_amount'] > 0);
    }

    // ════════════════════════════════════════════════════════════════════════
    // LOANS
    // ════════════════════════════════════════════════════════════════════════

    private function listLoans(): array
    {
        $model = new Loan($this->database);
        return $model->getAll();
    }

    private function createLoan(array $args): array
    {
        $acctId = (int) ($args['account_id'] ?? 0);
        if ($acctId <= 0) throw new ToolError('account_id is required.');
        $acctType = $this->resolveAccountType($acctId);
        $model    = new Loan($this->database);
        $id = $model->create([
            'loan_name'            => $args['lender_name'] ?? 'Untitled Loan',
            'loan_type'            => 'personal',
            'principal_amount'     => $args['principal'] ?? 0,
            'interest_rate'        => $args['interest_rate'] ?? 0,
            'tenure_months'        => $args['tenure_months'] ?? 12,
            'start_date'           => $args['disbursement_date'] ?? date('Y-m-d'),
            'disbursement_account' => $acctType . ':' . $acctId,
        ]);
        if (!$id) throw new ToolError('Failed to create loan.');
        return ['created' => true, 'loan_id' => $id];
    }

    private function markLoanEmiPaid(array $args): array
    {
        $loanId = (int) ($args['loan_id'] ?? 0);
        if ($loanId <= 0) throw new ToolError('loan_id is required.');
        $model  = new Loan($this->database);
        $date   = $args['paid_date'] ?? date('Y-m-d');

        $loan = $model->getById($loanId);
        if (!$loan) throw new ToolError("Loan {$loanId} not found.");

        // Find the next pending EMI
        $emi = $this->pdo->prepare(
            "SELECT id FROM loan_emi_schedule WHERE loan_id = :lid AND status = 'pending' ORDER BY emi_date ASC LIMIT 1"
        );
        $emi->execute([':lid' => $loanId]);
        $row = $emi->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new ToolError("No pending EMI found for loan {$loanId}.");

        $acctId   = !empty($args['account_id']) ? (int) $args['account_id'] : (int) ($loan['account_id'] ?? 0);
        if ($acctId <= 0) throw new ToolError('account_id is required (no default on this loan).');
        $acctType = $this->resolveAccountType($acctId);

        $ok = $model->markEmiPaid([
            'emi_id'          => $row['id'],
            'loan_id'         => $loanId,
            'payment_date'    => $date,
            'payment_account' => $acctType . ':' . $acctId,
        ]);
        if (!$ok) throw new ToolError('Failed to mark EMI paid. Check account type is savings/current/cash/wallet/other.');
        return ['marked_paid' => true, 'emi_id' => (int) $row['id']];
    }

    // ════════════════════════════════════════════════════════════════════════
    // LENDING
    // ════════════════════════════════════════════════════════════════════════

    private function listLending(): array
    {
        $model = new Lending($this->database);
        return $model->getAll();
    }

    private function createLending(array $args): array
    {
        $contactId = (int) ($args['contact_id'] ?? 0);
        $acctId    = (int) ($args['account_id'] ?? 0);
        if ($contactId <= 0) throw new ToolError('contact_id is required. Use search_contacts to find IDs.');
        if ($acctId <= 0)    throw new ToolError('account_id is required.');
        $acctType = $this->resolveAccountType($acctId);
        $model    = new Lending($this->database);
        $ok = $model->create([
            'contact_id'       => $contactId,
            'principal_amount' => $args['amount'] ?? 0,
            'interest_rate'    => $args['interest_rate'] ?? 0,
            'lending_date'     => $args['lent_date'] ?? date('Y-m-d'),
            'due_date'         => $args['due_date'] ?? null,
            'funding_account'  => $acctType . ':' . $acctId,
            'notes'            => $args['notes'] ?? '',
        ]);
        if (!$ok) throw new ToolError('Failed to create lending record. Check contact_id and amount.');
        return ['created' => true];
    }

    private function recordLendingRepayment(array $args): array
    {
        $lendingId = (int) ($args['lending_id'] ?? 0);
        $acctId    = (int) ($args['account_id'] ?? 0);
        if ($lendingId <= 0) throw new ToolError('lending_id is required.');
        if ($acctId <= 0)    throw new ToolError('account_id is required.');
        $acctType = $this->resolveAccountType($acctId);
        $model    = new Lending($this->database);
        $ok = $model->recordRepayment([
            'lending_record_id' => $lendingId,
            'repayment_amount'  => $args['amount'] ?? 0,
            'repayment_date'    => $args['repayment_date'] ?? date('Y-m-d'),
            'deposit_account'   => $acctType . ':' . $acctId,
            'notes'             => $args['notes'] ?? '',
        ]);
        if (!$ok) throw new ToolError("Lending record {$lendingId} not found or already closed.");
        return ['recorded' => true];
    }

    // ════════════════════════════════════════════════════════════════════════
    // BORROWING
    // ════════════════════════════════════════════════════════════════════════

    private function listBorrowing(): array
    {
        $model = new Borrowing($this->database);
        return $model->getAll();
    }

    private function createBorrowing(array $args): array
    {
        $contactId = (int) ($args['contact_id'] ?? 0);
        $acctId    = (int) ($args['account_id'] ?? 0);
        if ($contactId <= 0) throw new ToolError('contact_id is required.');
        if ($acctId <= 0)    throw new ToolError('account_id is required.');
        $acctType = $this->resolveAccountType($acctId);
        $model    = new Borrowing($this->database);
        $ok = $model->create([
            'contact_id'       => $contactId,
            'principal_amount' => $args['amount'] ?? 0,
            'interest_rate'    => $args['interest_rate'] ?? 0,
            'borrowed_date'    => $args['borrowed_date'] ?? date('Y-m-d'),
            'due_date'         => $args['due_date'] ?? null,
            'deposit_account'  => $acctType . ':' . $acctId,
            'notes'            => $args['notes'] ?? '',
        ]);
        if (!$ok) throw new ToolError('Failed to create borrowing record. Check contact_id and amount.');
        return ['created' => true];
    }

    private function recordBorrowingRepayment(array $args): array
    {
        $borrowingId = (int) ($args['borrowing_id'] ?? 0);
        $acctId      = (int) ($args['account_id']   ?? 0);
        if ($borrowingId <= 0) throw new ToolError('borrowing_id is required.');
        if ($acctId <= 0)      throw new ToolError('account_id is required.');
        $acctType = $this->resolveAccountType($acctId);
        $model    = new Borrowing($this->database);
        $ok = $model->recordRepayment([
            'borrowing_record_id' => $borrowingId,
            'repayment_amount'    => $args['amount'] ?? 0,
            'repayment_date'      => $args['repayment_date'] ?? date('Y-m-d'),
            'payment_account'     => $acctType . ':' . $acctId,
            'notes'               => $args['notes'] ?? '',
        ]);
        if (!$ok) throw new ToolError("Borrowing record {$borrowingId} not found.");
        return ['recorded' => true];
    }

    // ════════════════════════════════════════════════════════════════════════
    // RENTAL
    // ════════════════════════════════════════════════════════════════════════

    private function listRentalProperties(): array
    {
        $model = new Rental($this->database);
        return $model->getProperties();
    }

    private function listRentalContracts(): array
    {
        $model = new Rental($this->database);
        return $model->getContracts();
    }

    private function recordRentalPayment(array $args): array
    {
        $contractId = (int) ($args['contract_id'] ?? 0);
        $acctId     = (int) ($args['account_id']  ?? 0);
        if ($contractId <= 0) throw new ToolError('contract_id is required. Use list_rental_contracts to get IDs.');
        if ($acctId <= 0)     throw new ToolError('account_id is required.');
        $acctType = $this->resolveAccountType($acctId);

        $rentMonth = null;
        if (!empty($args['period_month']) && !empty($args['period_year'])) {
            $rentMonth = sprintf('%04d-%02d-01', (int) $args['period_year'], (int) $args['period_month']);
        }

        $model = new Rental($this->database);
        $ok    = $model->recordPayment([
            'contract_id'     => $contractId,
            'paid_amount'     => $args['amount'] ?? 0,
            'payment_status'  => 'paid',
            'due_date'        => $args['payment_date'] ?? date('Y-m-d'),
            'rent_month'      => $rentMonth ?? date('Y-m-01'),
            'deposit_account' => $acctType . ':' . $acctId,
            'notes'           => $args['notes'] ?? '',
        ]);
        if (!$ok) throw new ToolError('Failed to record rental payment.');
        return ['recorded' => true];
    }

    // ════════════════════════════════════════════════════════════════════════
    // RENTED HOME
    // ════════════════════════════════════════════════════════════════════════

    private function listRentedHomes(): array
    {
        $model = new RentedHome($this->database);
        return $model->getAll();
    }

    private function addRentedHomeExpense(array $args): array
    {
        $homeId   = (int) ($args['rented_home_id'] ?? 0);
        $acctId   = (int) ($args['account_id']     ?? 0);
        if ($homeId <= 0) throw new ToolError('rented_home_id is required. Use list_rented_homes to get IDs.');
        if ($acctId <= 0) throw new ToolError('account_id is required.');
        $acctType = $this->resolveAccountType($acctId);

        $periodMonth = null;
        if (!empty($args['period_month']) && !empty($args['period_year'])) {
            $periodMonth = sprintf('%04d-%02d', (int) $args['period_year'], (int) $args['period_month']);
        }

        $model = new RentedHome($this->database);
        $ok    = $model->recordExpense([
            'home_id'        => $homeId,
            'expense_type'   => $args['expense_type'] ?? 'other',
            'amount'         => $args['amount'] ?? 0,
            'expense_date'   => $args['expense_date'] ?? date('Y-m-d'),
            'account_token'  => $acctType . ':' . $acctId,
            'period_month'   => $periodMonth,
            'notes'          => $args['notes'] ?? '',
        ]);
        if (!$ok) throw new ToolError('Failed to record expense. Check rented_home_id and amount.');
        return ['created' => true];
    }

    // ════════════════════════════════════════════════════════════════════════
    // INVESTMENTS
    // ════════════════════════════════════════════════════════════════════════

    private function listInvestments(): array
    {
        $model = new Investment($this->database);
        return ['investments' => $model->getAll(), 'summary' => $model->getSummary()];
    }

    private function createInvestmentTransaction(array $args): array
    {
        $invId  = (int) ($args['investment_id'] ?? 0);
        $acctId = (int) ($args['account_id']    ?? 0);
        if ($invId  <= 0) throw new ToolError('investment_id is required. Use list_investments to get IDs.');
        if ($acctId <= 0) throw new ToolError('account_id is required.');
        $acctType = $this->resolveAccountType($acctId);
        $model    = new Investment($this->database);
        $model->createTransaction(array_merge($args, ['account_type' => $acctType]));
        return ['created' => true];
    }

    // ════════════════════════════════════════════════════════════════════════
    // RECURRING
    // ════════════════════════════════════════════════════════════════════════

    private function listRecurringTemplates(): array
    {
        $model = new Recurring($this->database);
        return $model->getAll();
    }

    private function listRecurringPending(): array
    {
        $model = new Recurring($this->database);
        $model->generatePending();
        return $model->getPendingItems();
    }

    private function approveRecurringPending(array $args): array
    {
        $pendingId = (int) ($args['pending_id'] ?? 0);
        if ($pendingId <= 0) throw new ToolError('pending_id is required. Use list_recurring_pending to get IDs.');
        $model = new Recurring($this->database);
        $txId  = $model->approvePending($pendingId, $args);
        return ['approved' => true, 'transaction_id' => $txId];
    }

    private function skipRecurringPending(array $args): array
    {
        $pendingId = (int) ($args['pending_id'] ?? 0);
        if ($pendingId <= 0) throw new ToolError('pending_id is required.');
        $model = new Recurring($this->database);
        $model->skipPending($pendingId);
        return ['skipped' => true];
    }

    // ════════════════════════════════════════════════════════════════════════
    // IMPORT STAGING
    // ════════════════════════════════════════════════════════════════════════

    private function listImportStaging(): array
    {
        $model = new Import($this->database);
        return $model->getPendingRows();
    }

    private function approveImportStaging(array $args): array
    {
        $stagingId = (int) ($args['staging_id'] ?? 0);
        if ($stagingId <= 0) throw new ToolError('staging_id is required. Use list_import_staging to get IDs.');
        $model = new Import($this->database);
        $txId  = $model->approveStagingRow($stagingId, $args);
        return ['approved' => true, 'transaction_id' => $txId];
    }

    private function skipImportStaging(array $args): array
    {
        $stagingId = (int) ($args['staging_id'] ?? 0);
        if ($stagingId <= 0) throw new ToolError('staging_id is required.');
        $model = new Import($this->database);
        $model->skipStagingRow($stagingId);
        return ['skipped' => true];
    }

    // ════════════════════════════════════════════════════════════════════════
    // REMINDERS
    // ════════════════════════════════════════════════════════════════════════

    private function listReminders(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, title, due_date, notes FROM reminders ORDER BY due_date ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function createReminder(array $args): array
    {
        $title = trim((string) ($args['title']    ?? ''));
        $date  = trim((string) ($args['due_date'] ?? ''));
        if ($title === '') throw new ToolError('title is required.');
        if ($date  === '') throw new ToolError('due_date (YYYY-MM-DD) is required.');
        $stmt = $this->pdo->prepare(
            "INSERT INTO reminders (title, due_date, notes) VALUES (:title, :date, :notes)"
        );
        $stmt->execute([':title' => $title, ':date' => $date, ':notes' => trim((string) ($args['notes'] ?? ''))]);
        return ['created' => true, 'id' => (int) $this->pdo->lastInsertId()];
    }

    // ════════════════════════════════════════════════════════════════════════
    // NOTES
    // ════════════════════════════════════════════════════════════════════════

    private function listNotes(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, title, LEFT(content,120) AS snippet, updated_at FROM notes ORDER BY updated_at DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getNote(array $args): array
    {
        $id   = (int) ($args['id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT * FROM notes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new ToolError("Note {$id} not found. Use list_notes to look up IDs.");
        return $row;
    }

    private function createNote(array $args): array
    {
        $content = trim((string) ($args['content'] ?? ''));
        if ($content === '') throw new ToolError('content is required.');
        $stmt = $this->pdo->prepare(
            "INSERT INTO notes (title, content) VALUES (:title, :content)"
        );
        $stmt->execute([':title' => trim((string) ($args['title'] ?? '')), ':content' => $content]);
        return ['created' => true, 'id' => (int) $this->pdo->lastInsertId()];
    }

    private function updateNote(array $args): array
    {
        $id  = (int) ($args['id'] ?? 0);
        if ($id <= 0) throw new ToolError('id is required.');
        $stmt = $this->pdo->prepare("SELECT id, title, content FROM notes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new ToolError("Note {$id} not found.");
        $title   = array_key_exists('title',   $args) ? trim((string) $args['title'])   : $row['title'];
        $content = array_key_exists('content', $args) ? trim((string) $args['content']) : $row['content'];
        $this->pdo->prepare("UPDATE notes SET title = :title, content = :content, updated_at = NOW() WHERE id = :id")
                  ->execute([':title' => $title, ':content' => $content, ':id' => $id]);
        return ['updated' => true];
    }

    private function deleteNote(array $args): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) throw new ToolError('id is required.');
        $this->pdo->prepare("DELETE FROM notes WHERE id = :id")->execute([':id' => $id]);
        return ['deleted' => true, 'id' => $id];
    }

    // ════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ════════════════════════════════════════════════════════════════════════

    private function resolveAccountType(int $id): string
    {
        $stmt = $this->pdo->prepare("SELECT account_type FROM accounts WHERE id = :id AND is_active = 1 LIMIT 1");
        $stmt->execute([':id' => $id]);
        $type = $stmt->fetchColumn();
        if ($type === false) {
            throw new ToolError("Account {$id} not found or inactive. Use list_accounts to look up IDs.");
        }
        return (string) $type;
    }

    private function accountDisplayName(int $id): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.bank_name, a.account_name, cc.bank_name AS cc_bank, cc.card_name
               FROM accounts a
               LEFT JOIN credit_cards cc ON cc.account_id = a.id
              WHERE a.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return 'Account #' . $id;
        $bank = $row['cc_bank'] ?: $row['bank_name'];
        $name = $row['card_name'] ?: $row['account_name'];
        return trim(($bank ? $bank . ' — ' : '') . $name);
    }

    private function def(
        string $name,
        string $description,
        array  $inputSchema,
        bool   $readOnly    = false,
        bool   $destructive = false
    ): array {
        return [
            'name'        => $name,
            'description' => $description,
            'inputSchema' => $inputSchema,
            'annotations' => [
                'title'           => str_replace('_', ' ', ucfirst($name)),
                'readOnlyHint'    => $readOnly,
                'destructiveHint' => $destructive,
                'idempotentHint'  => $readOnly || in_array($name, ['skip_recurring_pending', 'skip_import_staging'], true),
                'openWorldHint'   => false,
            ],
        ];
    }
}
