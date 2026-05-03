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
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'refund') {
            header('Content-Type: application/json');
            $refundOfId = (int) ($_POST['refund_of_id'] ?? 0);
            $amount     = (float) ($_POST['amount'] ?? 0);

            if ($refundOfId <= 0 || $amount <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Invalid input.']);
                exit;
            }

            $original = $this->transactionModel->getById($refundOfId);

            if (!$original || $original['transaction_type'] !== 'expense') {
                echo json_encode(['ok' => false, 'error' => 'Original expense not found.']);
                exit;
            }

            $newId = $this->transactionModel->create([
                'transaction_date' => date('Y-m-d'),
                'account_type'     => $original['account_type'],
                'account_id'       => $original['account_id'],
                'transaction_type' => 'income',
                'category_id'      => $original['category_id'],
                'subcategory_id'   => $original['subcategory_id'] ?? null,
                'amount'           => $amount,
                'reference_type'   => 'refund',
                'reference_id'     => $refundOfId,
                'notes'            => 'Refund of #' . $refundOfId,
            ]);

            echo json_encode(['ok' => $newId > 0]);
            exit;
        }

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
