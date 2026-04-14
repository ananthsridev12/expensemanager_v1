<?php

namespace Controllers;

use Models\Account;
use Models\Analytics;
use Models\Category;
use Models\CreditCard;
use Models\Investment;
use Models\Loan;
use Models\Lending;
use Models\Reminder;
use Models\Rental;
use Models\Transaction;

class DashboardController extends BaseController
{
    private Account $accountModel;
    private Analytics $analyticsModel;
    private Category $categoryModel;
    private CreditCard $creditCardModel;
    private Investment $investmentModel;
    private Loan $loanModel;
    private Lending $lendingModel;
    private Reminder $reminderModel;
    private Rental $rentalModel;
    private Transaction $transactionModel;

    public function __construct()
    {
        parent::__construct();
        $this->accountModel = new Account($this->database);
        $this->analyticsModel = new Analytics($this->database);
        $this->categoryModel = new Category($this->database);
        $this->creditCardModel = new CreditCard($this->database);
        $this->investmentModel = new Investment($this->database);
        $this->loanModel = new Loan($this->database);
        $this->lendingModel = new Lending($this->database);
        $this->reminderModel = new Reminder($this->database);
        $this->rentalModel = new Rental($this->database);
        $this->transactionModel = new Transaction($this->database);
    }

    public function index(): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = $_POST['form'] ?? '';
            if ($form === 'transaction') {
                $this->handleTransaction($_POST);
            }

            header('Location: ?module=dashboard');
            exit;
        }

        $accountsSummary = $this->accountModel->getSummary();
        $loans = $this->loanModel->getAll();
        $accounts = $this->accountModel->getAllWithBalances();
        $creditCards = $this->creditCardModel->getAll();
        $creditCardStatements = $this->creditCardModel->getStatementSnapshots();
        $creditCardEmiPlans = $this->creditCardModel->getEmiPlans();
        $creditCardEmiSchedule = $this->creditCardModel->getUpcomingSchedule(30);
        $categoriesWithSubs = $this->categoryModel->getAllWithSubcategories();
        $totalsByType = $this->transactionModel->getTotalsByType();

        $summary = [
            'accounts' => $accountsSummary,
            'categories' => $this->categoryModel->count(),
            'transactions' => $this->transactionModel->countAll(),
            'reminders' => $this->reminderModel->count(),
            'loans' => [
                'count' => count($loans),
                'outstanding' => array_sum(array_column($loans, 'outstanding_principal')),
            ],
            'credit_cards' => $this->creditCardModel->getSummary(),
            'lending'   => $this->lendingModel->getSummary(),
            'borrowing' => (function() {
                try {
                    $m = new \Models\Borrowing($this->database);
                    return $m->getSummary();
                } catch (\Throwable $e) {
                    return ['count' => 0, 'outstanding' => 0];
                }
            })(),
            'investments' => $this->investmentModel->getSummary(),
            'rentals' => $this->rentalModel->getSummary(),
        ];

        $recentTransactions = $this->transactionModel->getRecent(5);
        $upcomingReminders = $this->reminderModel->getUpcoming(3);
        $upcomingEmis = $this->loanModel->getUpcomingEmis(5);
        $monthComparison = $this->analyticsModel->getThisMonthVsLastMonth();
        $sparkline = $this->analyticsModel->getMiniSparkline(6);
        try {
            $budgetModel  = new \Models\Budget($this->database);
            $budgetSummary = $budgetModel->getSummaryForMonth((int) date('n'), (int) date('Y'));
        } catch (\Throwable $e) {
            $budgetSummary = ['count' => 0, 'total_budgeted' => 0, 'total_spent' => 0, 'over_count' => 0, 'rows' => []];
        }

        return $this->render('dashboard.php', [
            'summary' => $summary,
            'recentTransactions' => $recentTransactions,
            'upcomingReminders' => $upcomingReminders,
            'upcomingEmis' => $upcomingEmis,
            'accounts' => $accounts,
            'creditCards' => $creditCards,
            'creditCardStatements' => $creditCardStatements,
            'creditCardEmiPlans' => $creditCardEmiPlans,
            'creditCardEmiSchedule' => $creditCardEmiSchedule,
            'categories' => $categoriesWithSubs,
            'totalsByType' => $totalsByType,
            'monthComparison' => $monthComparison,
            'sparkline' => $sparkline,
            'budgetSummary' => $budgetSummary,
        ]);
    }

    private function handleTransaction(array $input): void
    {
        $transactionType = $input['transaction_type'] ?? 'expense';
        $amount = is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : 0.0;
        $fromToken = $input['account_id'] ?? '';
        $toToken = $input['transfer_account_id'] ?? '';

        [$fromType, $fromId] = $this->parseAccountToken($fromToken);
        [$toType, $toId] = $this->parseAccountToken($toToken);

        if ($amount <= 0 || $fromId <= 0) {
            return;
        }

        if ($transactionType === 'transfer') {
            if ($toId <= 0 || ($fromType === $toType && $fromId === $toId)) {
                return;
            }

            $this->createLedgerMovement($fromType, $fromId, 'expense', $amount, $input, $toType, $toId, 'Transfer out');
            $this->createLedgerMovement($toType, $toId, 'income', $amount, $input, $fromType, $fromId, 'Transfer in');
            return;
        }

        $this->createLedgerMovement($fromType, $fromId, $transactionType, $amount, $input, null, null, $input['notes'] ?? null);
    }

    private function createLedgerMovement(
        string $accountType,
        int $accountId,
        string $transactionType,
        float $amount,
        array $input,
        ?string $refType,
        ?int $refId,
        ?string $fallbackNotes
    ): void {
        $effectiveRefType = $refType;
        $effectiveRefId = $refId;
        if ($accountType === 'credit_card') {
            $effectiveRefType = 'credit_card';
            $effectiveRefId = $accountId;
        }

        $this->transactionModel->create([
            'transaction_date' => $input['transaction_date'] ?? date('Y-m-d'),
            'account_type' => $accountType,
            'account_id' => $accountId,
            'transaction_type' => $transactionType,
            'category_id' => !empty($input['category_id']) ? (int) $input['category_id'] : null,
            'subcategory_id' => !empty($input['subcategory_id']) ? (int) $input['subcategory_id'] : null,
            'amount' => $amount,
            'reference_type' => $effectiveRefType,
            'reference_id' => $effectiveRefId,
            'notes' => $input['notes'] ?? $fallbackNotes,
        ]);

        if ($accountType === 'credit_card') {
            $this->creditCardModel->applyTransactionMovementByAccount($accountId, $transactionType, $amount);
        }
    }

    private function parseAccountToken(string $token): array
    {
        if (strpos($token, ':') === false) {
            return ['savings', (int) $token];
        }

        [$type, $id] = explode(':', $token, 2);
        $allowedTypes = ['savings', 'current', 'credit_card', 'cash', 'other'];
        $normalizedType = in_array($type, $allowedTypes, true) ? $type : 'savings';
        return [$normalizedType, (int) $id];
    }

}
