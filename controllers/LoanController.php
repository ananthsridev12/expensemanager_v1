<?php

namespace Controllers;

use Models\Account;
use Models\Loan;

class LoanController extends BaseController
{
    private Loan $loanModel;
    private Account $accountModel;

    public function __construct()
    {
        parent::__construct();
        $this->loanModel = new Loan($this->database);
        $this->accountModel = new Account($this->database);
    }

    public function index(): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'loan') {
            $this->loanModel->create($_POST);
            header('Location: ?module=loans');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'loan_existing') {
            $this->loanModel->createExisting($_POST);
            header('Location: ?module=loans');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'loan_update') {
            $this->loanModel->update($_POST);
            header('Location: ?module=loans');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'emi_pay') {
            $this->loanModel->markEmiPaid($_POST);
            header('Location: ?module=loans');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'loan_link') {
            $loanId     = (int) ($_POST['loan_id'] ?? 0);
            $lendingId  = (int) ($_POST['lending_record_id'] ?? 0);
            $this->loanModel->linkToLending($loanId, $lendingId ?: null);
            header('Location: ?module=loans');
            exit;
        }

        $editLoan = null;
        if (!empty($_GET['edit'])) {
            $editLoan = $this->loanModel->getById((int) $_GET['edit']);
        }

        $loans = $this->loanModel->getAll();
        $accounts = array_values(array_filter(
            $this->accountModel->getList(),
            static fn (array $account): bool => ($account['account_type'] ?? '') !== 'credit_card'
        ));
        $upcomingEmis  = $this->loanModel->getUpcomingEmis(8);
        $linkedPairs   = $this->loanModel->getLinkedPairs();
        $lendingOptions = $this->loanModel->getAllLendingOptions();
        $summary = [
            'count' => count($loans),
            'total_outstanding' => array_sum(array_column($loans, 'outstanding_principal')),
        ];

        return $this->render('loans/index.php', [
            'loans'          => $loans,
            'accounts'       => $accounts,
            'upcomingEmis'   => $upcomingEmis,
            'linkedPairs'    => $linkedPairs,
            'lendingOptions' => $lendingOptions,
            'summary'        => $summary,
            'editLoan'       => $editLoan,
        ]);
    }
}
