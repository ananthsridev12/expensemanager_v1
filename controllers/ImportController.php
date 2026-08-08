<?php

namespace Controllers;

use Models\Account;
use Models\Category;
use Models\Contact;
use Models\Import;
use Models\PaymentMethod;

class ImportController extends BaseController
{
    private Import        $importModel;
    private Account       $accountModel;
    private Category      $categoryModel;
    private PaymentMethod $paymentMethodModel;
    private Contact       $contactModel;

    public function __construct()
    {
        parent::__construct();
        $this->importModel        = new Import($this->database);
        $this->accountModel       = new Account($this->database);
        $this->categoryModel      = new Category($this->database);
        $this->paymentMethodModel = new PaymentMethod($this->database);
        $this->contactModel       = new Contact($this->database);
    }

    public function index(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $form   = $_POST['form'] ?? '';

        // ── AJAX: approve a staging row ──────────────────────────────────────
        if ($method === 'POST' && $form === 'approve_staging') {
            header('Content-Type: application/json');
            $stagingId = (int) ($_POST['staging_id'] ?? 0);
            if ($stagingId <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Invalid staging id.']);
                exit;
            }
            try {
                $txId = $this->importModel->approveStagingRow($stagingId, $_POST);
                echo json_encode(['ok' => true, 'tx_id' => $txId]);
            } catch (\Throwable $e) {
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // ── AJAX: skip a staging row ─────────────────────────────────────────
        if ($method === 'POST' && $form === 'skip_staging') {
            header('Content-Type: application/json');
            $stagingId = (int) ($_POST['staging_id'] ?? 0);
            if ($stagingId <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Invalid staging id.']);
                exit;
            }
            $this->importModel->skipStagingRow($stagingId);
            echo json_encode(['ok' => true]);
            exit;
        }

        // ── Rollback a batch ─────────────────────────────────────────────────
        if ($method === 'POST' && $form === 'rollback') {
            $batchId = (int) ($_POST['batch_id'] ?? 0);
            if ($batchId > 0) {
                $deleted = $this->importModel->rollbackBatch($batchId);
                $_SESSION['import_msg'] = "Batch #{$batchId} rolled back — {$deleted} transaction(s) removed.";
            }
            header('Location: ?module=import');
            exit;
        }

        // ── Upload & parse ───────────────────────────────────────────────────
        if ($method === 'POST' && $form === 'upload') {
            $accountId   = (int) ($_POST['account_id'] ?? 0);
            $accountType = preg_replace('/[^a-z_]/', '', (string) ($_POST['account_type'] ?? 'savings'));
            $file        = $_FILES['csv_file'] ?? null;

            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['import_error'] = 'Upload failed — please choose a CSV or XLSX file.';
                header('Location: ?module=import');
                exit;
            }
            if (!preg_match('/\.(csv|xlsx)$/i', $file['name'])) {
                $_SESSION['import_error'] = 'Only .csv and .xlsx files are supported.';
                header('Location: ?module=import');
                exit;
            }
            if ($accountId <= 0) {
                $_SESSION['import_error'] = 'Please select an account.';
                header('Location: ?module=import');
                exit;
            }

            $allRows     = $this->importModel->readFile($file['tmp_name'], $file['name']);
            $parserClass = $this->importModel->detectParser($allRows);

            if (!$parserClass) {
                $available = $this->importModel->getAvailableParsers();
                $names     = $available ? implode(', ', array_map(fn($c) => $c::name(), $available)) : 'none added yet';
                $_SESSION['import_error'] = "Bank format not recognised. Supported: {$names}.";
                header('Location: ?module=import');
                exit;
            }

            $rows = $parserClass::parse($allRows);
            if (empty($rows)) {
                $_SESSION['import_error'] = 'No transactions found in the file.';
                header('Location: ?module=import');
                exit;
            }

            try {
                $batchId = $this->importModel->insertToStaging($rows, [
                    'account_id'   => $accountId,
                    'account_type' => $accountType,
                    'parser'       => $parserClass,
                    'bank_name'    => $parserClass::name(),
                ]);
                $_SESSION['import_msg'] = count($rows) . ' transaction(s) staged for review — batch #' . $batchId . '.';
                header('Location: ?module=import&batch=' . $batchId);
            } catch (\Throwable $e) {
                $_SESSION['import_error'] = 'Failed to stage import: ' . $e->getMessage();
                header('Location: ?module=import');
            }
            exit;
        }

        // ── GET: build view data ─────────────────────────────────────────────
        $selectedBatch  = (int) ($_GET['batch'] ?? 0);
        $accounts       = $this->accountModel->getAllWithBalances();
        $categories     = $this->categoryModel->getAllWithSubcategories();
        $paymentMethods = $this->paymentMethodModel->getAll();
        $contacts       = $this->contactModel->getAll();
        $batches        = $this->importModel->getBatches();
        $pendingRows    = $this->importModel->getPendingRows($selectedBatch);
        $parsers        = $this->importModel->getAvailableParsers();
        $msg            = $_SESSION['import_msg']   ?? null;
        $error          = $_SESSION['import_error'] ?? null;
        unset($_SESSION['import_msg'], $_SESSION['import_error']);

        return $this->render('import/index.php', compact(
            'accounts', 'categories', 'paymentMethods', 'contacts',
            'batches', 'pendingRows', 'parsers', 'selectedBatch', 'msg', 'error'
        ));
    }
}
