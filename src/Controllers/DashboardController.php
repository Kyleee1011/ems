<?php
namespace App\Controllers;

use PDO;
use DateTime;
use Exception;
use App\Models\DashboardModel;
use App\Models\PayrollConfigModel;
use App\Models\ScheduleBatchModel;
use App\Models\LeaveModel; // Assuming this is for dashboard summaries
use App\Models\OvertimeModel;

use App\Utils\AppHelpers;

class DashboardController
{
    protected $emsPdo;
    protected $schedulerPdo;
    protected $dashboardModel;
    protected $payrollConfigModel;
    protected $scheduleBatchModel;
    protected $leaveModel;
    protected $overtimeModel;

    public function __construct(PDO $emsPdo, PDO $schedulerPdo)
    {
        $this->emsPdo = $emsPdo;
        $this->schedulerPdo = $schedulerPdo;
        $this->dashboardModel = new DashboardModel($emsPdo, $schedulerPdo);
        $this->payrollConfigModel = new PayrollConfigModel($schedulerPdo);
        $this->scheduleBatchModel = new ScheduleBatchModel($schedulerPdo);
        $this->leaveModel = new LeaveModel($emsPdo);
        $this->overtimeModel = new OvertimeModel($emsPdo);
    }

    public function index()
    {
        session_start();

        // User Context
        $current_user_id = $_SESSION['user_id'] ?? 1; 
        $current_fullname = $_SESSION['full_name'] ?? 'System Admin';
        $user_role = trim($_SESSION['approval_role'] ?? 'HR'); 
        $user_dept = $_SESSION['dept_id'] ?? 0;

        $is_head = (strcasecmp($user_role, 'DeptHead') === 0);
        $is_hr   = (strcasecmp($user_role, 'HR') === 0);
        $is_ceo  = (strcasecmp($user_role, 'CEO') === 0);

        // Handle POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePostRequest();
        }

        // --- FETCH DATA (VIEW) ---
        $totalEmp = $this->dashboardModel->getTotalEmployees();
        $newHires = $this->dashboardModel->getNewHires();
        $avgSalary = $this->dashboardModel->getAvgSalary();
        $maleCount = $this->dashboardModel->getMaleEmployeeCount();
        $femaleCount = $this->dashboardModel->getFemaleEmployeeCount();
        
        $totalGender = $maleCount + $femaleCount;
        $malePercent = ($totalGender > 0) ? round(($maleCount / $totalGender) * 100) : 0;
        $femalePercent = ($totalGender > 0) ? round(($femaleCount / $totalGender) * 100) : 0;

        $pendingLeavesCount = $this->leaveModel->getPendingLeaveCount();
        $pendingOTCount = $this->overtimeModel->getPendingOTCount(); // Assuming this moves to an OT model later
        $pendingSchedCount = $this->scheduleBatchModel->getPendingScheduleCount();
        $totalPending = $pendingLeavesCount + $pendingOTCount + $pendingSchedCount;

        $deptStats = $this->dashboardModel->getDepartmentStats();
        $deptLabels = json_encode(array_column($deptStats, 'dept_name'));
        $deptCounts = json_encode(array_column($deptStats, 'count'));

        $statusStats = $this->dashboardModel->getStatusStats();
        $statusLabels = json_encode(array_column($statusStats, 'employment_status'));
        $statusCounts = json_encode(array_column($statusStats, 'count'));

        $pendingLeaveList = $this->leaveModel->getPendingLeaveList();
        $pendingOTList = $this->overtimeModel->getPendingOTList(); // Assuming this moves to an OT model later

        // Payroll Config Data
        $sssData = $this->payrollConfigModel->getSssData();
        $phData = $this->payrollConfigModel->getPhilHealthData();
        $piData = $this->payrollConfigModel->getPagIbigData();
        $taxData = $this->payrollConfigModel->getTaxData();
        $otRules = $this->payrollConfigModel->getOvertimeRules();
        $holRules = $this->payrollConfigModel->getHolidayRules();
        $genSettings = $this->payrollConfigModel->getGeneralSettings();
        $allowanceTypes = $this->payrollConfigModel->getAllowanceTypes();
        
