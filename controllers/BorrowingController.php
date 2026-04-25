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
                $flashMsg = $ok ? 'Repayment recorded.' : 'Failed to record repayment.';
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
                            $sent = $mailer->send($rec['email'], 'Payment made – ' . $amt . ' paid on ' . $date, $html);
                            $flashMsg .= $sent
                                ? ' Notification sent to ' . $rec['contact_name'] . '.'
                                : ' Email failed: ' . $mailer->getLastError();
                        } else {
                            $flashMsg .= ' (SMTP not configured — notification not sent.)';
                        }
                    } else {
                        $flashMsg .= ' (No email on file for contact.)';
                    }
                }
                $_SESSION['borrowing_flash'] = ['type' => $ok ? 'success' : 'error', 'msg' => $flashMsg];
                header('Location: ?module=borrowing');
                exit;
            } elseif ($form === 'borrowing_void_repayment') {
                $ok = $this->borrowingModel->deleteRepayment((int) ($_POST['repayment_id'] ?? 0));
                $_SESSION['borrowing_flash'] = ['type' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Repayment voided.' : 'Failed to void repayment.'];
                header('Location: ?module=borrowing');
                exit;
            } elseif ($form === 'borrowing_resend_email') {
                $rep = $this->borrowingModel->getRepaymentById((int) ($_POST['repayment_id'] ?? 0));
                if ($rep && !empty($rep['email'])) {
                    $mailer = $this->buildMailer();
                    if ($mailer) {
                        $amt  = 'Rs.' . number_format((float) $rep['amount'], 2, '.', ',');
                        $bal  = 'Rs.' . number_format((float) ($rep['outstanding_amount'] ?? 0), 2, '.', ',');
                        $date = date('d M Y', strtotime($rep['repayment_date']));
                        $html = $this->notificationEmail(
                            'Payment Made',
                            $rep['contact_name'],
                            'We have made a payment of ' . $amt . ' on ' . $date . ' towards our outstanding amount. Our remaining balance is ' . $bal . '.',
                            ['Amount paid' => $amt, 'Date' => $date, 'Remaining balance' => $bal]
                        );
                        $sent = $mailer->send($rep['email'], 'Payment made – ' . $amt . ' paid on ' . $date, $html);
                        $_SESSION['borrowing_flash'] = $sent
                            ? ['type' => 'success', 'msg' => 'Receipt sent to ' . $rep['contact_name'] . '.']
                            : ['type' => 'error',   'msg' => 'Send failed: ' . $mailer->getLastError()];
                    } else {
                        $_SESSION['borrowing_flash'] = ['type' => 'error', 'msg' => 'SMTP not configured.'];
                    }
                } else {
                    $_SESSION['borrowing_flash'] = ['type' => 'error', 'msg' => empty($rep) ? 'Repayment not found.' : 'No email on file for this contact.'];
                }
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
