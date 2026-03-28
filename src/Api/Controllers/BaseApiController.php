<?php
namespace App\Api\Controllers;

use PDO;

abstract class BaseApiController
{
    protected $pdo;
    protected $userId;
    protected $userRole;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        // Middleware: Check Auth
        $this->checkAuth();
    }

    protected function checkAuth()
    {
        // For Phase 1, we rely on existing Session
        if (!isset($_SESSION['user_id'])) {
            $this->sendError('Unauthorized. Please log in.', 401);
        }
        $this->userId = $_SESSION['user_id'];
        $this->userRole = $_SESSION['approval_role'] ?? 'Employee';
    }

    protected function jsonResponse($data, $code = 200)
    {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode([
            'success' => $code >= 200 && $code < 300,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    protected function sendError($message, $code = 400)
    {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    protected function getInput()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        return $input ?: $_POST;
    }
}
