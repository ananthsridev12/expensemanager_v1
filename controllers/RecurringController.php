<?php

namespace Controllers;

use Models\Account;
use Models\Category;
use Models\PaymentMethod;
use Models\Recurring;

class RecurringController extends BaseController
{
    private Recurring     $recurringModel;
    private Account       $accountModel;
    private Category      $categoryModel;
    private PaymentMethod $paymentMethodModel;

    public function __construct()
    {
        parent::__construct();
        $this->recurringModel     = new Recurring($this->database);
        $this->accountModel       = new Account($this->database);
        $this->categoryModel      = new Category($this->database);
        $this->paymentMethodModel = new PaymentMethod($this->database);
    }

    public function index(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $form   = $_POST['form'] ?? '';

        // ── AJAX: approve pending item ────────────────────────────────────────
        if ($method === 'POST' && $form === 'approve_pending') {
            header('Content-Type: application/json');
            $pendingId = (int) ($_POST['pending_id'] ?? 0);
            if ($pendingId <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Invalid pending id.']);
                exit;
            }
            try {
                $txId = $this->recurringModel->approvePending($pendingId, $_POST);
                echo json_encode(['ok' => true, 'tx_id' => $txId]);
            } catch (\Throwable $e) {
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // ── AJAX: skip pending item ───────────────────────────────────────────
        if ($method === 'POST' && $form === 'skip_pending') {
            header('Content-Type: application/json');
            $pendingId = (int) ($_POST['pending_id'] ?? 0);
            if ($pendingId <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Invalid pending id.']);
                exit;
            }
            $this->recurringModel->skipPending($pendingId);
            echo json_encode(['ok' => true]);
            exit;
        }

        // ── POST: deactivate template ─────────────────────────────────────────
        if ($method === 'POST' && $form === 'deactivate') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $this->recurringModel->deactivate($id);
            }
            header('Location: ?module=recurring');
            exit;
        }

        // ── POST: create new recurring template ───────────────────────────────
        if ($method === 'POST' && $form === 'create') {
            $token = (string) ($_POST['account_token'] ?? '');
            [$acctType, $acctId] = $this->parseAccountToken($token);

            $id = $this->recurringModel->create(array_merge($_POST, [
                'account_type' => $acctType,
                'account_id'   => $acctId > 0 ? $acctId : null,
            ]));

            $_SESSION['recurring_msg'] = $id > 0 ? 'Recurring transaction saved.' : 'Failed to save.';
            header('Location: ?module=recurring');
            exit;
        }

        // ── GET: generate any due pending items then render ───────────────────
        $this->recurringModel->generatePending();

        $msg = $_SESSION['recurring_msg'] ?? null;
        unset($_SESSION['recurring_msg']);

        $pendingItems   = $this->recurringModel->getPendingItems();
        $activeTemplates= $this->recurringModel->getAll();
        $accounts       = $this->accountModel->getList();
        $categories     = $this->categoryModel->getAllWithSubcategories();
        $paymentMethods = $this->paymentMethodModel->getAll();

        return $this->render('recurring/index.php', compact(
            'pendingItems', 'activeTemplates', 'accounts',
            'categories', 'paymentMethods', 'msg'
        ));
    }

    private function parseAccountToken(string $token): array
    {
        if (strpos($token, ':') === false) {
            return ['savings', (int) $token];
        }
        [$type, $id] = explode(':', $token, 2);
        $allowed = ['savings', 'current', 'credit_card', 'cash', 'wallet', 'other'];
        return [in_array($type, $allowed, true) ? $type : 'savings', (int) $id];
    }
}
