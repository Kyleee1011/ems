<?php
namespace App\Controllers;

use PDO;
use Exception;
use App\Services\AuditService;
use App\Services\AuditActions;
use App\Services\NotificationService;
use App\Services\NotificationTypes;
use App\Utils\AppHelpers;

class OrgChartController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . baseUrl('login'));
            exit;
        }

        $userRole = $_SESSION['approval_role'] ?? 'Employee';
        $canEdit = (strcasecmp($userRole, 'HR') === 0 || strcasecmp($userRole, 'CEO') === 0);

        // Handle POST update (HR / CEO only)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost($canEdit);
            return;
        }

        // Fetch all active employees
        $sql = "SELECT e.emp_id, e.ac_no, e.first_name, e.last_name, e.full_name, 
                       e.job_title, e.job_level, e.supervisor_id, e.profile_picture, 
                       e.approval_role, d.dept_name, d.dept_id
                FROM employees e
                LEFT JOIN departments d ON e.dept_id = d.dept_id
                WHERE e.IsActive = 1 AND e.employee_status = 'Active'
                ORDER BY d.dept_name, e.last_name";
        
        $stmt = $this->pdo->query($sql);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate direct report counts
        $reportCounts = [];
        foreach ($employees as $e) {
            $supId = $e['supervisor_id'];
            if ($supId) {
                $reportCounts[$supId] = ($reportCounts[$supId] ?? 0) + 1;
            }
        }

        foreach ($employees as &$e) {
            $e['direct_reports'] = $reportCounts[$e['emp_id']] ?? 0;
            if (empty($e['full_name'])) {
                $e['full_name'] = $e['first_name'] . ' ' . $e['last_name'];
            }
        }
        unset($e);

        // Separate tree roots (CEO or supervisor_id = null) and potential unassigned
        $chartDataJson = json_encode($employees, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        require BASE_PATH . '/src/Views/orgchart_view.php';
    }

    private function handlePost(bool $canEdit): void
    {
        header('Content-Type: application/json');

        if (!$canEdit) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        if (!AppHelpers::validateCsrfToken($_POST['csrf_token'] ?? null)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh.']);
            exit;
        }

        $empId = (int)($_POST['emp_id'] ?? 0);
        $newSupervisorId = !empty($_POST['supervisor_id']) ? (int)$_POST['supervisor_id'] : null;

        if ($empId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
            exit;
        }

        if ($empId === $newSupervisorId) {
            echo json_encode(['success' => false, 'message' => 'An employee cannot be their own supervisor.']);
            exit;
        }

        // Circular loop check: ensure new supervisor is not a descendant of empId
        if ($newSupervisorId !== null && $this->isDescendant($empId, $newSupervisorId)) {
            echo json_encode(['success' => false, 'message' => 'Circular reporting detected. You cannot assign a subordinate as a supervisor.']);
            exit;
        }

        try {
            // Get previous supervisor for audit diff
            $prevStmt = $this->pdo->prepare("SELECT supervisor_id, full_name, ac_no FROM employees WHERE emp_id = ?");
            $prevStmt->execute([$empId]);
            $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);

            // Update supervisor
            $updStmt = $this->pdo->prepare("UPDATE employees SET supervisor_id = ? WHERE emp_id = ?");
            $updStmt->execute([$newSupervisorId, $empId]);

            // Audit Log
            AuditService::log(
                AuditActions::ORG_CHART_UPDATED,
                'employee',
                (string)$empId,
                ['supervisor_id' => $prev['supervisor_id'] ?? null],
                ['supervisor_id' => $newSupervisorId]
            );

            // Notify Employee
            if ($newSupervisorId !== null) {
                $supNameStmt = $this->pdo->prepare("SELECT full_name FROM employees WHERE emp_id = ?");
                $supNameStmt->execute([$newSupervisorId]);
                $supName = $supNameStmt->fetchColumn() ?: 'New Manager';

                NotificationService::notify(
                    $empId,
                    NotificationTypes::ORG_CHART_CHANGED,
                    'Reporting Line Updated',
                    "Your reporting supervisor has been updated to {$supName}.",
                    'orgchart'
                );
            }

            echo json_encode(['success' => true, 'message' => 'Reporting supervisor updated successfully.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Checks if $targetId is a descendant of $ancestorId
     */
    private function isDescendant(int $ancestorId, int $targetId): bool
    {
        $current = $targetId;
        $visited = [];

        while ($current !== null) {
            if ($current === $ancestorId) {
                return true;
            }
            if (isset($visited[$current])) {
                break; // avoid infinite loop
            }
            $visited[$current] = true;

            $stmt = $this->pdo->prepare("SELECT supervisor_id FROM employees WHERE emp_id = ?");
            $stmt->execute([$current]);
            $current = $stmt->fetchColumn();
            $current = $current !== false && $current !== null ? (int)$current : null;
        }

        return false;
    }
}
