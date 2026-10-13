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

    /**
     * Approved Accessible Department Palette
     * Meets WCAG AA contrast (>= 4.5:1) for label tab text and badges
     * Differ in lightness and hue for accessibility
     */
    public const APPROVED_PALETTE = [
        '#C2410C' => ['name' => 'Orange (HR)', 'tint' => '#FFEDD5', 'text' => '#7C2D12'],
        '#1D4ED8' => ['name' => 'Blue (MIS / Tech)', 'tint' => '#DBEAFE', 'text' => '#1E3A8A'],
        '#0F766E' => ['name' => 'Teal (Operations / Maint)', 'tint' => '#CCFBF1', 'text' => '#134E4A'],
        '#6D28D9' => ['name' => 'Violet (Finance / Acctg)', 'tint' => '#EDE9FE', 'text' => '#4C1D95'],
        '#B45309' => ['name' => 'Amber (Kitchen)', 'tint' => '#FEF3C7', 'text' => '#78350F'],
        '#047857' => ['name' => 'Emerald (Housekeeping)', 'tint' => '#D1FAE5', 'text' => '#064E3B'],
        '#BE123C' => ['name' => 'Rose (Food & Beverage)', 'tint' => '#FFE4E6', 'text' => '#881337'],
        '#4338CA' => ['name' => 'Indigo (Front Office)', 'tint' => '#E0E7FF', 'text' => '#312E81'],
        '#0369A1' => ['name' => 'Sky (Sales & Marketing)', 'tint' => '#E0F2FE', 'text' => '#0C4A6E'],
        '#A21CAF' => ['name' => 'Fuchsia (DJ / Ent)', 'tint' => '#FAE8FF', 'text' => '#701A75'],
        '#374151' => ['name' => 'Slate', 'tint' => '#F3F4F6', 'text' => '#111827'],
        '#0F172A' => ['name' => 'Dark Ink (Executive)', 'tint' => '#F8FAFC', 'text' => '#020617'],
        '#475569' => ['name' => 'Neutral Gray', 'tint' => '#F1F5F9', 'text' => '#0F172A'],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Resolves palette metadata (color, tint, text, name) for a given hex color
     */
    public static function getPaletteInfo(?string $hex): array
    {
        $hex = strtoupper(trim((string)$hex));
        if ($hex && isset(self::APPROVED_PALETTE[$hex])) {
            return array_merge(['color' => $hex], self::APPROVED_PALETTE[$hex]);
        }
        // Default to Teal
        return array_merge(['color' => '#0F766E'], self::APPROVED_PALETTE['#0F766E']);
    }

    /**
     * Main view for Organization Chart (Supports full company and per-department views)
     */
    public function index(array $vars = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . baseUrl('login'));
            exit;
        }

        $userRole = $_SESSION['approval_role'] ?? 'Employee';
        $userDeptId = (int)($_SESSION['dept_id'] ?? 0);
        $canEditAll = (strcasecmp($userRole, 'HR') === 0 || strcasecmp($userRole, 'CEO') === 0);
        $isDeptHead = (strcasecmp($userRole, 'DeptHead') === 0 || strcasecmp($userRole, 'Dept Head') === 0);
        $canEditColors = $canEditAll; // Only HR and CEO can change department colors

        // Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'update_color') {
                $this->updateColor();
                return;
            }
            $this->handlePost($userRole, $userDeptId);
            return;
        }

        // Determine if scoped to a specific department
        $selectedDeptId = isset($vars['dept_id']) ? (int)$vars['dept_id'] : (isset($_GET['dept']) ? (int)$_GET['dept'] : 0);

        // Auto-heal hierarchy if needed
        self::autoSyncHierarchy($this->pdo);

        // Fetch full chart data
        $chartData = $this->fetchChartData();
        $employees = $chartData['employees'];
        $departments = $chartData['departments'];

        // If specific department is requested, validate department exists
        $departmentNotFound = false;
        $activeDepartment = null;

        if ($selectedDeptId > 0) {
            foreach ($departments as $d) {
                if ((int)$d['dept_id'] === $selectedDeptId) {
                    $activeDepartment = $d;
                    break;
                }
            }
            if (!$activeDepartment) {
                $departmentNotFound = true;
            }
        }

        // If JSON format is requested (API consumer)
        if (isset($_GET['format']) && $_GET['format'] === 'json') {
            header('Content-Type: application/json');
            if ($departmentNotFound) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Department not found']);
                exit;
            }
            if ($selectedDeptId > 0) {
                echo json_encode([
                    'success' => true,
                    'department' => $activeDepartment,
                    'employees' => array_values(array_filter($employees, fn($e) => (int)$e['dept_id'] === $selectedDeptId))
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'departments' => $departments,
                    'employees' => $employees
                ]);
            }
            exit;
        }

        $chartDataJson = json_encode($employees, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $deptsJson = json_encode($departments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $paletteJson = json_encode(self::APPROVED_PALETTE, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        require BASE_PATH . '/src/Views/orgchart_view.php';
    }

    /**
     * Dedicated method for department route: /orgchart/department/{dept_id}
     */
    public function departmentView(array $vars): void
    {
        $this->index($vars);
    }

    /**
     * API: Get full tree data
     */
    public function apiTree(): void
    {
        header('Content-Type: application/json');
        self::autoSyncHierarchy($this->pdo);
        $data = $this->fetchChartData();
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    /**
     * API: Get department subtree
     */
    public function apiDepartment(array $vars): void
    {
        header('Content-Type: application/json');
        $deptId = (int)($vars['dept_id'] ?? 0);
        $data = $this->fetchChartData();
        
        $dept = null;
        foreach ($data['departments'] as $d) {
            if ((int)$d['dept_id'] === $deptId) {
                $dept = $d;
                break;
            }
        }

        if (!$dept) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Department not found']);
            exit;
        }

        $deptEmps = array_values(array_filter($data['employees'], fn($e) => (int)$e['dept_id'] === $deptId));

        echo json_encode([
            'success' => true,
            'department' => $dept,
            'employees' => $deptEmps
        ]);
        exit;
    }

    /**
     * Updates department color (HR / CEO only)
     * Validates against approved palette and logs audit trail
     */
    public function updateColor(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        header('Content-Type: application/json');

        $userRole = $_SESSION['approval_role'] ?? 'Employee';
        $canEditAll = (strcasecmp($userRole, 'HR') === 0 || strcasecmp($userRole, 'CEO') === 0);

        if (!$canEditAll) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden: Only HR and Executive Leadership can modify department colors.']);
            exit;
        }

        if (!AppHelpers::validateCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh.']);
            exit;
        }

        $deptId = (int)($_POST['dept_id'] ?? 0);
        $newColor = strtoupper(trim((string)($_POST['color'] ?? '')));

        if ($deptId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid department ID']);
            exit;
        }

        // Validate color in approved palette
        if (!array_key_exists($newColor, self::APPROVED_PALETTE)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid color. Color must be selected from the approved accessible palette.',
                'approved_palette' => array_keys(self::APPROVED_PALETTE)
            ]);
            exit;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT dept_name, color FROM departments WHERE dept_id = ?");
            $stmt->execute([$deptId]);
            $dept = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dept) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Department not found']);
                exit;
            }

            $oldColor = $dept['color'] ?? '#0F766E';

            $upd = $this->pdo->prepare("UPDATE departments SET color = ? WHERE dept_id = ?");
            $upd->execute([$newColor, $deptId]);

            // Audit Log
            AuditService::log(
                AuditActions::DEPARTMENT_COLOR_UPDATED,
                'department',
                (string)$deptId,
                ['color' => $oldColor, 'dept_name' => $dept['dept_name']],
                ['color' => $newColor, 'dept_name' => $dept['dept_name']]
            );

            $paletteInfo = self::APPROVED_PALETTE[$newColor];

            echo json_encode([
                'success' => true,
                'message' => "Color updated for {$dept['dept_name']} successfully.",
                'dept_id' => $deptId,
                'color' => $newColor,
                'tint' => $paletteInfo['tint'],
                'text' => $paletteInfo['text'],
                'name' => $paletteInfo['name']
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Handles supervisor reassignment with server-side role validation
     * HR/CEO can edit all departments
     * DeptHead can edit ONLY their own department
     */
    private function handlePost(string $userRole, int $userDeptId): void
    {
        header('Content-Type: application/json');

        $canEditAll = (strcasecmp($userRole, 'HR') === 0 || strcasecmp($userRole, 'CEO') === 0);
        $isDeptHead = (strcasecmp($userRole, 'DeptHead') === 0 || strcasecmp($userRole, 'Dept Head') === 0);

        if (!$canEditAll && !$isDeptHead) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Only HR, CEO, and Department Heads can edit reporting lines.']);
            exit;
        }

        if (!AppHelpers::validateCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
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

        // Fetch employee being modified
        $empStmt = $this->pdo->prepare("SELECT emp_id, dept_id, approval_role, full_name, supervisor_id FROM employees WHERE emp_id = ?");
        $empStmt->execute([$empId]);
        $targetEmp = $empStmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetEmp) {
            echo json_encode(['success' => false, 'message' => 'Employee not found.']);
            exit;
        }

        // Server-side enforcement for Department Heads:
        // Must belong to their own department
        if (!$canEditAll && $isDeptHead) {
            if ((int)$targetEmp['dept_id'] !== $userDeptId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden: Department Heads can only edit reporting lines within their own department.']);
                exit;
            }

            // Also verify that the new supervisor is within the department or is CEO
            if ($newSupervisorId !== null) {
                $newSupStmt = $this->pdo->prepare("SELECT dept_id, approval_role FROM employees WHERE emp_id = ?");
                $newSupStmt->execute([$newSupervisorId]);
                $newSup = $newSupStmt->fetch(PDO::FETCH_ASSOC);

                if ($newSup && (int)$newSup['dept_id'] !== $userDeptId && $newSup['approval_role'] !== 'CEO') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Forbidden: Department Heads can only assign supervisors within their own department.']);
                    exit;
                }
            }
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
     * Fetches all active employees and departments with palette colors
     */
    public function fetchChartData(): array
    {
        // 1. Fetch departments
        $deptStmt = $this->pdo->query("SELECT dept_id, dept_name, dept_code, color FROM departments ORDER BY dept_name ASC");
        $rawDepts = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

        $departments = [];
        foreach ($rawDepts as $d) {
            $pInfo = self::getPaletteInfo($d['color'] ?? null);
            $departments[] = [
                'dept_id' => (int)$d['dept_id'],
                'dept_name' => $d['dept_name'],
                'dept_code' => $d['dept_code'],
                'color' => $pInfo['color'],
                'tint' => $pInfo['tint'],
                'text' => $pInfo['text'],
                'color_name' => $pInfo['name']
            ];
        }

        // 2. Fetch active employees
        $sql = "SELECT e.emp_id, e.ac_no, e.first_name, e.last_name, e.full_name, 
                       e.job_title, e.job_level, e.supervisor_id, e.profile_picture, 
                       e.approval_role, d.dept_name, d.dept_id, d.color as dept_color
                FROM employees e
                LEFT JOIN departments d ON e.dept_id = d.dept_id
                WHERE e.IsActive = 1 AND e.employee_status = 'Active'
                ORDER BY d.dept_name, e.last_name";

        $empStmt = $this->pdo->query($sql);
        $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate direct report counts
        $reportCounts = [];
        foreach ($employees as $e) {
            $supId = $e['supervisor_id'];
            if ($supId) {
                $reportCounts[$supId] = ($reportCounts[$supId] ?? 0) + 1;
            }
        }

        foreach ($employees as &$e) {
            $e['emp_id'] = (int)$e['emp_id'];
            $e['supervisor_id'] = $e['supervisor_id'] !== null ? (int)$e['supervisor_id'] : null;
            $e['dept_id'] = $e['dept_id'] !== null ? (int)$e['dept_id'] : 0;
            $e['direct_reports'] = $reportCounts[$e['emp_id']] ?? 0;
            if (empty($e['full_name'])) {
                $e['full_name'] = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
            }

            // Normalization for employees without a department
            if (empty($e['dept_name']) || $e['dept_id'] === 0) {
                $e['dept_id'] = 0;
                $e['dept_name'] = 'Unassigned';
                $e['dept_color'] = '#475569';
            } else {
                $pInfo = self::getPaletteInfo($e['dept_color'] ?? null);
                $e['dept_color'] = $pInfo['color'];
            }
        }
        unset($e);

        return [
            'departments' => $departments,
            'employees' => $employees
        ];
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

    /**
     * Automatically synchronizes and repairs organizational hierarchy:
     * 1. CEO is the root (supervisor_id = null)
     * 2. All Department Heads report directly to the CEO
     * 3. Regular employees report to a Department Head of their department
     *    - If a department has multiple heads, maintains assignment or assigns to a valid head
     *    - If a department has no heads, reports to CEO
     * 4. Inactive or deleted supervisors are re-linked so no active employee is orphaned
     */
    public static function autoSyncHierarchy(PDO $pdo): void
    {
        try {
            // Find CEO / Owner
            $ceoStmt = $pdo->query("SELECT emp_id FROM employees WHERE IsActive = 1 AND employee_status = 'Active' AND (approval_role = 'CEO' OR dept_id = 10) ORDER BY emp_id ASC LIMIT 1");
            $ceoId = (int)$ceoStmt->fetchColumn();
            if (!$ceoId) {
                $ceoId = 68;
            }

            // Fetch all active employees
            $stmt = $pdo->query("SELECT emp_id, approval_role, dept_id, supervisor_id 
                                 FROM employees 
                                 WHERE IsActive = 1 AND employee_status = 'Active' 
                                 ORDER BY dept_id ASC, emp_id ASC");
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($employees)) {
                return;
            }

            $activeIds = array_column($employees, 'emp_id');

            // Group DeptHeads by dept_id
            $deptHeads = [];
            foreach ($employees as $e) {
                if (in_array($e['approval_role'], ['DeptHead', 'Dept Head'])) {
                    $deptHeads[$e['dept_id']][] = (int)$e['emp_id'];
                }
            }

            $updateStmt = $pdo->prepare("UPDATE employees SET supervisor_id = ? WHERE emp_id = ?");

            foreach ($employees as $e) {
                $empId = (int)$e['emp_id'];
                $currentSup = $e['supervisor_id'] !== null ? (int)$e['supervisor_id'] : null;
                $targetSup = null;

                if ($empId === $ceoId || $e['approval_role'] === 'CEO') {
                    $targetSup = null;
                } elseif (in_array($e['approval_role'], ['DeptHead', 'Dept Head'])) {
                    // All Department Heads report to the CEO / Owner
                    $targetSup = $ceoId;
                } elseif ($e['dept_id'] == 5) {
                    // HR Department
                    $targetSup = ($empId == 41) ? $ceoId : 41;
                } else {
                    $deptId = $e['dept_id'];
                    $heads = $deptHeads[$deptId] ?? [];

                    if (empty($heads)) {
                        // Department with no DeptHead reports directly to CEO
                        $targetSup = $ceoId;
                    } elseif (count($heads) === 1) {
                        $targetSup = $heads[0];
                    } else {
                        // Multiple heads in a single department:
                        // Keep current head if valid and belongs to the department, otherwise assign to first head
                        if ($currentSup !== null && in_array($currentSup, $heads)) {
                            $targetSup = $currentSup;
                        } else {
                            $targetSup = $heads[0];
                        }
                    }
                }

                $isCurrentSupActive = $currentSup !== null && in_array($currentSup, $activeIds);
                if ($currentSup !== $targetSup || ($currentSup !== null && !$isCurrentSupActive)) {
                    $updateStmt->execute([$targetSup, $empId]);
                }
            }
        } catch (\Throwable $t) {
            error_log('Error in OrgChartController::autoSyncHierarchy: ' . $t->getMessage());
        }
    }
}
