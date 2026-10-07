<?php

namespace Controllers;

use Mcp\Server;
use Models\OAuthToken;

class McpController extends BaseController
{
    public function handle(): void
    {
        ob_start(); // capture any stray PHP output for the whole handler

        // CORS — must be set before any exit path
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '') {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Mcp-Session-Id');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Expose-Headers: Mcp-Session-Id');

        try {
            $this->dispatch();
        } catch (\Throwable $e) {
            ob_end_clean();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => ['code' => -32603, 'message' => $e->getMessage()],
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'OPTIONS') {
            ob_end_clean();
            http_response_code(204);
            return;
        }

        if ($method !== 'GET' && $method !== 'POST') {
            ob_end_clean();
            header('Allow: POST, GET, OPTIONS');
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'method_not_allowed']);
            return;
        }

        // ── Bearer token auth (required for both GET and POST) ────────────────
        $token  = $this->extractBearer();
        $authed = $token !== null && (new OAuthToken($this->database))->verify($token);

        if (!$authed) {
            ob_end_clean();
            if ($method === 'GET' && isset($_GET['setup'])) {
                $this->infoPage();
                return;
            }
            $this->unauthorized();
            return;
        }

        // ── Authenticated GET → setup info page ───────────────────────────────
        if ($method === 'GET') {
            ob_end_clean();
            $this->infoPage();
            return;
        }

        // ── Dispatch POST to MCP server ───────────────────────────────────────
        $body   = (string) file_get_contents('php://input');
        $server = new Server($this->database);
        $result = $server->handleHttp($body);
        ob_end_clean();
        http_response_code($result['status']);
        if ($result['body'] !== null) {
            header('Content-Type: application/json');
            echo json_encode($result['body'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function extractBearer(): ?string
    {
        // Apache/PHP-FPM can strip Authorization; try multiple sources
        $auth = $_SERVER['HTTP_AUTHORIZATION']
             ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
             ?? '';
        if ($auth === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (str_starts_with($auth, 'Bearer ')) {
            $t = trim(substr($auth, 7));
            return $t !== '' ? $t : null;
        }
        return null;
    }

    private function unauthorized(): void
    {
        $base = $this->baseUrl();
        header('WWW-Authenticate: Bearer resource_metadata="' . $base . '/oauth/protected-resource"');
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'unauthorized', 'error_description' => 'Bearer token required. Connect via OAuth first.']);
    }

    private function infoPage(): void
    {
        $base   = $this->baseUrl();
        $checks = [];

        // Check DB tables exist
        try {
            $pdo = $this->database->connect();
            foreach (['oauth_clients', 'oauth_codes', 'api_tokens'] as $tbl) {
                $exists = $pdo->query("SHOW TABLES LIKE '{$tbl}'")->fetchColumn();
                $checks[] = [
                    'status' => $exists ? 'ok'   : 'fail',
                    'label'  => "Table `{$tbl}`",
                    'detail' => $exists ? '' : 'Run migration 023_oauth_mcp.sql',
                ];
            }
        } catch (\Throwable $e) {
            $checks[] = ['status' => 'fail', 'label' => 'Database connection', 'detail' => $e->getMessage()];
        }

        // Check MCP Server can be instantiated (catches class-not-found / PHP errors)
        try {
            $server = new Server($this->database);
            $count  = count($server->getToolCount());
            $checks[] = ['status' => 'ok', 'label' => "MCP tools loaded ({$count})", 'detail' => ''];
        } catch (\Throwable $e) {
            $checks[] = ['status' => 'fail', 'label' => 'MCP Server init', 'detail' => $e->getMessage()];
        }

        // Check Authorization header passthrough
        $authSeen = $this->extractBearer() !== null;
        $authSrc  = $this->authHeaderSource();
        if ($authSeen) {
            $checks[] = ['status' => 'ok', 'label' => 'Authorization header visible to PHP', 'detail' => "via {$authSrc}"];
        } else {
            $checks[] = [
                'status' => 'warn',
                'label'  => 'Authorization header not sent (expected for ?setup)',
                'detail' => "Send with: curl -s '{$base}/mcp?setup' -H 'Authorization: Bearer test'",
            ];
        }

        // Well-known path (may be blocked by host firewall — warn only)
        $checks[] = [
            'status' => 'warn',
            'label'  => '/.well-known/oauth-authorization-server',
            'detail' => 'May be blocked by host firewall — OK, clients fall back to /oauth/.well-known/openid-configuration',
        ];

        $checks[] = ['status' => 'ok', 'label' => 'MCP endpoint /mcp is reachable', 'detail' => 'PHP ' . PHP_VERSION . ' / ' . PHP_SAPI];

        echo $this->renderPartial('mcp_info.php', compact('checks', 'base'));
    }

    private function authHeaderSource(): string
    {
        if (!empty($_SERVER['HTTP_AUTHORIZATION']))          return '$_SERVER[HTTP_AUTHORIZATION]';
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return '$_SERVER[REDIRECT_HTTP_AUTHORIZATION]';
        if (function_exists('getallheaders')) {
            $h = getallheaders();
            if (!empty($h['Authorization'] ?? $h['authorization'] ?? '')) return 'getallheaders()';
        }
        return 'none';
    }

    private function baseUrl(): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
// MCP server version marker — used to confirm deployment: v4
