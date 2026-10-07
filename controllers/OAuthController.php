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
            $path === '/oauth/register'
                => $this->register(),
            ($path === '/oauth/authorize' || $path === '/oauth/authorize/')
                => $this->authorize($method),
            $path === '/oauth/token'
                => $this->token(),
            $path === '/oauth/protected-resource'
                => $this->protectedResource(),
            ($path === '/oauth/.well-known/openid-configuration'
                || $path === '/.well-known/oauth-authorization-server')
                => $this->authServerMetadata(),
            default => $this->notFound($path),
        };
    }

    // ── Dynamic client registration (RFC 7591) ────────────────────────────────

    private function register(): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        $body    = (string) file_get_contents('php://input');
        $payload = json_decode($body, true) ?? [];

        $name         = trim((string) ($payload['client_name']    ?? 'MCP Client'));
        $redirectUris = (array) ($payload['redirect_uris'] ?? []);

        if (empty($redirectUris)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_client_metadata', 'error_description' => 'redirect_uris required.']);
            return;
        }

        $clientModel = new OAuthClient($this->database);
        $clientId    = $clientModel->register($name, $redirectUris);

        http_response_code(201);
        echo json_encode([
            'client_id'              => $clientId,
            'client_id_issued_at'    => time(),
            'client_name'            => $name,
            'redirect_uris'          => $redirectUris,
            'token_endpoint_auth_method' => 'none',
            'grant_types'            => ['authorization_code'],
            'response_types'         => ['code'],
            'code_challenge_methods' => ['S256'],
        ]);
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
        header('Access-Control-Allow-Origin: *');

        parse_str((string) file_get_contents('php://input'), $body);
        if (empty($body)) {
            $body = $_POST;
        }

        $grantType   = (string) ($body['grant_type']    ?? '');
        $code        = (string) ($body['code']          ?? '');
        $redirectUri = (string) ($body['redirect_uri']  ?? '');
        $clientId    = (string) ($body['client_id']     ?? '');
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
        ]);
    }

    // ── Protected resource metadata (RFC 9728) ────────────────────────────────

    private function protectedResource(): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        $base = $this->baseUrl();
        echo json_encode([
            'resource'                    => $base . '/mcp',
            'authorization_servers'       => [$base . '/oauth'],
            'bearer_methods_supported'    => ['header'],
            'resource_documentation'      => $base . '/mcp',
        ]);
    }

    // ── Authorization-server metadata ─────────────────────────────────────────

    private function authServerMetadata(): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        $base = $this->baseUrl();
        echo json_encode([
            'issuer'                                  => $base . '/oauth',
            'authorization_endpoint'                  => $base . '/oauth/authorize',
            'token_endpoint'                          => $base . '/oauth/token',
            'registration_endpoint'                   => $base . '/oauth/register',
            'response_types_supported'                => ['code'],
            'grant_types_supported'                   => ['authorization_code'],
            'code_challenge_methods_supported'        => ['S256'],
            'token_endpoint_auth_methods_supported'   => ['none'],
        ]);
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

        echo $this->render('oauth/authorize.php', compact('clientName', 'csrf', 'pinField', 'needsPin', 'pinError'));
    }

    private function showError(string $msg): void
    {
        echo $this->render('oauth/error.php', ['message' => $msg]);
    }

    private function oauthRedirect(string $redirectUri, string $state, ?string $code, ?string $error): void
    {
        $params = [];
        if ($code  !== null) $params['code']  = $code;
        if ($error !== null) $params['error'] = $error;
        if ($state !== '')   $params['state'] = $state;
        header('Location: ' . $redirectUri . (str_contains($redirectUri, '?') ? '&' : '?') . http_build_query($params));
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
