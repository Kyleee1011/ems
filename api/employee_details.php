<?php
header("Content-Type: application/json; charset=UTF-8");

require_once '../vendor/autoload.php';
require_once '../config.php';

use App\Models\Employee;

try {
    if (!isset($_GET['emp_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Employee ID is required.']);
        exit();
    }

    $emp_id = $_GET['emp_id'];
    $employeeModel = new Employee($pdo);
    $employeeData = $employeeModel->getEmployeeFullDetails($emp_id);

    if ($employeeData) {
        echo json_encode(['success' => true] + $employeeData);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee not found.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
