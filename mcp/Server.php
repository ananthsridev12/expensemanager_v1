<?php

namespace Mcp;

use Config\Database;

class Server
{
    private Tools $tools;

    public function __construct(Database $database)
    {
        $this->tools = new Tools($database);
    }

    // Returns the response body string and sets HTTP status via callback.
    public function handleHttp(string $body): array
    {
        $decoded = json_decode($body, true);
        if ($decoded === null && trim($body) !== '') {
            return ['status' => 400, 'body' => $this->rpcError(null, -32700, 'Parse error')];
        }

        // Batch request (JSON array)
        if (is_array($decoded) && isset($decoded[0])) {
            $responses = [];
            foreach ($decoded as $req) {
                if (!is_array($req)) continue;
                if (!array_key_exists('id', $req)) continue; // notifications → no response
                $responses[] = $this->dispatch($req);
            }
            if (empty($responses)) {
                return ['status' => 202, 'body' => null];
            }
            return ['status' => 200, 'body' => $responses];
        }

        // Single request
        $req = is_array($decoded) ? $decoded : [];
        if (!array_key_exists('id', $req)) {
            // Notification — no response
            return ['status' => 202, 'body' => null];
        }
        return ['status' => 200, 'body' => $this->dispatch($req)];
    }

    public function handle(string $body): array
    {
        $result = $this->handleHttp($body);
        return $result['body'] ?? [];
    }

    private function dispatch(array $req): array
    {
        $id     = $req['id']     ?? null;
        $method = (string) ($req['method'] ?? '');
        $params = (array)  ($req['params'] ?? []);

        try {
            $result = match ($method) {
                'initialize'                  => $this->initialize($params),
                'notifications/initialized'   => [],
                'ping'                        => [],
                'tools/list'                  => ['tools' => $this->tools->getDefinitions()],
                'tools/call'                  => $this->toolsCall($params),
                'resources/list'              => ['resources' => []],
                'prompts/list'                => ['prompts' => []],
                default                       => throw new \RuntimeException('Method not found', -32601),
            };
            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
        } catch (\RuntimeException $e) {
            $code = $e->getCode() ?: -32603;
            return $this->rpcError($id, (int) $code, $e->getMessage());
        }
    }

    private function initialize(array $params): array
    {
        // Echo back the client's protocol version if we support it
        $supported = ['2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05'];
        $requested = (string) ($params['protocolVersion'] ?? '2024-11-05');
        $version   = in_array($requested, $supported, true) ? $requested : '2025-11-25';
        return [
            'protocolVersion' => $version,
            'capabilities'    => ['tools' => ['listChanged' => false]],
            'serverInfo'      => ['name' => 'Easi7 Finance', 'version' => '1.0'],
            'instructions'    => implode(' ', [
                'Personal finance manager for one user.',
                'Always call get_overview first to get today\'s date and a balance snapshot.',
                'Use list_accounts to resolve account names before creating transactions.',
                'Use list_categories before assigning a category_id.',
                'Amounts are in INR (₹). Never approximate — use the exact figure the user states.',
            ]),
        ];
    }

    private function toolsCall(array $params): array
    {
        $name = (string) ($params['name']      ?? '');
        $args = (array)  ($params['arguments'] ?? []);

        try {
            $data    = $this->tools->call($name, $args);
            $jsonStr = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return [
                'content'           => [['type' => 'text', 'text' => $jsonStr]],
                'structuredContent' => $data,
                'isError'           => false,
            ];
        } catch (ToolError $e) {
            return [
                'content'  => [['type' => 'text', 'text' => 'Error: ' . $e->getMessage()]],
                'isError'  => true,
            ];
        }
    }

    private function rpcError(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
