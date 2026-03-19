<?php

namespace Config;

use PDO;
use PDOException;

class Database
{
    private string $host = 'localhost';
    private string $dbName = 'de2shrnx_personalfin';
    private string $username = 'de2shrnx_userpersonalfin';
    private string $password = ')%ovi]Ie6kNv';
    private ?PDO $pdo = null;

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


}
