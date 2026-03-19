<?php

namespace Models;

use Config\Database;
use PDO;

class BaseModel
{
    protected PDO $db;
    protected Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->db = $database->connect();
    }
}
