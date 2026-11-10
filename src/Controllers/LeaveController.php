<?php
namespace App\Controllers;

use App\Models\Leave;
use PDO;
use Exception;

class LeaveController
{
    protected $model;
    protected $currentUserId;
    protected $userRole;
    protected $userDept;

    public function __construct(PDO $pdo)
    {
        $this->model = new Leave($pdo);
        
        if (!isset($_SESSION['user_id'])) { header("Location: " . baseUrl('login')); exit; }
        
        $this->currentUserId = $_SESSION['user_id'];
        $this->userRole = $_SESSION['approval_role'] ?? 'Employee';
        $this->userDept = $_SESSION['dept_id'] ?? 0;
    }

    public function index()
    {
        $this->loadSharedData();
        
        $mySilCredits = $this->model->getCredits($this->currentUserId);
        $myAllCredits = $this->model->getAllCredits($this->currentUserId);
        $myLeaves = $this->model->getLeaveHistory($this->currentUserId);
        $consumedData = $this->model->getConsumedLeaveHistory($this->currentUserId);
        $hrLeaves = $this->isHr ? $this->model->getPendingLeaves() : [];
        
        $leaveTypes = [
            'Off',
            'Sick Leave',
            'Vacation Leave',
            'Solo Parent Leave',
            'Maternity Leave',
            'Paternity Leave',
            'Bereavement Leave'
        ];

        $allEmployeesCredits = [];
        $creditSearchTerm = trim($_GET['credit_search'] ?? '');
        $creditFilterDept = $_GET['credit_dept'] ?? '';
        $creditPage = isset($_GET['credit_page']) && is_numeric($_GET['credit_page']) ? (int)$_GET['credit_page'] : 1;
        $creditLimit = 8;
        $creditTotalRecords = 0;
        $creditTotalPages = 1;
        $creditDepts = [];

        if ($this->isHr) {
            $creditDepts = $this->model->getDepartments();
            $paginatedCredits = $this->model->getPaginatedEmployeesWithCredits($creditSearchTerm, $creditFilterDept, $creditPage, $creditLimit);
            $allEmployeesCredits = $paginatedCredits['employees'];
            $creditTotalRecords = $paginatedCredits['totalRecords'];
            $creditTotalPages = $paginatedCredits['totalPages'];
        }

        $activeTab = $_GET['tab'] ?? 'leave';
        if (isset($_GET['credit_search']) || isset($_GET['credit_dept']) || isset($_GET['credit_page'])) {
            $activeTab = 'hr-credits';
        }

        $vars = array_merge($this->sharedVars, [
            'my_sil_credits' => $mySilCredits,
            'my_all_credits' => $myAllCredits,
            'my_leaves' => $myLeaves,
            'consumed_history' => $consumedData['history'],
            'consumed_totals' => $consumedData['totals'],
            'hr_leaves' => $hrLeaves,
            'all_employees_credits' => $allEmployeesCredits,
            'creditSearchTerm' => $creditSearchTerm,
            'creditFilterDept' => $creditFilterDept,
            'creditPage' => $creditPage,
            'creditTotalRecords' => $creditTotalRecords,
            'creditTotalPages' => $creditTotalPages,
            'creditLimit' => $creditLimit,
            'creditDepts' => $creditDepts,
            'activeTab' => $activeTab,
            'leaveTypes' => $leaveTypes,
            'appeal_data' => $_SESSION['appeal_data'] ?? null,
            'show_appeal' => isset($_GET['show_appeal']),
        ]);
        
        extract($vars);
        require __DIR__ . '/../Views/leave_view.php';
    }

    public function changeSchedule()
    {
        $this->loadSharedData();
        
        $mySchedChanges = $this->model->getScheduleChangeHistory($this->currentUserId);
        $shifts = $this->model->getAllShifts();
        $hrChanges = $this->isHr ? $this->model->getPendingScheduleChanges() : [];

        $vars = array_merge($this->sharedVars, [
            'my_sched_changes' => $mySchedChanges,
            'shifts' => $shifts,
            'hr_changes' => $hrChanges
        ]);

        extract($vars);
        require __DIR__ . '/../Views/changesched_view.php';
    }

