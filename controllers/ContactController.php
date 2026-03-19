<?php

namespace Controllers;

use Models\Contact;

class ContactController extends BaseController
{
    private Contact $contactModel;

    public function __construct()
    {
        parent::__construct();
        $this->contactModel = new Contact($this->database);
    }

    public function index(): string
    {
        $action = $_GET['action'] ?? '';
        if ($action === 'search') {
            return $this->search();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'contact') {
            $this->contactModel->create($_POST);
            header('Location: ?module=contacts');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'contact_update') {
            $this->contactModel->update($_POST);
            header('Location: ?module=contacts');
            exit;
        }

        $editContact = null;
        if (!empty($_GET['edit'])) {
            $editContact = $this->contactModel->getById((int) $_GET['edit']);
        }

        $contacts = $this->contactModel->getAll();
        return $this->render('contacts/index.php', [
            'contacts' => $contacts,
            'editContact' => $editContact,
        ]);
    }

    private function search(): string
    {
        $query = (string) ($_GET['q'] ?? '');
        $results = $this->contactModel->search($query, 20);
        header('Content-Type: application/json');
        return json_encode($results, JSON_THROW_ON_ERROR);
    }
}
