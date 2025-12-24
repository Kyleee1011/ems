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

    public function __construct(PDO $emsPdo, PDO $schedulerPdo)
    {
        $this->model = new Leave($emsPdo, $schedulerPdo);
        
        if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
        
        $this->currentUserId = $_SESSION['user_id'];
        $this->userRole = $_SESSION['approval_role'] ?? 'Employee';
        $this->userDept = $_SESSION['dept_id'] ?? 0;
    }

    public function index()
    {
        $isManager = ($this->userRole === 'DeptHead' || $this->userRole === 'Manager' || $this->userRole === 'CEO');
        $isHr = ($this->userRole === 'HR');

        $message = $_SESSION['flash_message'] ?? '';
        $messageType = $_SESSION['flash_type'] ?? '';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']); // Clear flash

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost($isManager, $isHr);
        }

        // Fetch Data for View
        $mySilCredits = $this->model->getCredits($this->currentUserId);
        $myLeaves = $this->model->getLeaveHistory($this->currentUserId);
        $mySchedChanges = $this->model->getScheduleChangeHistory($this->currentUserId);
        
        $shifts = $this->model->getAllShifts();
        $deptEmployees = $isManager ? $this->model->getEmployeesByDept($this->userDept) : [];
        $overtimes = $this->model->getOvertimeHistory($isManager ? null : $this->currentUserId, $isManager ? $this->userDept : null);

        $hrLeaves = $isHr ? $this->model->getPendingLeaves() : [];
        $hrOts = $isHr ? $this->model->getPendingOvertimes() : [];
        $hrChanges = $isHr ? $this->model->getPendingScheduleChanges() : [];
        
        $leaveTypes = [
            'Service Incentive Leave', 'Vacation Leave', 'Sick Leave', 'Emergency Leave',
            'Maternity/Paternity', 'Bereavement', 'DAYOFF', 'FLEX', 'HOLIDAY OFF', 'Leave Without Pay', 'Leave With Pay'
        ];

        extract([
            'message' => $message,
            'messageType' => $messageType,
            'is_manager' => $isManager,
            'is_hr' => $isHr,
            'my_sil_credits' => $mySilCredits,
            'my_leaves' => $myLeaves,
            'my_sched_changes' => $mySchedChanges,
            'shifts' => $shifts,
            'deptEmployees' => $deptEmployees,
            'overtimes' => $overtimes,
            'hr_leaves' => $hrLeaves,
            'hr_ots' => $hrOts,
            'hr_changes' => $hrChanges,
            'leaveTypes' => $leaveTypes
        ]);

        require __DIR__ . '/../Views/leave_view.php';
    }

    private function handlePost($isManager, $isHr)
    {
        $action = $_POST['action'] ?? '';
        try {
            switch ($action) {
                case 'apply_leave':
                    $this->model->applyLeave($this->currentUserId, $_POST['leave_type'], $_POST['start_date'], $_POST['end_date'], $_POST['reason']);
                    $this->setFlash("Leave application submitted successfully!", "success");
                    break;

                case 'apply_ot':
                    if (!$isManager) throw new Exception("Unauthorized.");
                    $emps = isset($_POST['ot_employees']) ? array_unique($_POST['ot_employees']) : [];
                    if (empty($emps)) throw new Exception("Select employees.");
                    
                    $res = $this->model->applyOvertime($emps, $_POST['ot_date'], $_POST['ot_hours'], $_POST['reason']);
                    $msg = "Overtime submitted for {$res['added']} employee(s).";
                    if ($res['dupes'] > 0) $msg .= " Skipped {$res['dupes']} duplicates.";
                    $this->setFlash($msg, $res['dupes'] > 0 ? "warning" : "success");
                    break;

                case 'apply_change_sched':
                    $this->model->applyScheduleChange($this->currentUserId, $_POST['sched_date'], $_POST['new_shift_code'], $_POST['reason']);
                    $this->setFlash("Schedule change request submitted!", "success");
                    break;

                case 'update_leave_status':
                    if (!$isHr) throw new Exception("Unauthorized.");
                    $this->model->updateLeaveStatus($_POST['leave_id'], $_POST['status'], $this->currentUserId);
                    $this->setFlash("Leave " . strtolower($_POST['status']), "success");
                    break;

                case 'update_ot_status':
                    if (!$isHr) throw new Exception("Unauthorized.");
                    $this->model->updateOvertimeStatus($_POST['ot_id'], $_POST['status'], $this->currentUserId);
                    $this->setFlash("Overtime " . strtolower($_POST['status']), "success");
                    break;

                case 'update_change_status':
                    if (!$isHr) throw new Exception("Unauthorized.");
                    $this->model->updateScheduleChangeStatus($_POST['req_id'], $_POST['status'], $this->currentUserId);
                    $this->setFlash("Schedule change " . strtolower($_POST['status']), "success");
                    break;
            }
        } catch (Exception $e) {
            $this->setFlash("Error: " . $e->getMessage(), "error");
        }
        
        header("Location: leave.php");
        exit;
    }

    private function setFlash($msg, $type)
    {
        $_SESSION['flash_message'] = $msg;
        $_SESSION['flash_type'] = $type;
    }
}
