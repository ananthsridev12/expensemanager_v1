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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (($_GET['action'] ?? '') === 'contact_search') {
            header('Content-Type: application/json');
            return json_encode($this->contactModel->search((string) ($_GET['q'] ?? ''), 20));
        }

        $flash = null;
        if (!empty($_SESSION['borrowing_flash'])) {
            $flash = $_SESSION['borrowing_flash'];
            unset($_SESSION['borrowing_flash']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = $_POST['form'] ?? '';

            if ($form === 'borrowing') {
                $this->borrowingModel->create($_POST);
            } elseif ($form === 'borrowing_repayment') {
                $ok = $this->borrowingModel->recordRepayment($_POST);
                if ($ok && ($_POST['send_email'] ?? '') === '1') {
                    $rec = $this->borrowingModel->getById((int) ($_POST['borrowing_record_id'] ?? 0));
                    if ($rec && !empty($rec['email'])) {
                        $mailer = $this->buildMailer();
                        if ($mailer) {
                            $amt  = 'Rs.' . number_format((float) ($_POST['repayment_amount'] ?? 0), 2, '.', ',');
                            $bal  = 'Rs.' . number_format((float) ($rec['outstanding_amount'] ?? 0), 2, '.', ',');
                            $date = date('d M Y');
                            $html = $this->notificationEmail(
                                'Payment Made',
                                $rec['contact_name'],
                                'We have made a payment of ' . $amt . ' on ' . $date . ' towards our outstanding amount. Our remaining balance is ' . $bal . '.',
                                ['Amount paid' => $amt, 'Date' => $date, 'Remaining balance' => $bal]
                            );
                            $mailer->send($rec['email'], 'Payment made – ' . $amt . ' paid on ' . $date, $html);
                        }
                    }
                }
                $_SESSION['borrowing_flash'] = ['type' => 'success', 'msg' => 'Repayment recorded.'];
                header('Location: ?module=borrowing');
                exit;
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
            'smtpReady'     => $this->smtpIsReady(),
            'flash'         => $flash,
        ]);
    }
}
