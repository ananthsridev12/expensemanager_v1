<?php

namespace Controllers;

use Models\Account;
use Models\Borrowing;
use Models\Contact;

class BorrowingController extends BaseController
{
    private Borrowing $borrowingModel;
    private Account   $accountModel;
    private Contact   $contactModel;

    public function __construct()
    {
        parent::__construct();
        $this->borrowingModel = new Borrowing($this->database);
        $this->accountModel   = new Account($this->database);
        $this->contactModel   = new Contact($this->database);
    }

    public function index(): string
    {
        if (($_GET['action'] ?? '') === 'contact_search') {
            header('Content-Type: application/json');
            return json_encode($this->contactModel->search((string) ($_GET['q'] ?? ''), 20));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = $_POST['form'] ?? '';

            if ($form === 'borrowing') {
                $this->borrowingModel->create($_POST);
            } elseif ($form === 'borrowing_repayment') {
                $this->borrowingModel->recordRepayment($_POST);
            } elseif ($form === 'borrowing_update') {
                $this->borrowingModel->update($_POST);
            }

            header('Location: ?module=borrowing');
            exit;
        }

        $editRecord = null;
        if (!empty($_GET['edit'])) {
            $editRecord = $this->borrowingModel->getById((int) $_GET['edit']);
        }

        $records       = $this->borrowingModel->getAll();
        $openRecords   = $this->borrowingModel->getOpenRecords();
        $allRepayments = $this->borrowingModel->getAllRepayments();
        $accounts      = $this->accountModel->getList();
        $summary       = $this->borrowingModel->getSummary();

        return $this->render('borrowing/index.php', [
            'records'       => $records,
            'openRecords'   => $openRecords,
            'allRepayments' => $allRepayments,
            'accounts'      => $accounts,
            'summary'       => $summary,
            'editRecord'    => $editRecord,
        ]);
    }
}
