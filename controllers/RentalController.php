<?php

namespace Controllers;

use Models\Account;
use Models\Contact;
use Models\Rental;

class RentalController extends BaseController
{
    private Rental $rentalModel;
    private Contact $contactModel;
    private Account $accountModel;

    public function __construct()
    {
        parent::__construct();
        $this->rentalModel = new Rental($this->database);
        $this->contactModel = new Contact($this->database);
        $this->accountModel = new Account($this->database);
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
        if (!empty($_SESSION['rental_flash'])) {
            $flash = $_SESSION['rental_flash'];
            unset($_SESSION['rental_flash']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = $_POST['form'] ?? '';

            if ($form === 'property') {
                $this->rentalModel->createProperty($_POST);
            }

            if ($form === 'tenant') {
                $this->rentalModel->createTenant($_POST);
            }

            if ($form === 'contract') {
                $this->rentalModel->createContract($_POST);
            }

            if ($form === 'transaction') {
                $ok = $this->rentalModel->recordPayment($_POST);
                if ($ok && ($_POST['send_email'] ?? '') === '1' && ($_POST['payment_status'] ?? '') === 'paid') {
                    $contractId = (int) ($_POST['contract_id'] ?? 0);
                    $pdo  = $this->database->connect();
                    $stmt = $pdo->prepare(
                        'SELECT t.name AS tenant_name, t.email AS tenant_email,
                                p.property_name, rc.rent_amount
                         FROM rental_contracts rc
                         JOIN tenants t    ON t.id = rc.tenant_id
                         JOIN properties p ON p.id = rc.property_id
                         WHERE rc.id = :id LIMIT 1'
                    );
                    $stmt->execute([':id' => $contractId]);
                    $info = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($info && !empty($info['tenant_email'])) {
                        $mailer = $this->buildMailer();
                        if ($mailer) {
                            $amt   = 'Rs.' . number_format((float) ($_POST['paid_amount'] ?? 0), 2, '.', ',');
                            $month = !empty($_POST['rent_month']) ? date('F Y', strtotime($_POST['rent_month'])) : date('F Y');
                            $html  = $this->notificationEmail(
                                'Rent Received',
                                $info['tenant_name'],
                                'We acknowledge receipt of rent of ' . $amt . ' for ' . $info['property_name'] . ' for the month of ' . $month . '. Thank you.',
                                ['Property' => $info['property_name'], 'Month' => $month, 'Amount received' => $amt]
                            );
                            $mailer->send($info['tenant_email'], 'Rent received – ' . $info['property_name'] . ' – ' . $month, $html);
                        }
                    }
                }
                header('Location: ?module=rental');
                exit;
            }

            if ($form === 'rental_reminder') {
                $txnId = (int) ($_POST['transaction_id'] ?? 0);
                $pdo   = $this->database->connect();
                $stmt  = $pdo->prepare(
                    'SELECT rt.due_date, rt.rent_month, rt.paid_amount,
                            t.name AS tenant_name, t.email AS tenant_email,
                            p.property_name, rc.rent_amount
                     FROM rental_transactions rt
                     JOIN rental_contracts rc ON rc.id = rt.contract_id
                     JOIN tenants t           ON t.id  = rc.tenant_id
                     JOIN properties p        ON p.id  = rc.property_id
                     WHERE rt.id = :id LIMIT 1'
                );
                $stmt->execute([':id' => $txnId]);
                $info = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($info && !empty($info['tenant_email'])) {
                    $mailer = $this->buildMailer();
                    if ($mailer) {
                        $rent  = 'Rs.' . number_format((float) ($info['rent_amount'] ?? 0), 2, '.', ',');
                        $due   = !empty($info['due_date'])   ? date('d M Y', strtotime($info['due_date']))   : 'soon';
                        $month = !empty($info['rent_month']) ? date('F Y',   strtotime($info['rent_month'])) : '';
                        $body  = 'This is a reminder that rent of ' . $rent . ' for ' . $info['property_name']
                            . ($month ? ' for the month of ' . $month : '') . ' is due on ' . $due . '. Please arrange payment.';
                        $details = ['Property' => $info['property_name']];
                        if ($month) {
                            $details['Month'] = $month;
                        }
                        $details['Rent amount'] = $rent;
                        $details['Due date']    = $due;
                        $html = $this->notificationEmail('Rent Payment Reminder', $info['tenant_name'], $body, $details);
                        $sent = $mailer->send($info['tenant_email'], 'Rent reminder – ' . $info['property_name'] . ($month ? ' – ' . $month : ''), $html);
                        $_SESSION['rental_flash'] = $sent
                            ? ['type' => 'success', 'msg' => 'Reminder sent to ' . $info['tenant_name'] . '.']
                            : ['type' => 'error',   'msg' => 'Send failed: ' . $mailer->getLastError()];
                    } else {
                        $_SESSION['rental_flash'] = ['type' => 'error', 'msg' => 'SMTP not configured.'];
                    }
                } else {
                    $_SESSION['rental_flash'] = ['type' => 'error', 'msg' => empty($info) ? 'Transaction not found.' : 'No email address for this tenant.'];
                }
                header('Location: ?module=rental');
                exit;
            }

            if ($form === 'property_update') {
                $this->rentalModel->updateProperty($_POST);
            }

            if ($form === 'tenant_update') {
                $this->rentalModel->updateTenant($_POST);
            }

            header('Location: ?module=rental');
            exit;
        }

        $editProperty = null;
        if (!empty($_GET['edit_property'])) {
            $editProperty = $this->rentalModel->getPropertyById((int) $_GET['edit_property']);
        }

        $editTenant = null;
        if (!empty($_GET['edit_tenant'])) {
            $editTenant = $this->rentalModel->getTenantById((int) $_GET['edit_tenant']);
        }

        $properties   = $this->rentalModel->getProperties();
        $tenants      = $this->rentalModel->getTenants();
        $contacts     = $this->rentalModel->getContacts();
        $accounts     = array_values(array_filter(
            $this->accountModel->getList(),
            static fn (array $a): bool => ($a['account_type'] ?? '') !== 'credit_card'
        ));
        $contracts    = $this->rentalModel->getContracts();
        $transactions = $this->rentalModel->getTransactions();
        $upcoming     = $this->rentalModel->getUpcomingRent(5);
        $summary      = $this->rentalModel->getSummary();

        return $this->render('rental/index.php', [
            'properties'   => $properties,
            'tenants'      => $tenants,
            'contacts'     => $contacts,
            'accounts'     => $accounts,
            'contracts'    => $contracts,
            'transactions' => $transactions,
            'upcoming'     => $upcoming,
            'summary'      => $summary,
            'editProperty' => $editProperty,
            'editTenant'   => $editTenant,
            'smtpReady'    => $this->smtpIsReady(),
            'flash'        => $flash,
        ]);
    }
}