        // Dropdowns
        $depts = $this->dashboardModel->getDepartments();
        $cutoff_options = AppHelpers::generateCutoffPeriods(); // This is a helper function, move to model/utility
        $today = date('Y-m-d'); $default_cutoff = $cutoff_options[3]['value'];
        foreach($cutoff_options as $opt) { $d=explode('|',$opt['value']); if($today>=$d[0]&&$today<=$d[1]){$default_cutoff=$opt['value']; break;} }

        $activeTab = $_GET['tab'] ?? 'dashboard';
        $activePaySub = $_GET['sub'] ?? 'sss';

        // Render the view
        include 'partials/_header.php'; // Include the header HTML
        include 'src/Views/dashboard_view.php'; // The main dashboard view

        // Since the current dashboard.php outputs a full HTML page, we'll keep the closing tags here.
        echo '</body></html>';
    }

    private function handlePostRequest()
    {
        // User Context
        $current_user_id = $_SESSION['user_id'] ?? 1; 
        $current_fullname = $_SESSION['full_name'] ?? 'System Admin';
        $user_role = trim($_SESSION['approval_role'] ?? 'HR'); 

        $is_hr   = (strcasecmp($user_role, 'HR') === 0);
        $is_ceo  = (strcasecmp($user_role, 'CEO') === 0);

        if (isset($_POST['action'])) {
            try {
                switch ($_POST['action']) {
                    case 'process_leave':
                        $this->leaveModel->processLeaveApplication($_POST['leave_id'], $_POST['decision'], $current_user_id);
                        header("Location: dashboard.php?tab=approvals"); exit;
                    case 'process_ot':
                        $this->overtimeModel->processOvertimeApplication($_POST['ot_id'], $_POST['decision'], $current_user_id);
                        header("Location: dashboard.php?tab=approvals"); exit;
                    case 'update_sss':
                        $this->payrollConfigModel->updateSssTable($_POST['sss']);
                        header("Location: dashboard.php?tab=payroll&sub=sss"); exit;
                    case 'update_philhealth':
                        $this->payrollConfigModel->updatePhilHealthTable($_POST['ph']);
                        header("Location: dashboard.php?tab=payroll&sub=philhealth"); exit;
                    case 'update_pagibig':
                        $this->payrollConfigModel->updatePagIbigTable($_POST['pi']);
                        header("Location: dashboard.php?tab=payroll&sub=pagibig"); exit;
                    case 'update_tax':
                        $this->payrollConfigModel->updateTaxTable($_POST['tax']);
                        header("Location: dashboard.php?tab=payroll&sub=tax"); exit;
                    case 'update_ot':
                        $this->payrollConfigModel->updateOvertimeRules($_POST);
                        header("Location: dashboard.php?tab=payroll&sub=ot"); exit;
                    case 'update_holiday':
                        $this->payrollConfigModel->updateHolidayRules($_POST);
                        header("Location: dashboard.php?tab=payroll&sub=holiday"); exit;
                    case 'update_general':
                        $this->payrollConfigModel->updateGeneralSettings($_POST['settings']);
                        header("Location: dashboard.php?tab=payroll&sub=general"); exit;
                    case 'update_allowance_type':
                        $this->payrollConfigModel->addAllowanceType($_POST['name'], $_POST['amount'], $_POST['deduction'], $_POST['frequency']);
                        header("Location: dashboard.php?tab=payroll&sub=allowances"); exit;
                    case 'assign_allowance':
                        $this->payrollConfigModel->assignAllowanceToAllActiveEmployees($_POST['allowance_id'], $this->emsPdo);
                        header("Location: dashboard.php?tab=payroll&sub=allowances"); exit;
                    case 'get_employees_by_dept':
                        $emps = $this->dashboardModel->getActiveEmployeesByDept($_POST['dept_id']);
                        echo json_encode(['success' => true, 'employees' => $emps]); exit;
                    case 'get_schedules':
                        $dept_id = $_POST['dept_id'];
                        $range = $_POST['range']; 
                        list($dS, $dE) = explode('|', $range);
                        
                        $batch = $this->scheduleBatchModel->getBatchStatus($dept_id, $dS);
                        $status = $batch ? $batch['status'] : 'Not Started';
                        $batch_id = $batch ? $batch['batch_id'] : 0;

                        $can_approve_hr = ($is_hr && $status == 'Pending HR');
                        $can_approve_ceo = ($is_ceo && $status == 'Pending CEO');

                        $schedules = [];
                        
                        if ($status === 'Approved') {
                            $sqlSched = "SELECT e.emp_id as employee_id, fs.schedule_Date, fs.Shift_code, fs.Time_In, fs.Time_Out 
                                         FROM FinalizedSchedule fs
                                         JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON fs.Ac_no = e.ac_no
                                         WHERE e.dept_id = ? AND fs.schedule_Date BETWEEN ? AND ?";
                             $stmtSched = $this->schedulerPdo->prepare($sqlSched);
                             $stmtSched->execute([$dept_id, $dS, $dE]);
                        } elseif ($batch_id) {
                            $sqlSched = "SELECT es.employee_id, es.schedule_date, es.shift_code, st.time_in, st.time_out 
                                         FROM schedules es
                                         LEFT JOIN shift_types st ON es.shift_code = st.shift_code
                                         WHERE es.batch_id = ? AND es.schedule_date BETWEEN ? AND ?";
                            $stmtSched = $this->schedulerPdo->prepare($sqlSched);
                            $stmtSched->execute([$batch_id, $dS, $dE]);
                        }

                        if (isset($stmtSched)) {
                            while ($row = $stmtSched->fetch(PDO::FETCH_ASSOC)) {
                                $date = is_object($row['schedule_date'] ?? $row['schedule_Date']) ? ($row['schedule_date'] ?? $row['schedule_Date'])->format('Y-m-d') : ($row['schedule_date'] ?? $row['schedule_Date']);
                                $tInObj = $row['Time_In'] ?? $row['time_in'] ?? null;
                                $tOutObj = $row['Time_Out'] ?? $row['time_out'] ?? null;
                                $code = $row['Shift_code'] ?? $row['shift_code'] ?? '-';
                                
                                $tIn = ($tInObj instanceof DateTime) ? $tInObj->format('H:i') : (string)$tInObj;
                                $tOut = ($tOutObj instanceof DateTime) ? $tOutObj->format('H:i') : (string)$tOutObj;
                                $displayTime = ($tIn && $tOut) ? substr($tIn,0,5) . '-' . substr($tOut,0,5) : '-';
                                if(in_array($code, ['OFF','FLEX','HOLIDAY OFF','LWOP','LWP'])) $displayTime = $code;

                                $schedules[$row['employee_id']][$date] = ['code' => $code, 'time' => $displayTime];
                            }
                        }

                        echo json_encode([
                            'success' => true, 'status' => $status, 
                            'can_approve_hr' => $can_approve_hr, 'can_approve_ceo' => $can_approve_ceo, 'schedules' => $schedules
                        ]); exit;
                    case 'update_status':
                        $dept_id = $_POST['dept_id']; $range = $_POST['range']; list($start, $end) = explode('|', $range);
                        $new_status = $_POST['status']; 
                        
                        $row = $this->scheduleBatchModel->getBatchStatus($dept_id, $start);

                        if ($row) {
                            $batch_id = $row['batch_id'];
                            $this->scheduleBatchModel->updateBatchStatus($batch_id, $new_status, $current_fullname, $user_role);

                            if ($new_status === 'Approved') {
                                $this->scheduleBatchModel->deleteFinalizedSchedule($batch_id, $start, $end);
                                $this->scheduleBatchModel->insertFinalizedSchedule($batch_id);
                            }
                        }
                        echo json_encode(['success' => true]); exit;
                    case 'get_pending_batches':
                        $target = $is_hr ? 'Pending HR' : ($is_ceo ? 'Pending CEO' : '');
                        $pending = $this->scheduleBatchModel->getPendingBatches($target);
                        echo json_encode(['success'=>true, 'data'=>$pending]); exit;
                    default:
                        // Handle unknown action or show error
                        break;
                }
            } catch (\Exception $e) {
                // Handle exceptions, perhaps log them and return a generic error
                // For now, re-throwing for visibility during development
                throw $e;
            }
        }
        // Redirect to prevent form resubmission on refresh
        header("Location: dashboard.php"); exit;
    }
}