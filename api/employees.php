<?php
header("Content-Type: application/json; charset=UTF-8");

require_once '../vendor/autoload.php';
require_once '../config.php';

use App\Models\Employee;

try {
    $employeeModel = new Employee($pdo);
    $employees = $employeeModel->getAll();

    echo json_encode($employees);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
