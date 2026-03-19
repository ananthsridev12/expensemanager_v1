<?php

namespace Controllers;

use Models\Account;
use Models\Investment;

class InvestmentController extends BaseController
{
    private Investment $investmentModel;
    private Account $accountModel;

    public function __construct()
    {
        parent::__construct();
        $this->investmentModel = new Investment($this->database);
        $this->accountModel = new Account($this->database);
    }

    public function index(): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['form'] ?? '') === 'investment') {
                $this->investmentModel->create($_POST);
            }

            if (($_POST['form'] ?? '') === 'investment_transaction') {
                $this->investmentModel->createTransaction($_POST);
            }

            if (($_POST['form'] ?? '') === 'investment_update') {
                $this->investmentModel->update($_POST);
            }

            header('Location: ?module=investments');
            exit;
        }

        $editInvestment = null;
        if (!empty($_GET['edit'])) {
            $editInvestment = $this->investmentModel->getById((int) $_GET['edit']);
        }

        $investments = $this->investmentModel->getAll();
        $transactions = $this->investmentModel->getRecentTransactions(10);
        $accounts = $this->accountModel->getList();
        $summary = $this->investmentModel->getSummary();

        return $this->render('investments/index.php', [
            'investments' => $investments,
            'transactions' => $transactions,
            'accounts' => $accounts,
            'summary' => $summary,
            'editInvestment' => $editInvestment,
        ]);
    }
}
