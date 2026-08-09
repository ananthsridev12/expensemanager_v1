<?php

namespace Controllers;

use Models\Account;
use Models\Category;
use Models\Contact;
use Models\PaymentMethod;
use Models\Transaction;

class ReceiptController extends BaseController
{
    private Transaction   $transactionModel;
    private Account       $accountModel;
    private Category      $categoryModel;
    private PaymentMethod $paymentMethodModel;
    private Contact       $contactModel;

    public function __construct()
    {
        parent::__construct();
        $this->transactionModel   = new Transaction($this->database);
        $this->accountModel       = new Account($this->database);
        $this->categoryModel      = new Category($this->database);
        $this->paymentMethodModel = new PaymentMethod($this->database);
        $this->contactModel       = new Contact($this->database);
    }

    public function index(): string
    {
        if (($_GET['action'] ?? '') === 'contact_search') {
            header('Content-Type: application/json');
            return json_encode($this->contactModel->search((string) ($_GET['q'] ?? ''), 20));
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $form   = $_POST['form'] ?? '';

        // ── AJAX: scan receipt ───────────────────────────────────────────────
        if ($method === 'POST' && $form === 'scan') {
            header('Content-Type: application/json');

            $file = $_FILES['receipt'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['ok' => false, 'error' => 'Upload failed.']);
                exit;
            }

            $mimeType = $this->detectMime($file['tmp_name'], $file['name']);
            if (!$mimeType) {
                echo json_encode(['ok' => false, 'error' => 'Unsupported image type. Use JPEG, PNG, or WebP.']);
                exit;
            }

            echo json_encode($this->callGemini($file['tmp_name'], $mimeType));
            exit;
        }

        // ── POST: create transaction from scanned receipt ────────────────────
        if ($method === 'POST' && $form === 'create') {
            [$acctType, $acctId] = $this->parseAccountToken((string) ($_POST['account_id'] ?? ''));

            if ($acctId > 0) {
                $this->transactionModel->create([
                    'transaction_date'  => $_POST['transaction_date'] ?? date('Y-m-d'),
                    'account_type'      => $acctType,
                    'account_id'        => $acctId,
                    'transaction_type'  => 'expense',
                    'category_id'       => !empty($_POST['category_id'])       ? (int) $_POST['category_id']       : null,
                    'subcategory_id'    => !empty($_POST['subcategory_id'])     ? (int) $_POST['subcategory_id']    : null,
                    'payment_method_id' => !empty($_POST['payment_method_id']) ? (int) $_POST['payment_method_id'] : null,
                    'contact_id'        => !empty($_POST['contact_id'])        ? (int) $_POST['contact_id']        : null,
                    'amount'            => is_numeric($_POST['amount'] ?? null) ? (float) $_POST['amount'] : 0.0,
                    'notes'             => trim((string) ($_POST['notes'] ?? '')),
                ]);
            }

            header('Location: ?module=transactions');
            exit;
        }

        $geminiCfg      = $this->loadGeminiConfig();
        $accounts       = $this->accountModel->getList();
        $categories     = $this->categoryModel->getAllWithSubcategories();
        $paymentMethods = $this->paymentMethodModel->getAll();

        return $this->render('receipt/index.php', [
            'accounts'       => $accounts,
            'categories'     => $categories,
            'paymentMethods' => $paymentMethods,
            'apiKeySet'      => !empty(trim((string) ($geminiCfg['api_key'] ?? ''))),
        ]);
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

    private function detectMime(string $tmpPath, string $origName): ?string
    {
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        if (isset($map[$ext])) {
            return $map[$ext];
        }
        $fh = fopen($tmpPath, 'rb');
        if (!$fh) return null;
        $magic = fread($fh, 4);
        fclose($fh);
        if (str_starts_with($magic, "\xFF\xD8"))   return 'image/jpeg';
        if (str_starts_with($magic, "\x89PNG"))    return 'image/png';
        if (str_starts_with($magic, 'RIFF'))       return 'image/webp';
        return null;
    }

    private function loadGeminiConfig(): array
    {
        $path = __DIR__ . '/../config/gemini.php';
        return file_exists($path) ? (require $path) : [];
    }

    private function callGemini(string $tmpPath, string $mimeType): array
    {
        $cfg    = $this->loadGeminiConfig();
        $apiKey = trim((string) ($cfg['api_key'] ?? ''));
        if ($apiKey === '') {
            return ['ok' => false, 'error' => 'Gemini API key not set. Add it to config/gemini.php.'];
        }

        $model   = $cfg['model'] ?? 'gemini-1.5-flash';
        $url     = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $imgData = base64_encode((string) file_get_contents($tmpPath));

        $prompt = 'This is a store receipt or bill photo. '
            . 'Extract these details and return ONLY a valid JSON object — no markdown, no explanation: '
            . '{"date":"YYYY-MM-DD","merchant":"store or merchant name","amount":0.00,"notes":"brief purchase description"}. '
            . 'Use the printed date on the receipt (convert to YYYY-MM-DD format). '
            . 'Use the grand total / amount paid. If anything is unclear, make a reasonable estimate.';

        $payload = [
            'contents' => [[
                'parts' => [
                    ['inline_data' => ['mime_type' => $mimeType, 'data' => $imgData]],
                    ['text' => $prompt],
                ],
            ]],
            'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 300],
        ];

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'cURL is not available on this server.'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw   = curl_exec($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            return ['ok' => false, 'error' => 'Network error: ' . $curlErr];
        }
        if ($code !== 200) {
            $decoded = json_decode((string) $raw, true);
            $msg     = $decoded['error']['message'] ?? "HTTP {$code}";
            return ['ok' => false, 'error' => 'Gemini error: ' . $msg];
        }

        $decoded = json_decode((string) $raw, true);
        $text    = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Strip markdown code fences if the model added them
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/i', '', $text);

        $data = json_decode(trim($text), true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Could not parse AI response. Raw: ' . substr($text, 0, 200)];
        }

        return [
            'ok'       => true,
            'date'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'] ?? '') ? $data['date'] : date('Y-m-d'),
            'merchant' => trim((string) ($data['merchant'] ?? '')),
            'amount'   => max(0.0, (float) ($data['amount'] ?? 0)),
            'notes'    => trim((string) ($data['notes'] ?? $data['merchant'] ?? '')),
        ];
    }
}
