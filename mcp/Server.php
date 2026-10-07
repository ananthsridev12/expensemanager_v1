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

    public function handle(string $body): array
    {
        $req = json_decode($body, true);
        if (!is_array($req)) {
            return $this->rpcError(null, -32700, 'Parse error');
        }

        $id     = $req['id']     ?? null;
        $method = (string) ($req['method'] ?? '');
        $params = (array)  ($req['params'] ?? []);

        try {
            $result = match ($method) {
                'initialize'        => $this->initialize(),
                'notifications/initialized' => [], // client ACK, no response needed… but we respond OK
                'ping'              => [],
                'tools/list'        => ['tools' => $this->tools->getDefinitions()],
                'tools/call'        => $this->toolsCall($params),
                default             => throw new \RuntimeException('Method not found', -32601),
            };
            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
        } catch (\RuntimeException $e) {
            $code = $e->getCode() ?: -32603;
            return $this->rpcError($id, (int) $code, $e->getMessage());
        }
    }

    private function initialize(): array
    {
        return [
            'protocolVersion' => '2024-11-05',
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
            $data = $this->tools->call($name, $args);
            return [
                'content'  => [['type' => 'text', 'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]],
                'isError'  => false,
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
