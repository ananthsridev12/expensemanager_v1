<?php

namespace Controllers;

use Models\Account;
use Models\Category;
use Models\Transaction;

class AllTransactionsController extends BaseController
{
    private Transaction $transactionModel;
    private Account $accountModel;
    private Category $categoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->transactionModel = new Transaction($this->database);
        $this->accountModel = new Account($this->database);
        $this->categoryModel = new Category($this->database);
    }

    public function index(): string
    {
        if (($_GET['action'] ?? '') === 'export') {
            $filters = $this->collectFilters();
            $rows = $this->transactionModel->getFiltered($filters);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="transactions.csv"');
            echo "Date,Type,Account,Category,Subcategory,Amount,Payment Method,Contact,Purchased From,Notes\n";
            foreach ($rows as $row) {
                $line = [
                    $row['transaction_date'],
                    ucfirst($row['transaction_type']),
                    $row['account_display'],
                    $row['category_name'] ?? 'Uncategorized',
                    $row['subcategory_name'] ?? '',
                    $row['amount'],
                    $row['payment_method_name'] ?? '',
                    $row['contact_name'] ?? '',
                    $row['purchase_source_name'] ?? '',
                    str_replace(['"', "\n"], ['""', ' '], $row['notes'] ?? ''),
                ];
                echo '"' . implode('","', $line) . '"' . "\n";
            }
            exit;
        }

        $filters = $this->collectFilters();
        $accounts = $this->accountModel->getList();
        $categories = $this->categoryModel->getAllWithSubcategories();
        $transactions = $this->transactionModel->getFiltered($filters);
        $totalsByType = $this->transactionModel->getTotalsByType();

        return $this->render('all_transactions/index.php', [
            'accounts' => $accounts,
            'categories' => $categories,
            'filters' => $filters,
            'transactions' => $transactions,
            'totalsByType' => $totalsByType,
        ]);
    }

    private function collectFilters(): array
    {
        $start = isset($_GET['start_date']) ? trim((string) $_GET['start_date']) : '';
        $end = isset($_GET['end_date']) ? trim((string) $_GET['end_date']) : '';

        return [
            'account_id' => !empty($_GET['account_id']) ? (int) $_GET['account_id'] : null,
            'category_id' => !empty($_GET['category_id']) ? (int) $_GET['category_id'] : null,
            'subcategory_id' => !empty($_GET['subcategory_id']) ? (int) $_GET['subcategory_id'] : null,
            'start_date' => $start !== '' ? $start : null,
            'end_date' => $end !== '' ? $end : null,
        ];
    }
}
