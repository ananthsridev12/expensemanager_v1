<?php

namespace Controllers;

use Models\Account;
use Models\Contact;
use Models\Lending;

class LendingController extends BaseController
{
    private Lending $lendingModel;
    private Account $accountModel;
    private Contact $contactModel;

    public function __construct()
    {
        parent::__construct();
        $this->lendingModel = new Lending($this->database);
        $this->accountModel = new Account($this->database);
        $this->contactModel = new Contact($this->database);
    }

    public function index(): string
    {
        if (($_GET['action'] ?? '') === 'contact_search') {
            header('Content-Type: application/json');
            return json_encode(
                $this->contactModel->search((string) ($_GET['q'] ?? ''), 20)
            );
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'lending') {
            $this->lendingModel->create($_POST);
            header('Location: ?module=lending');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'repayment') {
            $this->lendingModel->recordRepayment($_POST);
            header('Location: ?module=lending');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'lending_update') {
            $this->lendingModel->update($_POST);
            header('Location: ?module=lending');
            exit;
        }

        $editRecord = null;
        if (!empty($_GET['edit'])) {
            $editRecord = $this->lendingModel->getById((int) $_GET['edit']);
        }

        $records = $this->lendingModel->getAll();
        $openRecords = $this->lendingModel->getOpenRecords();
        $allRepayments = $this->lendingModel->getAllRepayments();
        $accounts = array_values(array_filter(
            $this->accountModel->getList(),
            static fn (array $account): bool => ($account['account_type'] ?? '') !== 'credit_card'
        ));
        $summary = $this->lendingModel->getSummary();

        return $this->render('lending/index.php', [
            'records' => $records,
            'openRecords' => $openRecords,
            'allRepayments' => $allRepayments,
            'accounts' => $accounts,
            'summary' => $summary,
            'editRecord' => $editRecord,
        ]);
    }
}
