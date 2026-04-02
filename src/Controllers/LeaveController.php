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
        $myLeaves = $this->model->getLeaveHistory($this->currentUserId);
        $consumedData = $this->model->getConsumedLeaveHistory($this->currentUserId);
        $hrLeaves = $this->isHr ? $this->model->getPendingLeaves() : [];
        
        $leaveTypes = [
            'Service Incentive Leave', 'Vacation Leave', 'Sick Leave', 'Emergency Leave',
            'Maternity/Paternity', 'Bereavement', 'DAYOFF', 'FLEX', 'HOLIDAY OFF', 'Leave Without Pay', 'Leave With Pay'
        ];

        $vars = array_merge($this->sharedVars, [
            'my_sil_credits' => $mySilCredits,
            'my_leaves' => $myLeaves,
            'consumed_history' => $consumedData['history'],
            'consumed_totals' => $consumedData['totals'],
            'hr_leaves' => $hrLeaves,
            'leaveTypes' => $leaveTypes
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
        
        $deptEmployees = $this->isManager ? $this->model->getEmployeesByDept($this->userDept) : [];
        $overtimes = $this->model->getOvertimeHistory($this->isManager ? null : $this->currentUserId, $this->isManager ? $this->userDept : null);
        $hrOts = $this->isHr ? $this->model->getPendingOvertimes() : [];

        $vars = array_merge($this->sharedVars, [
            'deptEmployees' => $deptEmployees,
            'overtimes' => $overtimes,
            'hr_ots' => $hrOts
        ]);

        extract($vars);
        require __DIR__ . '/../Views/overtime_view.php';
    }

    protected $isManager;
    protected $isHr;
    protected $sharedVars;

    private function loadSharedData()
    {
        $this->isManager = ($this->userRole === 'DeptHead' || $this->userRole === 'Manager' || $this->userRole === 'CEO');
        $this->isHr = ($this->userRole === 'HR');

        $message = $_SESSION['flash_message'] ?? '';
        $messageType = $_SESSION['flash_type'] ?? '';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);

        $this->sharedVars = [
            'message' => $message,
            'messageType' => $messageType,
            'is_manager' => $this->isManager,
            'is_hr' => $this->isHr
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
                    $this->model->applyLeave($this->currentUserId, $_POST['leave_type'], $_POST['start_date'], $_POST['end_date'], $_POST['reason']);
                    $this->setFlash("Leave application submitted successfully!", "success");
                    break;

                case 'apply_ot':
                    $redirect = 'overtime';
                    if (!$this->isManager) throw new Exception("Unauthorized.");
                    $emps = isset($_POST['ot_employees']) ? array_unique($_POST['ot_employees']) : [];
                    if (empty($emps)) throw new Exception("Select employees.");
                    
                    $res = $this->model->applyOvertime($emps, $_POST['ot_date'], $_POST['ot_hours'], $_POST['reason']);
                    $msg = "Overtime submitted for {$res['added']} employee(s).";
                    if ($res['dupes'] > 0) $msg .= " Skipped {$res['dupes']} duplicates.";
                    $this->setFlash($msg, $res['dupes'] > 0 ? "warning" : "success");
                    break;

                case 'apply_change_sched':
                    $redirect = 'changesched';
                    $this->model->applyScheduleChange($this->currentUserId, $_POST['sched_date'], $_POST['new_shift_code'], $_POST['reason']);
                    $this->setFlash("Schedule change request submitted!", "success");
                    break;

                case 'update_leave_status':
                    if (!$this->isHr) throw new Exception("Unauthorized.");
                    $this->model->updateLeaveStatus($_POST['leave_id'], $_POST['status'], $this->currentUserId);
                    $this->setFlash("Leave " . strtolower($_POST['status']), "success");
                    break;

                case 'update_ot_status':
                    $redirect = 'overtime';
                    if (!$this->isHr) throw new Exception("Unauthorized.");
                    $this->model->updateOvertimeStatus($_POST['ot_id'], $_POST['status'], $this->currentUserId);
                    $this->setFlash("Overtime " . strtolower($_POST['status']), "success");
                    break;

                case 'update_change_status':
                    $redirect = 'changesched';
                    if (!$this->isHr) throw new Exception("Unauthorized.");
                    $this->model->updateScheduleChangeStatus($_POST['req_id'], $_POST['status'], $this->currentUserId);
                    $this->setFlash("Schedule change " . strtolower($_POST['status']), "success");
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
