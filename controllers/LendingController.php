<?php

namespace Controllers;

use Models\Account;
use Models\Contact;
use Models\Lending;
use Models\Loan;

class LendingController extends BaseController
{
    private Lending $lendingModel;
    private Account $accountModel;
    private Contact $contactModel;
    private Loan $loanModel;

    public function __construct()
    {
        parent::__construct();
        $this->lendingModel = new Lending($this->database);
        $this->accountModel = new Account($this->database);
        $this->contactModel = new Contact($this->database);
        $this->loanModel    = new Loan($this->database);
    }

    public function index(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (($_GET['action'] ?? '') === 'contact_search') {
            header('Content-Type: application/json');
            return json_encode(
                $this->contactModel->search((string) ($_GET['q'] ?? ''), 20)
            );
        }

        $flash = null;
        if (!empty($_SESSION['lending_flash'])) {
            $flash = $_SESSION['lending_flash'];
            unset($_SESSION['lending_flash']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = $_POST['form'] ?? '';

            if ($form === 'lending') {
                $this->lendingModel->create($_POST);
                header('Location: ?module=lending');
                exit;
            }

            if ($form === 'repayment') {
                $ok = $this->lendingModel->recordRepayment($_POST);
                if ($ok && ($_POST['send_email'] ?? '') === '1') {
                    $rec = $this->lendingModel->getById((int) ($_POST['lending_record_id'] ?? 0));
                    if ($rec && !empty($rec['email'])) {
                        $mailer = $this->buildMailer();
                        if ($mailer) {
                            $amt  = 'Rs.' . number_format((float) ($_POST['repayment_amount'] ?? 0), 2, '.', ',');
                            $bal  = 'Rs.' . number_format((float) ($rec['outstanding_amount'] ?? 0), 2, '.', ',');
                            $date = date('d M Y');
                            $html = $this->notificationEmail(
                                'Payment Received',
                                $rec['contact_name'],
                                'We have received your payment of ' . $amt . ' on ' . $date . '. Thank you.',
                                ['Amount received' => $amt, 'Date' => $date, 'Remaining balance' => $bal]
                            );
                            $mailer->send($rec['email'], 'Payment received – ' . $amt, $html);
                        }
                    }
                }
                $_SESSION['lending_flash'] = ['type' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Repayment recorded.' : 'Failed to record repayment.'];
                header('Location: ?module=lending');
                exit;
            }

            if ($form === 'lending_reminder') {
                $rec = $this->lendingModel->getById((int) ($_POST['lending_record_id'] ?? 0));
                if ($rec && !empty($rec['email'])) {
                    $mailer = $this->buildMailer();
                    if ($mailer) {
                        $bal  = 'Rs.' . number_format((float) ($rec['outstanding_amount'] ?? 0), 2, '.', ',');
                        $due  = !empty($rec['due_date']) ? date('d M Y', strtotime($rec['due_date'])) : null;
                        $body = 'This is a friendly reminder that an outstanding amount of ' . $bal . ' is pending'
                            . ($due ? ', due by ' . $due : '') . '. Please arrange repayment at your earliest convenience.';
                        $details = ['Outstanding amount' => $bal];
                        if ($due) {
                            $details['Due date'] = $due;
                        }
                        $html = $this->notificationEmail('Payment Reminder', $rec['contact_name'], $body, $details);
                        $sent = $mailer->send($rec['email'], 'Payment reminder – ' . $bal . ' outstanding', $html);
                        $_SESSION['lending_flash'] = $sent
                            ? ['type' => 'success', 'msg' => 'Reminder sent to ' . $rec['contact_name'] . '.']
                            : ['type' => 'error',   'msg' => 'Send failed: ' . $mailer->getLastError()];
                    } else {
                        $_SESSION['lending_flash'] = ['type' => 'error', 'msg' => 'SMTP not configured.'];
                    }
                } else {
                    $_SESSION['lending_flash'] = ['type' => 'error', 'msg' => empty($rec) ? 'Record not found.' : 'No email address for this contact.'];
                }
                header('Location: ?module=lending');
                exit;
            }

            if ($form === 'lending_void_repayment') {
                $ok = $this->lendingModel->deleteRepayment((int) ($_POST['repayment_id'] ?? 0));
                $_SESSION['lending_flash'] = ['type' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Repayment voided.' : 'Failed to void repayment.'];
                header('Location: ?module=lending');
                exit;
            }

            if ($form === 'lending_resend_email') {
                $rep = $this->lendingModel->getRepaymentById((int) ($_POST['repayment_id'] ?? 0));
                if ($rep && !empty($rep['email'])) {
                    $mailer = $this->buildMailer();
                    if ($mailer) {
                        $amt  = 'Rs.' . number_format((float) $rep['amount'], 2, '.', ',');
                        $bal  = 'Rs.' . number_format((float) ($rep['outstanding_amount'] ?? 0), 2, '.', ',');
                        $date = date('d M Y', strtotime($rep['repayment_date']));
                        $html = $this->notificationEmail(
                            'Payment Received',
                            $rep['contact_name'],
                            'We have received your payment of ' . $amt . ' on ' . $date . '. Thank you.',
                            ['Amount received' => $amt, 'Date' => $date, 'Remaining balance' => $bal]
                        );
                        $sent = $mailer->send($rep['email'], 'Payment received – ' . $amt, $html);
                        $_SESSION['lending_flash'] = $sent
                            ? ['type' => 'success', 'msg' => 'Receipt sent to ' . $rep['contact_name'] . '.']
                            : ['type' => 'error',   'msg' => 'Send failed: ' . $mailer->getLastError()];
                    } else {
                        $_SESSION['lending_flash'] = ['type' => 'error', 'msg' => 'SMTP not configured.'];
                    }
                } else {
                    $_SESSION['lending_flash'] = ['type' => 'error', 'msg' => empty($rep) ? 'Repayment not found.' : 'No email on file for this contact.'];
                }
                header('Location: ?module=lending');
                exit;
            }

            if ($form === 'lending_update') {
                $this->lendingModel->update($_POST);
                header('Location: ?module=lending');
                exit;
            }

            if ($form === 'lending_link_loan') {
                $loanId    = (int) ($_POST['loan_id'] ?? 0);
                $lendingId = (int) ($_POST['lending_record_id'] ?? 0);
                if ($loanId > 0) {
                    $this->loanModel->linkToLending($loanId, $lendingId ?: null);
                }
                header('Location: ?module=lending');
                exit;
            }
        }

        $editRecord = null;
        if (!empty($_GET['edit'])) {
            $editRecord = $this->lendingModel->getById((int) $_GET['edit']);
        }

        $records     = $this->lendingModel->getAll();
        $openRecords = $this->lendingModel->getOpenRecords();
        $allRepayments = $this->lendingModel->getAllRepayments();
        $accounts    = array_values(array_filter(
            $this->accountModel->getList(),
            static fn (array $a): bool => ($a['account_type'] ?? '') !== 'credit_card'
        ));
        $summary  = $this->lendingModel->getSummary();
        $allLoans = $this->loanModel->getAll();

        return $this->render('lending/index.php', [
            'records'       => $records,
            'openRecords'   => $openRecords,
            'allRepayments' => $allRepayments,
            'accounts'      => $accounts,
            'summary'       => $summary,
            'editRecord'    => $editRecord,
            'allLoans'      => $allLoans,
            'smtpReady'     => $this->smtpIsReady(),
            'flash'         => $flash,
        ]);
    }
}
