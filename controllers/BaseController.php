<?php

namespace Controllers;

use Config\Database;

class BaseController
{
    protected Database $database;

    public function __construct()
    {
        $this->database = new Database();
    }

    protected function render(string $viewPath, array $params = []): string
    {
        extract($params, EXTR_SKIP);
        ob_start();
        include __DIR__ . '/../views/' . $viewPath;
        $content = ob_get_clean();

        ob_start();
        include __DIR__ . '/../views/layout.php';
        return ob_get_clean();
    }
}
