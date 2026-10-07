<?php

namespace Controllers;

use Models\OAuthClient;
use Models\OAuthCode;
use Models\OAuthToken;

class OAuthController extends BaseController
{
    public function handle(string $path): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // CORS pre-flight for token / register
        if ($method === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            http_response_code(204);
            return;
        }

        match (true) {
            in_array($path, ['/oauth/register', '/register'], true)
                => $this->register(),
            in_array($path, ['/oauth/authorize', '/oauth/authorize/', '/authorize'], true)
                => $this->authorize($method),
            in_array($path, ['/oauth/token', '/token'], true)
                => $this->token(),
            in_array($path, ['/oauth/protected-resource',
                              '/.well-known/oauth-protected-resource',
                              '/.well-known/oauth-protected-resource/mcp'], true)
                => $this->protectedResource(),
            in_array($path, ['/oauth/.well-known/openid-configuration',
                              '/oauth/.well-known/oauth-authorization-server',
                              '/.well-known/oauth-authorization-server',
                              '/.well-known/openid-configuration'], true)
                => $this->authServerMetadata(),
            $path === '/oauth/jwks'
                => $this->jwks(),
            default => $this->notFound($path),
        };
    }

    // ── Dynamic client registration (RFC 7591) ────────────────────────────────

    private function register(): void
    {
        $this->ensureTables();
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        header('Access-Control-Allow-Origin: *');

        $body    = (string) file_get_contents('php://input');
        $payload = json_decode($body, true) ?? [];

        $name         = trim((string) ($payload['client_name']    ?? 'MCP Client'));
        $redirectUris = (array) ($payload['redirect_uris'] ?? []);
        $authMethod   = (string) ($payload['token_endpoint_auth_method'] ?? 'client_secret_basic');

        if (empty($redirectUris)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_client_metadata', 'error_description' => 'redirect_uris required.']);
            return;
        }

        $allowed = ['none', 'client_secret_post', 'client_secret_basic'];
        if (!in_array($authMethod, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_client_metadata', 'error_description' => "token_endpoint_auth_method must be one of: none, client_secret_post, client_secret_basic."]);
            return;
        }

        $clientModel = new OAuthClient($this->database);
        $clientId    = $clientModel->register($name, $redirectUris);
        $secret      = $this->hmacClientSecret($clientId);

        http_response_code(201);
        $resp = [
            'client_id'                  => $clientId,
            'client_id_issued_at'        => time(),
            'client_name'                => $name,
            'redirect_uris'              => $redirectUris,
            'token_endpoint_auth_method' => $authMethod,
            'grant_types'                => ['authorization_code'],
            'response_types'             => ['code'],
            'code_challenge_methods_supported' => ['S256'],
            'scope'                      => 'app',
        ];
        if ($authMethod !== 'none') {
            $resp['client_secret']            = $secret;
            $resp['client_secret_expires_at'] = 0;
        }
        echo json_encode($resp);
    }

    // ── Authorization endpoint ────────────────────────────────────────────────

    private function authorize(string $method): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $clientId     = trim((string) ($_GET['client_id']             ?? ''));
        $redirectUri  = trim((string) ($_GET['redirect_uri']          ?? ''));
        $state        = trim((string) ($_GET['state']                 ?? ''));
        $challenge    = trim((string) ($_GET['code_challenge']        ?? '')) ?: null;
        $challengeMethod = trim((string) ($_GET['code_challenge_method'] ?? 'S256'));

        $clientModel = new OAuthClient($this->database);
        $client      = $clientModel->find($clientId);

        if (!$client) {
            $this->showError('Unknown client. Register your MCP client and try again.');
            return;
        }
        if (!in_array($redirectUri, $client['redirect_uris'], true)) {
            $this->showError('Redirect URI not registered for this client.');
            return;
        }

        if ($method === 'GET') {
            $csrf = bin2hex(random_bytes(16));
            $_SESSION['oauth_csrf']  = $csrf;
            $_SESSION['oauth_params'] = compact('clientId', 'redirectUri', 'state', 'challenge', 'challengeMethod');
            $this->renderAuthorize((string) $client['name'], $csrf);
            return;
        }

        // POST — process consent form
        $formCsrf = (string) ($_POST['csrf'] ?? '');
        $stored   = (string) ($_SESSION['oauth_csrf'] ?? '');
        if (!hash_equals($stored, $formCsrf)) {
            $this->showError('Session expired. Please go back and try again.');
            return;
        }

        // Restore params from session (don't trust POST)
        $params = $_SESSION['oauth_params'] ?? [];
        unset($_SESSION['oauth_csrf'], $_SESSION['oauth_params']);

        if (($_POST['action'] ?? '') === 'deny') {
            $this->oauthRedirect($params['redirectUri'], $params['state'], null, 'access_denied');
            return;
        }

        // Verify PIN if set
        $pinCfg  = require __DIR__ . '/../config/pin.php';
        $pinHash = trim((string) ($pinCfg['pin_hash'] ?? ''));
        if ($pinHash !== '') {
            $entered = trim((string) ($_POST['pin'] ?? ''));
            if ($entered === '' || !password_verify($entered, $pinHash)) {
                $csrf = bin2hex(random_bytes(16));
                $_SESSION['oauth_csrf']   = $csrf;
                $_SESSION['oauth_params'] = $params;
                $this->renderAuthorize((string) $client['name'], $csrf, 'Incorrect PIN. Try again.');
                return;
            }
        }

        // Issue authorization code
        $codeModel = new OAuthCode($this->database);
        $code      = $codeModel->create(
            $params['clientId'],
            $params['redirectUri'],
            $params['challenge'],
            $params['challengeMethod']
        );
        $this->oauthRedirect($params['redirectUri'], $params['state'], $code, null);
    }

    // ── Token endpoint ────────────────────────────────────────────────────────

    private function token(): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        header('Access-Control-Allow-Origin: *');

        $raw  = (string) file_get_contents('php://input');
        $body = [];
        $ct   = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json')) {
            $body = json_decode($raw, true) ?? [];
        } else {
            parse_str($raw, $body);
            if (empty($body)) $body = $_POST;
        }

        // Extract client credentials from Basic header if present
        $clientId = (string) ($body['client_id'] ?? '');
        $clientSecret = (string) ($body['client_secret'] ?? '');
        $auth = $_SERVER['HTTP_AUTHORIZATION']
             ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
             ?? '';
        if ($auth === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (str_starts_with($auth, 'Basic ')) {
            $decoded = base64_decode(substr($auth, 6));
            if (str_contains($decoded, ':')) {
                [$clientId, $clientSecret] = explode(':', $decoded, 2);
            }
        }

        $grantType   = (string) ($body['grant_type']   ?? '');
        $code        = (string) ($body['code']         ?? '');
        $redirectUri = (string) ($body['redirect_uri'] ?? '');
        $verifier    = ($body['code_verifier'] ?? '') !== '' ? (string) $body['code_verifier'] : null;

        if ($grantType !== 'authorization_code' || $code === '' || $clientId === '') {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_request', 'error_description' => 'grant_type, code and client_id required.']);
            return;
        }

        $clientModel = new OAuthClient($this->database);
        $client      = $clientModel->find($clientId);
        if (!$client) {
            http_response_code(401);
            echo json_encode(['error' => 'invalid_client']);
            return;
        }

        // Verify secret when provided
        if ($clientSecret !== '') {
            $expected = $this->hmacClientSecret($clientId);
            if (!hash_equals($expected, $clientSecret)) {
                http_response_code(401);
                echo json_encode(['error' => 'invalid_client', 'error_description' => 'Invalid client secret.']);
                return;
            }
        }

        $codeModel = new OAuthCode($this->database);
        if (!$codeModel->consume($code, $clientId, $redirectUri, $verifier)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_grant', 'error_description' => 'Code invalid, expired or PKCE mismatch.']);
            return;
        }

        $tokenModel = new OAuthToken($this->database);
        $token      = $tokenModel->issue($clientId, 'MCP: ' . $client['name']);

        echo json_encode([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'scope'        => 'app',
        ]);
    }

    // ── Protected resource metadata (RFC 9728) ────────────────────────────────

    private function protectedResource(): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        $base = $this->baseUrl();
        echo json_encode([
            'resource'                 => $base . '/mcp',
            'authorization_servers'    => [$base . '/oauth'],
            'bearer_methods_supported' => ['header'],
            'scopes_supported'         => ['app'],
        ]);
    }

    // ── Authorization-server metadata ─────────────────────────────────────────

    private function authServerMetadata(): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        $base = $this->baseUrl();
        echo json_encode([
            'issuer'                                => $base . '/oauth',
            'authorization_endpoint'                => $base . '/oauth/authorize',
            'token_endpoint'                        => $base . '/oauth/token',
            'registration_endpoint'                 => $base . '/oauth/register',
            'response_types_supported'              => ['code'],
            'grant_types_supported'                 => ['authorization_code'],
            'code_challenge_methods_supported'      => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
            'scopes_supported'                      => ['app'],
            'jwks_uri'                              => $base . '/oauth/jwks',
            'subject_types_supported'               => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
        ]);
    }

    // ── JWKS (no ID tokens, but OpenID parsers require the endpoint) ──────────

    private function jwks(): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        echo json_encode(['keys' => []]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function renderAuthorize(string $clientName, string $csrf, string $pinError = ''): void
    {
        $pinCfg     = require __DIR__ . '/../config/pin.php';
        $needsPin   = trim((string) ($pinCfg['pin_hash'] ?? '')) !== '';
        $clientName = htmlspecialchars($clientName, ENT_QUOTES);
        $csrf       = htmlspecialchars($csrf, ENT_QUOTES);
        $pinError   = $pinError !== '' ? '<p class="err">' . htmlspecialchars($pinError) . '</p>' : '';
        $pinField   = $needsPin
            ? '<label>PIN <input type="password" name="pin" inputmode="numeric" autocomplete="off" required autofocus></label>' . $pinError
            : '';

        // renderPartial — OAuth pages are standalone HTML, must NOT be wrapped in the app layout
        echo $this->renderPartial('oauth/authorize.php', compact('clientName', 'csrf', 'pinField', 'needsPin', 'pinError'));
    }

    private function showError(string $msg): void
    {
        echo $this->renderPartial('oauth/error.php', ['message' => $msg]);
    }

    private function oauthRedirect(string $redirectUri, string $state, ?string $code, ?string $error): void
    {
        $params = ['iss' => $this->baseUrl() . '/oauth'];
        if ($code  !== null) $params['code']  = $code;
        if ($error !== null) $params['error'] = $error;
        if ($state !== '')   $params['state'] = $state;
        header('Location: ' . $redirectUri . (str_contains($redirectUri, '?') ? '&' : '?') . http_build_query($params));
    }

    private function hmacClientSecret(string $clientId): string
    {
        $key = $this->appKey();
        return hash_hmac('sha256', 'oauth-client:' . $clientId, $key);
    }

    private function appKey(): string
    {
        // Use a stable secret from config if available, fall back to a host-derived constant.
        // On first deploy, generate: php -r "echo bin2hex(random_bytes(32));" and put in config/app_key.php
        $f = __DIR__ . '/../config/app_key.php';
        if (file_exists($f)) {
            $cfg = require $f;
            $k   = (string) ($cfg['key'] ?? '');
            if ($k !== '') return $k;
        }
        return hash('sha256', ($_SERVER['HTTP_HOST'] ?? 'localhost') . __FILE__);
    }

    private function ensureTables(): void
    {
        try {
            $pdo = $this->database->connect();
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS oauth_clients (
                    id VARCHAR(80) NOT NULL PRIMARY KEY,
                    name VARCHAR(200) NOT NULL DEFAULT 'MCP Client',
                    redirect_uris TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS oauth_codes (
                    code VARCHAR(128) NOT NULL PRIMARY KEY,
                    client_id VARCHAR(80) NOT NULL,
                    redirect_uri VARCHAR(2000) NOT NULL,
                    code_challenge VARCHAR(128) DEFAULT NULL,
                    code_challenge_method VARCHAR(10) DEFAULT 'S256',
                    expires_at DATETIME NOT NULL,
                    used TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_client_id (client_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS api_tokens (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    token_hash VARCHAR(64) NOT NULL,
                    client_id VARCHAR(80) NOT NULL,
                    label VARCHAR(200) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_used_at TIMESTAMP NULL DEFAULT NULL,
                    UNIQUE KEY unique_token (token_hash),
                    INDEX idx_client (client_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (\Throwable) {
            // Best-effort; will surface naturally if tables really don't exist
        }
    }

    private function notFound(string $path): void
    {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'not_found', 'path' => $path]);
    }

    private function baseUrl(): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
