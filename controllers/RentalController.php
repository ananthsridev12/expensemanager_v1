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
        if (($_GET['action'] ?? '') === 'contact_search') {
            header('Content-Type: application/json');
            return json_encode(
                $this->contactModel->search((string) ($_GET['q'] ?? ''), 20)
            );
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
                $this->rentalModel->recordPayment($_POST);
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

        $properties = $this->rentalModel->getProperties();
        $tenants = $this->rentalModel->getTenants();
        $contacts = $this->rentalModel->getContacts();
        $accounts = array_values(array_filter(
            $this->accountModel->getList(),
            static fn (array $account): bool => ($account['account_type'] ?? '') !== 'credit_card'
        ));
        $contracts = $this->rentalModel->getContracts();
        $transactions = $this->rentalModel->getTransactions();
        $upcoming = $this->rentalModel->getUpcomingRent(5);
        $summary = $this->rentalModel->getSummary();

        return $this->render('rental/index.php', [
            'properties' => $properties,
            'tenants' => $tenants,
            'contacts' => $contacts,
            'accounts' => $accounts,
            'contracts' => $contracts,
            'transactions' => $transactions,
            'upcoming' => $upcoming,
            'summary' => $summary,
            'editProperty' => $editProperty,
            'editTenant' => $editTenant,
        ]);
    }
}
