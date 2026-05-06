<?php

namespace Controllers;

use Models\Account;
use Models\Contact;
use Models\RentedHome;

class RentedHomeController extends BaseController
{
    private RentedHome $model;
    private Account    $accountModel;
    private Contact    $contactModel;

    public function __construct()
    {
        parent::__construct();
        $this->model        = new RentedHome($this->database);
        $this->accountModel = new Account($this->database);
        $this->contactModel = new Contact($this->database);
    }

    public function index(): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = $_POST['form'] ?? '';

            if ($form === 'deposit_refund') {
                header('Content-Type: application/json');
                $ok = $this->model->recordDepositRefund(array_merge($_POST, [
                    'account_token' => $_POST['account_token'] ?? '',
                ]));
                echo json_encode(['ok' => $ok]);
                exit;
            }

            if ($form === 'rented_home') {
                $this->model->create($_POST);
            } elseif ($form === 'rented_home_update') {
                $this->model->update($_POST);
            } elseif ($form === 'rented_home_expense') {
                $this->model->recordExpense(array_merge($_POST, [
                    'account_token' => $_POST['account_token'] ?? '',
                ]));
            }

            $redirectTo = trim((string) ($_POST['redirect_to'] ?? ''));
            if ($redirectTo !== '' && strpos($redirectTo, '/') === 0) {
                header('Location: ' . $redirectTo);
            } else {
                header('Location: ?module=rented_home');
            }
            exit;
        }

        $editHome = null;
        if (!empty($_GET['edit'])) {
            $editHome = $this->model->getById((int) $_GET['edit']);
        }

        $homes          = $this->model->getAll();
        $activeHomes    = $this->model->getActive();
        $recentExpenses = $this->model->getRecentExpenses(20);
        $accounts       = $this->accountModel->getList();
        $contacts       = $this->contactModel->getAll();
        $summary        = $this->model->getSummary();

        return $this->render('rented_home/index.php', [
            'homes'          => $homes,
            'activeHomes'    => $activeHomes,
            'recentExpenses' => $recentExpenses,
            'accounts'       => $accounts,
            'contacts'       => $contacts,
            'summary'        => $summary,
            'editHome'       => $editHome,
        ]);
    }
}