    public function overtime()
    {
        $this->loadSharedData();
        
        // Fetch active employees for the OT application dropdown
        // Priority order: user's department first, then alphabetical by department and name
        $userDeptId = (int)$this->userDept;
        $sql = "SELECT e.emp_id, e.ac_no, e.first_name, e.last_name, e.dept_id, COALESCE(d.dept_name, 'Unassigned') as dept_name 
                FROM employees e 
                LEFT JOIN departments d ON e.dept_id = d.dept_id 
                WHERE e.employee_status = 'Active'
                ORDER BY (e.dept_id = :user_dept) DESC, d.dept_name ASC, e.last_name ASC, e.first_name ASC";
        $stmt = $this->model->getPdo()->prepare($sql);
        $stmt->execute([':user_dept' => $userDeptId]);
        $deptEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Overtime history: HR/CEO see recent company OT, managers see their department & self, employees see their own
        if ($this->isHr || strcasecmp($this->userRole, 'CEO') === 0 || strcasecmp($_SESSION['role'] ?? '', 'CEO') === 0) {
            $overtimes = $this->model->getPdo()->query(
                "SELECT ot.*, e.first_name, e.last_name, d.dept_name 
                 FROM overtime_applications ot 
                 JOIN employees e ON ot.emp_id = e.emp_id 
                 LEFT JOIN departments d ON e.dept_id = d.dept_id 
                 ORDER BY ot.created_at DESC LIMIT 100"
            )->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($this->isManager) {
            $overtimes = $this->model->getOvertimeHistory($this->currentUserId, $this->userDept);
        } else {
            $overtimes = $this->model->getOvertimeHistory($this->currentUserId, null);
        }

        $hrOts = $this->isHr ? $this->model->getPendingOvertimes() : [];

        $vars = array_merge($this->sharedVars, [
            'deptEmployees' => $deptEmployees,
            'overtimes' => $overtimes,
            'hr_ots' => $hrOts,
            'currentUserId' => $this->currentUserId
        ]);

        extract($vars);
        require __DIR__ . '/../Views/overtime_view.php';
    }

    protected $isManager;
    protected $isHr;
    protected $sharedVars;

    private function loadSharedData()
    {
        $roleClean = strtolower(str_replace([' ', '_'], '', trim($this->userRole ?? '')));
        $displayRoleClean = strtolower(str_replace([' ', '_'], '', trim($_SESSION['role'] ?? '')));

        $this->isManager = in_array($roleClean, ['depthead', 'manager', 'ceo', 'admin']) 
                        || in_array($displayRoleClean, ['depthead', 'manager', 'ceo', 'admin']);

        $this->isHr = in_array($roleClean, ['hr', 'admin']) 
                   || in_array($displayRoleClean, ['hr', 'admin']) 
                   || ((int)$this->userDept === 5);

        $message = $_SESSION['flash_message'] ?? '';
        $messageType = $_SESSION['flash_type'] ?? '';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);

        $this->sharedVars = [
            'message' => $message,
            'messageType' => $messageType,
            'is_manager' => $this->isManager,
            'is_hr' => $this->isHr,
            'userRole' => $this->userRole,
            'userDept' => $this->userDept,
        ];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }
    }

    private function handlePost()
    {
        // CSRF Check
        if (!\App\Utils\AppHelpers::validateCsrfToken($_POST['csrf_token'] ?? null)) {
            $this->setFlash("Invalid CSRF token. Please refresh the page.", "error");
            header("Location: " . baseUrl('leave'));
            exit;
        }

        $action = $_POST['action'] ?? '';
        $redirect = 'leave';

        try {
            switch ($action) {
                case 'apply_leave':
                    try {
                        $this->model->applyLeave($this->currentUserId, $_POST['leave_type'], $_POST['start_date'], $_POST['end_date'], $_POST['reason']);
                    } catch (Exception $ruleEx) {
                        // Check if this is a rule violation that needs appeal
                        if (strpos($ruleEx->getMessage(), 'RULE_VIOLATION|') === 0) {
                            $parts = explode('|', $ruleEx->getMessage());
                            // parts: [0]=RULE_VIOLATION, [1]=rule code, [2]=message, [3]=violation_note
                            $_SESSION['appeal_data'] = [
                                'leave_type' => $_POST['leave_type'],
                                'start_date' => $_POST['start_date'],
                                'end_date' => $_POST['end_date'],
                                'reason' => $_POST['reason'],
                                'rule' => $parts[1] ?? '',
                                'violation_note' => $parts[3] ?? '',
                            ];
                            $this->setFlash($parts[2] ?? 'Filing requirement not met.', "warning");
                            header("Location: " . baseUrl('leave') . "?show_appeal=1");
                            exit;
                        }
                        throw $ruleEx; // Re-throw non-rule exceptions
                    }
                    \App\Services\AuditService::log(
                        \App\Services\AuditActions::LEAVE_APPLIED,
                        'leave',
                        null,
                        null,
                        ['leave_type' => $_POST['leave_type'], 'start_date' => $_POST['start_date'], 'end_date' => $_POST['end_date'], 'reason' => $_POST['reason']]
                    );
                    \App\Services\NotificationService::notifyRole(
                        'HR',
                        \App\Services\NotificationTypes::LEAVE_APPLIED,
                        'New Leave Application',
                        ($_SESSION['full_name'] ?? 'An employee') . " submitted a leave application for {$_POST['start_date']} to {$_POST['end_date']}.",
                        'leave'
                    );
                    $this->setFlash("Leave application submitted successfully!", "success");
                    break;

                case 'apply_leave_appeal':
                    $leaveType = $_POST['leave_type'] ?? '';
                    $startDate = $_POST['start_date'] ?? '';
                    $endDate = $_POST['end_date'] ?? '';
                    $reason = $_POST['reason'] ?? '';
                    $appealReason = $_POST['appeal_reason'] ?? '';
                    $violationNote = $_POST['violation_note'] ?? '';

                    if (empty($appealReason)) throw new Exception("Appeal reason is required.");

                    // Handle file upload
                    $attachmentPath = null;
                    if (isset($_FILES['appeal_attachment']) && $_FILES['appeal_attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $attachmentPath = \App\Services\FileUploadService::handleFileUpload(
                            $_FILES['appeal_attachment'],
                            'appeal_' . $this->currentUserId,
                            'leave_appeals'
                        );
                    }

                    $this->model->applyLeaveAppeal(
                        $this->currentUserId, $leaveType, $startDate, $endDate,
                        $reason, $appealReason, $attachmentPath, $violationNote
                    );

                    \App\Services\AuditService::log(
                        \App\Services\AuditActions::LEAVE_APPEAL_SUBMITTED,
                        'leave',
                        null,
                        null,
                        ['leave_type' => $leaveType, 'start_date' => $startDate, 'end_date' => $endDate, 'appeal_reason' => $appealReason, 'violation_note' => $violationNote]
                    );
                    \App\Services\NotificationService::notifyRole(
                        'HR',
                        \App\Services\NotificationTypes::LEAVE_APPEAL_SUBMITTED,
                        'Leave Appeal Submitted',
                        ($_SESSION['full_name'] ?? 'An employee') . " submitted a leave appeal for $leaveType ($startDate to $endDate). Reason: $appealReason",
                        'leave'
                    );
                    // Clear stored appeal data
                    unset($_SESSION['appeal_data']);
                    $this->setFlash("Leave appeal submitted successfully! HR will review your request.", "success");
                    break;

                case 'apply_ot':
                    $redirect = 'overtime';
                    $otDate = trim($_POST['ot_date'] ?? '');
                    $otHours = (float)($_POST['ot_hours'] ?? 0);
                    $reason = trim($_POST['reason'] ?? '');

                    if (empty($otDate)) throw new Exception("Work date is required.");
                    if ($otHours <= 0) throw new Exception("Overtime hours must be greater than zero.");
                    if ($otHours > 16) throw new Exception("Overtime hours cannot exceed 16 hours in a single day.");
                    if (empty($reason)) throw new Exception("Reason / task details are required.");

                    if ($this->isManager || $this->isHr) {
                        $emps = isset($_POST['ot_employees']) ? array_values(array_filter(array_unique((array)$_POST['ot_employees']))) : [];
                        if (empty($emps)) {
                            // If manager/HR did not pick anyone, default to self
                            $emps = [$this->currentUserId];
                        }
                    } else {
                        // Regular employee applying for themselves (or teammates if selected)
                        $emps = isset($_POST['ot_employees']) ? array_values(array_filter(array_unique((array)$_POST['ot_employees']))) : [$this->currentUserId];
                        if (empty($emps)) {
                            $emps = [$this->currentUserId];
                        }
                    }

                    $res = $this->model->applyOvertime($emps, $otDate, $otHours, $reason);

                    \App\Services\AuditService::log(
                        \App\Services\AuditActions::OVERTIME_APPLIED,
                        'overtime',
                        null,
                        null,
                        ['employees' => $emps, 'ot_date' => $otDate, 'ot_hours' => $otHours, 'reason' => $reason]
                    );

                    $applicantName = $_SESSION['full_name'] ?? 'Employee';
                    if (count($emps) === 1 && $emps[0] == $this->currentUserId) {
                        if ($res['added'] === 0 && $res['dupes'] > 0) {
                            throw new Exception("You already have a pending or approved overtime request for {$otDate}.");
                        }
                        \App\Services\NotificationService::notifyRole(
                            'HR',
                            \App\Services\NotificationTypes::OVERTIME_APPLIED,
                            'Overtime Application Submitted',
                            "{$applicantName} submitted an overtime request for {$otDate} ({$otHours} hrs).",
                            'overtime'
                        );
                        $this->setFlash("Overtime request for {$otDate} ({$otHours} hrs) submitted successfully!", "success");
                    } else {
                        \App\Services\NotificationService::notifyRole(
                            'HR',
                            \App\Services\NotificationTypes::OVERTIME_APPLIED,
                            'Overtime Application Submitted',
                            "Overtime for {$res['added']} employee(s) on {$otDate} was submitted by {$applicantName}.",
                            'overtime'
                        );
                        $msg = "Overtime submitted for {$res['added']} employee(s).";
                        if ($res['dupes'] > 0) $msg .= " Skipped {$res['dupes']} duplicate(s).";
                        $this->setFlash($msg, $res['dupes'] > 0 ? "warning" : "success");
                    }
                    break;

                case 'apply_change_sched':
                    $redirect = 'changesched';
                    $this->model->applyScheduleChange($this->currentUserId, $_POST['sched_date'], $_POST['new_shift_code'], $_POST['reason']);
                    \App\Services\NotificationService::notifyRole(
                        'HR',
                        \App\Services\NotificationTypes::SCHEDULE_SUBMITTED,
                        'Schedule Change Request',
                        ($_SESSION['full_name'] ?? 'An employee') . " requested schedule shift to {$_POST['new_shift_code']} on {$_POST['sched_date']}.",
                        'changesched'
                    );
                    $this->setFlash("Schedule change request submitted!", "success");
                    break;

                case 'update_leave_status':
                    if (!$this->isHr) throw new Exception("Unauthorized.");
                    $leaveId = (int)$_POST['leave_id'];
                    $status = $_POST['status'];
                    
                    // Fetch leave details to notify applicant (including appeal info)
                    $gStmt = $this->model->getPdo()->prepare("SELECT emp_id, leave_type, start_date, end_date, is_appeal FROM leave_applications WHERE leave_id = ?");
                    $gStmt->execute([$leaveId]);
                    $leaveRow = $gStmt->fetch(PDO::FETCH_ASSOC);

                    $this->model->updateLeaveStatus($leaveId, $status, $this->currentUserId);

                    $auditAction = ($status === 'Approved') ? \App\Services\AuditActions::LEAVE_APPROVED : \App\Services\AuditActions::LEAVE_REJECTED;
                    \App\Services\AuditService::log($auditAction, 'leave', (string)$leaveId, null, ['status' => $status, 'is_appeal' => $leaveRow['is_appeal'] ?? 0]);

                    if ($leaveRow && !empty($leaveRow['emp_id'])) {
                        $notifType = ($status === 'Approved') ? \App\Services\NotificationTypes::LEAVE_APPROVED : \App\Services\NotificationTypes::LEAVE_REJECTED;
                        $appealLabel = !empty($leaveRow['is_appeal']) ? 'Appeal ' : '';
                        \App\Services\NotificationService::notify(
                            (int)$leaveRow['emp_id'],
                            $notifType,
                            "Leave {$appealLabel}Application " . $status,
                            "Your {$leaveRow['leave_type']} {$appealLabel}({$leaveRow['start_date']} to {$leaveRow['end_date']}) has been {$status}.",
                            'leave'
                        );
                    }
                    $appealTag = (!empty($leaveRow['is_appeal'])) ? ' appeal' : '';
                    $this->setFlash("Leave{$appealTag} " . strtolower($status), "success");
                    break;

                case 'update_credits':
                    if (!$this->isHr) throw new Exception("Unauthorized: Only HR can input or modify leave credits.");
                    $targetEmpId = (int)($_POST['target_emp_id'] ?? 0);
                    if (!$targetEmpId) throw new Exception("Employee ID is required.");

                    $creditsData = [
                        'vl_credits' => (float)($_POST['vl_credits'] ?? 0),
                        'sl_credits' => (float)($_POST['sl_credits'] ?? 0),
                        'solo_parent_credits' => (float)($_POST['solo_parent_credits'] ?? 7),
                        'paternity_credits' => (float)($_POST['paternity_credits'] ?? 7),
                        'maternity_credits' => (float)($_POST['maternity_credits'] ?? 105),
                        'bereavement_immediate_credits' => (float)($_POST['bereavement_immediate_credits'] ?? 5),
                        'sil_credits' => (float)($_POST['sil_credits'] ?? 0),
                    ];

                    $this->model->updateEmployeeCredits($targetEmpId, $creditsData);

                    \App\Services\AuditService::log(
                        'LEAVE_CREDITS_UPDATED',
                        'employee_credits',
                        (string)$targetEmpId,
                        null,
                        $creditsData
                    );

                    \App\Services\NotificationService::notify(
                        $targetEmpId,
                        \App\Services\NotificationTypes::PROFILE_UPDATED_BY_HR,
                        'Leave Credits Updated',
                        'Your leave credits have been updated by Human Resources.',
                        'leave'
                    );

                    $redirect = 'leave?tab=hr-credits';
                    $this->setFlash("Leave credits updated successfully by HR.", "success");
                    break;

                case 'update_ot_status':
                    $redirect = 'overtime';
                    if (!$this->isHr) throw new Exception("Unauthorized.");
                    $otId = (int)$_POST['ot_id'];
                    $status = $_POST['status'];

                    $gStmt = $this->model->getPdo()->prepare("SELECT emp_id, ot_date, ot_hours FROM overtime_applications WHERE ot_id = ?");
                    $gStmt->execute([$otId]);
                    $otRow = $gStmt->fetch(PDO::FETCH_ASSOC);

                    $this->model->updateOvertimeStatus($otId, $status, $this->currentUserId);

                    $auditAction = ($status === 'Approved') ? \App\Services\AuditActions::OVERTIME_APPROVED : \App\Services\AuditActions::OVERTIME_REJECTED;
                    \App\Services\AuditService::log($auditAction, 'overtime', (string)$otId, null, ['status' => $status]);

                    if ($otRow && !empty($otRow['emp_id'])) {
                        $notifType = ($status === 'Approved') ? \App\Services\NotificationTypes::OVERTIME_APPROVED : \App\Services\NotificationTypes::OVERTIME_REJECTED;
                        \App\Services\NotificationService::notify(
                            (int)$otRow['emp_id'],
                            $notifType,
                            "Overtime " . $status,
                            "Your overtime request for {$otRow['ot_date']} ({$otRow['ot_hours']} hrs) has been {$status}.",
                            'overtime'
                        );
                    }
                    $this->setFlash("Overtime " . strtolower($status), "success");
                    break;

                case 'update_change_status':
                    $redirect = 'changesched';
                    if (!$this->isHr) throw new Exception("Unauthorized.");
                    $reqId = (int)$_POST['req_id'];
                    $status = $_POST['status'];

                    $gStmt = $this->model->getPdo()->prepare("SELECT emp_id, schedule_date, new_shift_code FROM schedule_change_requests WHERE id = ?");
                    $gStmt->execute([$reqId]);
                    $reqRow = $gStmt->fetch(PDO::FETCH_ASSOC);

                    $this->model->updateScheduleChangeStatus($reqId, $status, $this->currentUserId);

                    if ($reqRow && !empty($reqRow['emp_id'])) {
                        \App\Services\NotificationService::notify(
                            (int)$reqRow['emp_id'],
                            \App\Services\NotificationTypes::SCHEDULE_APPROVED,
                            "Schedule Change " . $status,
                            "Your schedule change request for {$reqRow['schedule_date']} has been {$status}.",
                            'changesched'
                        );
                    }
                    $this->setFlash("Schedule change " . strtolower($status), "success");
                    break;
            }
        } catch (Exception $e) {
            $this->setFlash("Error: " . $e->getMessage(), "error");
        }
        
        header("Location: " . baseUrl($redirect));
        exit;
    }

    private function setFlash($msg, $type)
    {
        $_SESSION['flash_message'] = $msg;
        $_SESSION['flash_type'] = $type;
    }
}
