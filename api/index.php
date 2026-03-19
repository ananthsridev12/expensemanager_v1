<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/src/ApiResponse.php';
require_once __DIR__ . '/src/ApiRequest.php';
require_once __DIR__ . '/src/ApiJwt.php';
require_once __DIR__ . '/src/ApiRateLimiter.php';
require_once __DIR__ . '/src/ApiAuth.php';
require_once __DIR__ . '/src/ApiTransactionService.php';

use Config\Database;
use Models\Account;
use Models\Category;
use Models\Transaction;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
$allowedOrigin = getenv('API_ALLOWED_ORIGIN') ?: '*';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($allowedOrigin === '*' || $requestOrigin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . ($allowedOrigin === '*' ? '*' : $requestOrigin));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$database = new Database();
$pdo = $database->connect();
$request = new ApiRequest();
$response = new ApiResponse();

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'), '/');
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/api/v1', PHP_URL_PATH) ?: '/api/v1';
$path = '/' . ltrim(substr($uriPath, strlen($basePath)), '/');
$path = preg_replace('#/+#', '/', $path);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$auth = new ApiAuth($pdo);

try {
    if ($path === '/v1/auth/pin-login' && $method === 'POST') {
        $rateLimiter = new ApiRateLimiter(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'expensemanager_api_login');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!$rateLimiter->allow($ip, 5, 300)) {
            $response->error(429, 'RATE_LIMITED', 'Too many login attempts. Please try later.');
        }

        $payload = $request->json();
        $pin = trim((string) ($payload['pin'] ?? ''));
        $deviceName = trim((string) ($payload['device_name'] ?? ''));
        if ($pin === '') {
            $response->error(422, 'VALIDATION_ERROR', 'PIN is required.');
        }

        $login = $auth->loginWithPin($pin, $deviceName);
        if (!$login['success']) {
            $response->error(401, 'AUTH_FAILED', 'Invalid PIN.');
        }

        $response->success($login['data'], ['timestamp' => date('c')]);
    }

    $user = $auth->requireAuth();

    if ($path === '/v1/dashboard/summary' && $method === 'GET') {
        $accountModel = new Account($database);
        $transactionModel = new Transaction($database);
        $accountsSummary = $accountModel->getSummary();
        $totalsByType = $transactionModel->getTotalsByType();
        $summary = [
            'bank_balance' => (float) ($accountsSummary['total_balance'] ?? 0),
            'accounts_count' => (int) ($accountsSummary['count'] ?? 0),
            'totals' => [
                'income' => (float) ($totalsByType['income'] ?? 0),
                'expense' => (float) ($totalsByType['expense'] ?? 0),
                'transfer' => (float) ($totalsByType['transfer'] ?? 0),
            ],
        ];
        $response->success($summary, ['timestamp' => date('c'), 'user_id' => $user['id']]);
    }

    if ($path === '/v1/accounts' && $method === 'GET') {
        $accountModel = new Account($database);
        $rows = $accountModel->getAllWithBalances();
        $mapped = array_map(static function (array $row): array {
            $isCreditCard = ($row['account_type'] ?? '') === 'credit_card';
            $balanceOrLimit = $isCreditCard
                ? (float) ($row['credit_limit'] ?? 0)
                : (float) ($row['balance'] ?? 0);
            return [
                'id' => (int) ($row['id'] ?? 0),
                'bank_name' => (string) ($row['bank_name'] ?? ''),
                'account_name' => (string) ($row['account_name'] ?? ''),
                'account_type' => (string) ($row['account_type'] ?? 'savings'),
                'balance_or_limit' => $balanceOrLimit,
                'outstanding_balance' => (float) ($row['outstanding_balance'] ?? 0),
            ];
        }, $rows);

        $response->success($mapped, ['count' => count($mapped)]);
    }

    if ($path === '/v1/categories' && $method === 'GET') {
        $categoryModel = new Category($database);
        $categories = $categoryModel->getCategoryList();
        $response->success($categories, ['count' => count($categories)]);
    }

    if ($path === '/v1/subcategories' && $method === 'GET') {
        $categoryId = (int) ($_GET['category_id'] ?? 0);
        if ($categoryId <= 0) {
            $response->error(422, 'VALIDATION_ERROR', 'category_id is required.');
        }

        $stmt = $pdo->prepare('SELECT id, name FROM subcategories WHERE category_id = :category_id ORDER BY created_at ASC');
        $stmt->execute([':category_id' => $categoryId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response->success($items, ['count' => count($items)]);
    }

    if ($path === '/v1/transactions' && $method === 'GET') {
        $startDate = (string) ($_GET['start_date'] ?? date('Y-m-01'));
        $endDate = (string) ($_GET['end_date'] ?? date('Y-m-d'));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $sql = <<<SQL
SELECT
    t.id,
    t.transaction_date,
    t.transaction_type,
    t.account_type,
    t.amount,
    t.notes,
    c.name AS category_name,
    sc.name AS subcategory_name,
    CASE
        WHEN t.account_type = 'loan' THEN CONCAT('Loan - ', l.loan_name)
        WHEN t.account_type = 'lending' THEN CONCAT('Lending - ', ct.name)
        WHEN t.account_type = 'rental' THEN 'Rental'
        ELSE CONCAT(COALESCE(a.bank_name, '-'), ' - ', COALESCE(a.account_name, '-'))
    END AS account_display
FROM transactions t
LEFT JOIN categories c ON c.id = t.category_id
LEFT JOIN subcategories sc ON sc.id = t.subcategory_id
LEFT JOIN accounts a ON a.id = t.account_id
LEFT JOIN loans l ON (t.account_type = 'loan' AND t.reference_type = 'loan' AND l.id = t.reference_id)
LEFT JOIN lending_records lr ON (t.reference_type = 'lending' AND lr.id = t.reference_id)
LEFT JOIN contacts ct ON ct.id = lr.contact_id
WHERE t.transaction_date BETWEEN :start_date AND :end_date
ORDER BY t.transaction_date DESC, t.created_at DESC
LIMIT :limit OFFSET :offset
SQL;
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':start_date', $startDate);
        $stmt->bindValue(':end_date', $endDate);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE transaction_date BETWEEN :start_date AND :end_date');
        $countStmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $total = (int) $countStmt->fetchColumn();

        $response->success($items, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    if ($path === '/v1/transactions' && $method === 'POST') {
        $payload = $request->json();
        $service = new ApiTransactionService($database);
        $result = $service->create($payload);
        if (!$result['success']) {
            $response->error(422, 'VALIDATION_ERROR', $result['message'] ?? 'Transaction failed.');
        }

        $response->success($result['data'], ['timestamp' => date('c')]);
    }

    $response->error(404, 'NOT_FOUND', 'Endpoint not found.');
} catch (ApiUnauthorizedException $exception) {
    $response->error(401, 'UNAUTHORIZED', $exception->getMessage());
} catch (Throwable $exception) {
    $response->error(500, 'SERVER_ERROR', $exception->getMessage());
}
