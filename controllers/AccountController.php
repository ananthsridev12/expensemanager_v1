<?php

namespace Controllers;

use Models\Account;

class AccountController extends BaseController
{
    private Account $accountModel;

    public function __construct()
    {
        parent::__construct();
        $this->accountModel = new Account($this->database);
    }

    public function index(): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'account') {
            $this->accountModel->create($_POST);
            header('Location: ?module=accounts');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'account_update') {
            $this->accountModel->update($_POST);
            header('Location: ?module=accounts');
            exit;
        }

        $accounts = $this->accountModel->getAllWithBalances();
        $summary = $this->accountModel->getSummary();
        $accountTypes = $this->accountModel->getAccountTypes();
        $editAccount = null;
        if (!empty($_GET['edit'])) {
            $editAccount = $this->accountModel->getById((int) $_GET['edit']);
        }

        return $this->render('accounts/index.php', [
            'accounts' => $accounts,
            'summary' => $summary,
            'accountTypes' => $accountTypes,
            'editAccount' => $editAccount,
        ]);
    }
}
