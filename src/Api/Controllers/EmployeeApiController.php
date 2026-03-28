<?php
namespace App\Api\Controllers;

use App\Models\Employee;
use PDO;

class EmployeeApiController extends BaseApiController
{
    private $employeeModel;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->employeeModel = new Employee($pdo);
    }

    /**
     * GET /api/v1/employees
     */
    public function index()
    {
        try {
            $employees = $this->employeeModel->getAll();
            return $this->jsonResponse($employees);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * GET /api/v1/employees/{id}
     */
    public function show($vars)
    {
        try {
            $id = $vars['id'];
            $employee = $this->employeeModel->find($id);
            if (!$employee) {
                return $this->sendError('Employee not found', 404);
            }
            return $this->jsonResponse($employee);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
