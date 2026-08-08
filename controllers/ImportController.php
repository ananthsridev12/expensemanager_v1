<?php

namespace Controllers;

use Models\Account;
use Models\Category;
use Models\Import;

class ImportController extends BaseController
{
    private Import   $importModel;
    private Account  $accountModel;
    private Category $categoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->importModel   = new Import($this->database);
        $this->accountModel  = new Account($this->database);
        $this->categoryModel = new Category($this->database);
    }

    public function index(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $form   = $_POST['form'] ?? '';

        // ── Rollback a batch ─────────────────────────────────────────────────
        if ($method === 'POST' && $form === 'rollback') {
            $batchId = (int)($_POST['batch_id'] ?? 0);
            if ($batchId > 0) {
                $deleted = $this->importModel->rollbackBatch($batchId);
                $_SESSION['import_msg'] = "Batch #{$batchId} rolled back — {$deleted} transaction(s) removed.";
            }
            header('Location: ?module=import');
            exit;
        }

        // ── Confirm import ───────────────────────────────────────────────────
        if ($method === 'POST' && $form === 'confirm') {
            $preview = $_SESSION['import_preview'] ?? null;
            if ($preview) {
                unset($_SESSION['import_preview']);
                $categoryId = (int)($_POST['category_id'] ?? 0);
                $skipDups   = ($_POST['skip_duplicates'] ?? '1') === '1';
                try {
                    $batchId = $this->importModel->insertBatch($preview['rows'], [
                        'account_id'      => $preview['account_id'],
                        'account_type'    => $preview['account_type'],
                        'category_id'     => $categoryId ?: null,
                        'parser'          => $preview['parser'],
                        'bank_name'       => $preview['bank_name'],
                        'skip_duplicates' => $skipDups,
                    ]);
                    $imported = $preview['counts']['total'] - ($skipDups ? $preview['counts']['duplicates'] : 0);
                    $_SESSION['import_msg'] = "Imported {$imported} transaction(s) as batch #{$batchId}.";
                } catch (\Throwable $e) {
                    $_SESSION['import_error'] = 'Import failed: ' . $e->getMessage();
                }
            }
            header('Location: ?module=import');
            exit;
        }

        // ── Cancel preview ───────────────────────────────────────────────────
        if ($method === 'POST' && $form === 'cancel_preview') {
            unset($_SESSION['import_preview']);
            header('Location: ?module=import');
            exit;
        }

        // ── Upload & parse ───────────────────────────────────────────────────
        if ($method === 'POST' && $form === 'upload') {
            $accountId   = (int)($_POST['account_id'] ?? 0);
            $accountType = preg_replace('/[^a-z_]/', '', (string)($_POST['account_type'] ?? 'savings'));
            $file        = $_FILES['csv_file'] ?? null;

            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['import_error'] = 'Upload failed — please choose a CSV file.';
                header('Location: ?module=import');
                exit;
            }
            if (!preg_match('/\.csv$/i', $file['name'])) {
                $_SESSION['import_error'] = 'Only .csv files are supported.';
                header('Location: ?module=import');
                exit;
            }
            if ($accountId <= 0) {
                $_SESSION['import_error'] = 'Please select an account.';
                header('Location: ?module=import');
                exit;
            }

            // Detect bank format
            $fh      = fopen($file['tmp_name'], 'r');
            $bom     = fread($fh, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($fh);
            $headers = fgetcsv($fh) ?: [];
            fclose($fh);
            $headers = array_map('trim', $headers);

            $parserClass = $this->importModel->detectParser($headers);
            if (!$parserClass) {
                $available   = $this->importModel->getAvailableParsers();
                $names       = array_map(fn($c) => $c::name(), $available);
                $supported   = $names ? implode(', ', $names) : 'none added yet';
                $_SESSION['import_error'] = "Bank format not recognised. Supported: {$supported}.";
                header('Location: ?module=import');
                exit;
            }

            $rows  = $this->importModel->parseCsvFile($file['tmp_name'], $parserClass);
            $rows  = $this->importModel->flagDuplicates($rows, $accountId);
            $dups  = count(array_filter($rows, fn($r) => $r['is_duplicate']));

            $_SESSION['import_preview'] = [
                'parser'       => $parserClass,
                'bank_name'    => $parserClass::name(),
                'account_id'   => $accountId,
                'account_type' => $accountType,
                'rows'         => $rows,
                'counts'       => ['total' => count($rows), 'duplicates' => $dups],
            ];
            header('Location: ?module=import&preview=1');
            exit;
        }

        // ── GET: clear stale preview if not in preview mode ──────────────────
        if (empty($_GET['preview'])) {
            unset($_SESSION['import_preview']);
        }

        $accounts   = $this->accountModel->getAllWithBalances();
        $categories = $this->categoryModel->getAllWithSubcategories();
        $batches    = $this->importModel->getBatches();
        $parsers    = $this->importModel->getAvailableParsers();
        $preview    = $_SESSION['import_preview'] ?? null;
        $msg        = $_SESSION['import_msg']   ?? null;
        $error      = $_SESSION['import_error'] ?? null;
        unset($_SESSION['import_msg'], $_SESSION['import_error']);

        return $this->render('import/index.php', compact(
            'accounts', 'categories', 'batches', 'parsers', 'preview', 'msg', 'error'
        ));
    }
}
