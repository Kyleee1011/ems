<?php
namespace App\Controllers;

use PDO;
use App\Models\AllowanceModel;

class AllowanceController
{
    protected $pdo;
    protected $model;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new AllowanceModel($pdo);
    }

    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . baseUrl('login'));
            exit;
        }

        // Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            $action = $_POST['action'] ?? '';

            try {
                switch ($action) {
                    case 'create':
                        $this->model->createAllowanceType([
                            'name' => $_POST['name'] ?? '',
                            'amount' => (float)($_POST['amount'] ?? 0),
                            'deduction_per_absent' => (float)($_POST['deduction_per_absent'] ?? 0),
                            'frequency' => $_POST['frequency'] ?? 'Semi-Monthly',
                            'start_cutoff_date' => $_POST['start_cutoff_date'] ?? null,
                        ]);
                        echo json_encode(['success' => true, 'message' => 'Allowance type created.']);
                        break;

                    case 'assign':
                        $allowanceId = (int)($_POST['allowance_id'] ?? 0);
                        $assignAll = ($_POST['assign_all'] ?? '0') === '1';
                        $startDate = $_POST['start_cutoff_date'] ?? null;
                        
                        if ($assignAll) {
                            $count = $this->model->assignToAll($allowanceId, $startDate);
                        } else {
                            $empIds = json_decode($_POST['emp_ids'] ?? '[]', true);
                            $count = $this->model->assignToEmployees($allowanceId, $empIds, $startDate);
                        }
                        echo json_encode(['success' => true, 'message' => "Assigned to $count employee(s)."]);
                        break;

                    case 'delete':
                        $this->model->deleteAllowanceType((int)($_POST['allowance_id'] ?? 0));
                        echo json_encode(['success' => true, 'message' => 'Allowance type deleted.']);
                        break;

                    case 'remove_assignment':
                        $this->model->removeEmployeeAllowance(
                            (int)($_POST['emp_id'] ?? 0),
                            (int)($_POST['allowance_id'] ?? 0)
                        );
                        echo json_encode(['success' => true, 'message' => 'Assignment removed.']);
                        break;

                    default:
                        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
                }
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }

        // GET: fetch data for view
        $allowanceTypes = $this->model->getAllAllowanceTypes();
        $employees = $this->pdo->query("SELECT emp_id, first_name, last_name, ac_no FROM employees WHERE employee_status = 'Active' ORDER BY last_name, first_name")->fetchAll(PDO::FETCH_ASSOC);

        // For each allowance type, get assigned employees
        foreach ($allowanceTypes as &$at) {
            $at['assigned'] = $this->model->getAssignedEmployees((int)$at['allowance_id']);
        }
        unset($at);

        require __DIR__ . '/../Views/allowance_view.php';
    }
}
