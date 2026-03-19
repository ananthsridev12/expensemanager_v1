<?php

namespace Config;

use PDO;
use PDOException;

class Database
{
    private string $host = 'localhost';
    private string $dbName = 'expensemanager';
    private string $username = 'root';
    private string $password = '';
    private ?PDO $pdo = null;

    public function __construct()
    {
        $this->applyOverride();
    }

    public function connect(): PDO
    {
        if ($this->pdo === null) {
            $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset=utf8mb4";
            try {
                $this->pdo = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $exception) {
                die('Database connection failed: ' . $exception->getMessage());
            }
        }

        return $this->pdo;
    }

    private function applyOverride(): void
    {
        $overridePath = __DIR__ . '/database.override.php';
        if (!file_exists($overridePath)) {
            return;
        }

        $override = require $overridePath;
        if (!is_array($override)) {
            return;
        }

        $host = trim((string) ($override['host'] ?? ''));
        $dbName = trim((string) ($override['dbName'] ?? ''));
        $username = trim((string) ($override['username'] ?? ''));
        $password = (string) ($override['password'] ?? '');

        if ($host !== '') {
            $this->host = $host;
        }
        if ($dbName !== '') {
            $this->dbName = $dbName;
        }
        if ($username !== '') {
            $this->username = $username;
        }
        $this->password = $password;
    }
}
