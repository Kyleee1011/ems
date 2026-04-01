<?php
namespace App\Controllers;

use PDO;
use DateTime;
use Exception;
use App\Models\DashboardModel;
use App\Models\PayrollConfigModel;
use App\Models\ScheduleBatchModel;
use App\Models\LeaveModel;
use App\Models\OvertimeModel;
use App\Models\AttendanceModel;
use App\Models\Timecard;

use App\Utils\AppHelpers;

class DashboardController
{
    protected $pdo;
    protected $dashboardModel;
    protected $payrollConfigModel;
    protected $scheduleBatchModel;
    protected $leaveModel;
    protected $overtimeModel;
    protected $attendanceModel;
    protected $timecardModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        $this->dashboardModel = new DashboardModel($this->pdo);
        $this->payrollConfigModel = new PayrollConfigModel($this->pdo);
        $this->scheduleBatchModel = new ScheduleBatchModel($this->pdo);
        $this->leaveModel = new LeaveModel($this->pdo);
        $this->overtimeModel = new OvertimeModel($this->pdo);
        $this->attendanceModel = new AttendanceModel($this->pdo);
        $this->timecardModel = new Timecard($this->pdo);
    }

    public function index()
    {
        // User Context
        $user_role = trim($_SESSION['approval_role'] ?? 'Employee'); 
        $user_dept = $_SESSION['dept_id'] ?? 0;
        $current_fullname = $_SESSION['full_name'] ?? 'User';

        // --- ROLE & DEPARTMENT ACCESS CONTROL ---
        // Allow access if role is 'HR' OR dept_id is 5 (Human Resources)
        if (strcasecmp($user_role, 'HR') !== 0 && $user_dept != 5) {
            // Not HR role and not in HR Department, send to correct location
            if (strcasecmp($user_role, 'CEO') === 0) {
                header("Location: " . baseUrl('ceodashboard')); exit;
            } else {
                header("Location: " . baseUrl('home')); exit;
            }
        }

        $is_hr = true;

        // --- FETCH DATA (VIEW) ---
        $totalEmp = $this->dashboardModel->getTotalEmployees();
        $manpowerStats = $this->dashboardModel->getManpowerStats();
        $avgSalary = $this->dashboardModel->getAvgSalary();
        $maleCount = $this->dashboardModel->getMaleEmployeeCount();
        $femaleCount = $this->dashboardModel->getFemaleEmployeeCount();
        $otherCount = $this->dashboardModel->getUnassignedGenderCount();
        
        $totalGender = $maleCount + $femaleCount + $otherCount;
        $malePercent = ($totalGender > 0) ? round(($maleCount / $totalGender) * 100) : 0;
        $femalePercent = ($totalGender > 0) ? round(($femaleCount / $totalGender) * 100) : 0;
        $otherPercent = ($totalGender > 0) ? round(($otherCount / $totalGender) * 100) : 0;

        $pendingLeavesCount = $this->leaveModel->getPendingLeaveCount();
        $pendingOTCount = $this->overtimeModel->getPendingOTCount();
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM employee_loans WHERE status IN ('Pending HR', 'Pending CEO')");
        $stmt->execute();
        $pendingLoansCount = $stmt->fetchColumn(); 
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM schedule_change_requests WHERE status = 'Pending'");
        $stmt->execute();
        $pendingChangeSched = $stmt->fetchColumn();

        $pendingSchedBatches = $this->scheduleBatchModel->getPendingBatches('Pending HR');
        $pendingSchedCount = count($pendingSchedBatches);
        
        $totalPending = $pendingLeavesCount + $pendingOTCount + $pendingLoansCount + $pendingChangeSched + $pendingSchedCount;

        $deptStats = $this->dashboardModel->getDepartmentStats();
        $deptLabels = json_encode(array_column($deptStats, 'dept_name'));
        $deptCounts = json_encode(array_column($deptStats, 'count'));

        $statusStats = $this->dashboardModel->getStatusStats();
        $statusLabels = json_encode(array_column($statusStats, 'employment_status'));
        $statusCounts = json_encode(array_column($statusStats, 'count'));

        $pendingLeaveList = $this->leaveModel->getPendingLeaveList();
        $pendingOTList = $this->overtimeModel->getPendingOTList();
        $pendingChangeSchedList = $this->leaveModel->getPendingScheduleChanges();

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
        // 2. Fetch Data (Analytics)
        
        // Determine Current Cutoff Range
        // Determine Current Cutoff Range
        $cutoff_options = AppHelpers::generateCutoffPeriods($this->pdo);
        $today = date('Y-m-d'); 
        
        // Default to current date logic
        $default_cutoff = $cutoff_options[0]['value'] ?? '';
        foreach($cutoff_options as $opt) { 
            $d=explode('|',$opt['value']); 
            if($today>=$d[0]&&$today<=$d[1]){
                $default_cutoff=$opt['value']; 
                break;
            } 
        }
        
        // Override with User Selection
        if (isset($_GET['cutoff']) && !empty($_GET['cutoff'])) {
            $default_cutoff = $_GET['cutoff'];
        }
        
        $currentWait = explode('|', $default_cutoff);
        $cStart = $currentWait[0]; $cEnd = $currentWait[1];
        
        // Format for View (e.g., "Jan 08 - Jan 22")
        $analytics_range_label = date('M d', strtotime($cStart)) . ' - ' . date('M d', strtotime($cEnd));

        // A-C & Tallies: Use Timecard model — EXACT same logic as the timecard view
        // This guarantees: late = timecard late, absent = timecard absent
        $attendanceSummary = $this->timecardModel->getAttendanceSummary($cStart, $cEnd);
        $lateEmployees   = $attendanceSummary['late'];
        $lateTallyList   = $attendanceSummary['late_tally'];
        $absentTallyList = $attendanceSummary['absent_tally'];

        // Derive highest absent dept from tally
        $absentByDept = [];
        foreach ($attendanceSummary['absent_tally'] as $row) {
            $absentByDept[$row['department']] = ($absentByDept[$row['department']] ?? 0) + $row['absent_count'];
        }
        arsort($absentByDept);
        $topAbsentDept = array_key_first($absentByDept);
        $highestAbsentDept = $topAbsentDept ? ['dept_name' => $topAbsentDept, 'absent_count' => $absentByDept[$topAbsentDept]] : null;

        // Derive highest late dept from tally
        $lateByDept = [];
        foreach ($attendanceSummary['late_tally'] as $row) {
            $lateByDept[$row['department']] = ($lateByDept[$row['department']] ?? 0) + $row['late_count'];
        }
        arsort($lateByDept);
        $topLateDept = array_key_first($lateByDept);
        $highestLateDept = $topLateDept ? ['dept_name' => $topLateDept, 'late_count' => $lateByDept[$topLateDept]] : null;

        // D. Employees on Leave (Current Cutoff)
        $sqlLeaves = "SELECT e.first_name, e.last_name, d.dept_name, l.leave_type, l.start_date, l.end_date, l.status 
                      FROM leave_applications l 
                      JOIN employees e ON l.emp_id = e.emp_id 
                      LEFT JOIN departments d ON e.dept_id = d.dept_id 
                      WHERE l.status = 'Approved' 
                      AND ((l.start_date BETWEEN ? AND ?) OR (l.end_date BETWEEN ? AND ?)) 
                      ORDER BY l.start_date DESC";
        $stmtLeaves = $this->pdo->prepare($sqlLeaves);
        $stmtLeaves->execute([$cStart, $cEnd, $cStart, $cEnd]);
        $employeesOnLeave = $stmtLeaves->fetchAll(PDO::FETCH_ASSOC);


        $activeTab = $_GET['tab'] ?? 'dashboard';
        $activePaySub = $_GET['sub'] ?? 'sss';

        // Fetch holidays list
        $holYear = $_GET['hol_year'] ?? date('Y');
        $stmtHol = $this->pdo->prepare(
            "SELECT id, holiday_date, holiday_name, holiday_type
             FROM scheduler_holidays
             WHERE YEAR(holiday_date) = ?
             ORDER BY holiday_date ASC"
        );
        $stmtHol->execute([$holYear]);
        $holidaysList = $stmtHol->fetchAll(PDO::FETCH_ASSOC);

        // Render the view
        $this->render('dashboard_view', compact(
            'totalEmp', 'avgSalary', 'manpowerStats', 'malePercent', 'femalePercent', 'otherPercent',
            'totalPending', 'pendingLeavesCount', 'pendingOTCount', 'pendingLoansCount', 'pendingChangeSched',
            'deptLabels', 'deptCounts', 'statusLabels', 'statusCounts', 'pendingLeaveList',
            'pendingOTList', 'pendingChangeSchedList', 'sssData', 'phData', 'piData', 'taxData', 'otRules',
            'holRules', 'genSettings', 'allowanceTypes', 'depts', 'cutoff_options', 'default_cutoff',
            'activeTab', 'activePaySub', 'current_fullname', 'user_role',
            'highestAbsentDept', 'lateEmployees', 'highestLateDept', 'employeesOnLeave', 'analytics_range_label',
            'maleCount', 'femaleCount', 'otherCount', 'holidaysList',
            'lateTallyList', 'absentTallyList'
        ));
    }

    public function handlePost()
    {
        // User Context
        $current_user_id = $_SESSION['user_id'] ?? 1; 
        $current_fullname = $_SESSION['full_name'] ?? 'System Admin';
        $user_role = trim($_SESSION['approval_role'] ?? 'HR'); 
        $user_dept = $_SESSION['dept_id'] ?? 0;

        $is_hr   = (strcasecmp($user_role, 'HR') === 0 || $user_dept == 5);
        $is_ceo  = (strcasecmp($user_role, 'CEO') === 0);

        if (isset($_POST['action'])) {
            try {
                switch ($_POST['action']) {
                    case 'update_cutoff_settings':
                        $this->payrollConfigModel->updateGeneralSettings($_POST['cutoff']);
                        header("Location: " . baseUrl('dashboard?tab=payroll&sub=cutoff&success=1')); exit;
                    case 'update_general':
                        $this->payrollConfigModel->updateGeneralSettings($_POST['settings']);
                        header("Location: " . baseUrl('dashboard?tab=payroll&sub=general&success=1')); exit;
                    case 'process_leave':
                        $this->leaveModel->processLeaveApplication($_POST['leave_id'], $_POST['decision'], $current_user_id);
                        header("Location: " . baseUrl('dashboard?tab=approvals')); exit;
                    case 'process_ot':
                        $this->overtimeModel->processOvertimeApplication($_POST['ot_id'], $_POST['decision'], $current_user_id);
                        header("Location: " . baseUrl('dashboard?tab=approvals')); exit;
                    case 'process_change_sched':
                        $this->leaveModel->updateChangeScheduleStatus($_POST['req_id'], $_POST['decision'], $current_user_id);
                        header("Location: " . baseUrl('dashboard?tab=approvals')); exit;
                    case 'process_schedule':
                        $batch_id = $_POST['batch_id'] ?? 0;
                        $decision = $_POST['decision'] ?? '';
                        $new_status = '';
                        if ($decision === 'approve') {
                            $new_status = $is_hr ? 'Pending CEO' : 'Approved';
                        } else {
                            $new_status = 'Rejected';
                        }
                        
                        try {
                            $this->pdo->beginTransaction();
                            
                            $this->scheduleBatchModel->updateBatchStatus($batch_id, $new_status, $current_fullname, $user_role);
                            
                            if ($new_status === 'Approved') {
                                // If CEO approved, finalize the schedule
                                $this->scheduleBatchModel->insertFinalizedSchedule($batch_id);
                            }

                            $this->pdo->commit();
                        } catch (\Exception $e) {
                            if ($this->pdo->inTransaction()) {
                                $this->pdo->rollBack();
                            }
                            throw $e;
                        }

                        $redirect = $is_hr ? 'dashboard' : 'ceodashboard';
                        header("Location: " . baseUrl($redirect . '?tab=approvals&success=1')); exit;
                    
                    // --- PAYROLL CONFIG HANDLERS ---
                    case 'update_sss':
                        $this->payrollConfigModel->updateSssTable($_POST['sss']);
                        header("Location: " . baseUrl('dashboard?tab=payroll&sub=sss&success=1')); exit;
                    
                    case 'update_philhealth':
                        $phData = [[
                            'id' => $_POST['ph']['id'],
                            'min' => $_POST['ph']['min'],
                            'max' => $_POST['ph']['max'],
                            'rate' => $_POST['ph']['rate']
                        ]];
                        $this->payrollConfigModel->updatePhilHealthTable($phData);
                        header("Location: " . baseUrl('dashboard?tab=payroll&sub=philhealth&success=1')); exit;
                    
                    case 'update_pagibig':
                        $piData = [[
                            'id' => $_POST['pi']['id'],
                            'fixed' => $_POST['pi']['amount']
                        ]];
                        $this->payrollConfigModel->updatePagIbigTable($piData);
                        header("Location: " . baseUrl('dashboard?tab=payroll&sub=pagibig&success=1')); exit;
                    
                    case 'update_tax':
                        $this->payrollConfigModel->updateTaxTable($_POST['tax']);
                        header("Location: " . baseUrl('dashboard?tab=payroll&sub=tax&success=1')); exit;
                    
                    case 'update_ot':
                        $this->payrollConfigModel->updateOvertimeRules($_POST);
                        header("Location: " . baseUrl('dashboard?tab=payroll&sub=ot&success=1')); exit;
                    
                    case 'update_holiday':
                        $this->payrollConfigModel->updateHolidayRules($_POST);
                        header("Location: " . baseUrl('dashboard?tab=payroll&sub=holiday&success=1')); exit;
                    
                    case 'update_allowance_type':
                        $this->payrollConfigModel->addAllowanceType(
                            $_POST['name'], 
                            $_POST['amount'], 
                            $_POST['deduction'], 
                            $_POST['frequency'],
                            $_POST['start_cutoff_date'] ?? null
                        );
                        header("Location: " . baseUrl('dashboard?tab=payroll&sub=allowances&success=1')); exit;
                    
                    case 'assign_allowance':
                        $this->payrollConfigModel->assignAllowanceToAllActiveEmployees($_POST['allowance_id'], $_POST['start_date'] ?? null);
                        header("Location: " . baseUrl('dashboard?tab=payroll&sub=allowances&success=1')); exit;

                    case 'delete_allowance':
                        $this->payrollConfigModel->deleteAllowanceType((int)$_POST['allowance_id']);
                        header("Location: " . baseUrl('dashboard?tab=payroll&sub=allowances&success=1')); exit;

                    // --- HOLIDAY CALENDAR HANDLERS ---
                    case 'add_holiday':
                        $holiday_name = trim($_POST['holiday_name'] ?? '');
                        $holiday_date = trim($_POST['holiday_date'] ?? '');
                        $holiday_type = trim($_POST['holiday_type'] ?? 'REGULAR');
                        
                        if (!empty($holiday_name) && !empty($holiday_date)) {
                            try {
                                $this->pdo->beginTransaction();
                                // Pointing to scheduler_holidays table
                                $stmt = $this->pdo->prepare("INSERT INTO scheduler_holidays (holiday_date, holiday_type, holiday_name) VALUES (?, ?, ?)");
                                $stmt->execute([$holiday_date, $holiday_type, $holiday_name]);
                                $this->pdo->commit();
                            } catch (\Exception $e) {
                                if ($this->pdo->inTransaction()) {
                                    $this->pdo->rollBack();
                                }
                                throw $e;
                            }
                        }
                        header("Location: " . baseUrl('dashboard?tab=payroll&sub=holidays&success=1')); exit;
                    
                    case 'delete_holiday':
                        $holiday_id = (int)($_POST['holiday_id'] ?? 0);
                        if ($holiday_id > 0) {
                            // Pointing to scheduler_holidays table (using 'id' column)
                            $stmt = $this->pdo->prepare("DELETE FROM scheduler_holidays WHERE id = ?");
                            $stmt->execute([$holiday_id]);
                        }
                        header("Location: " . baseUrl('dashboard?tab=payroll&sub=holidays&success=1')); exit;

                    case 'get_employees_by_dept':
                        $emps = $this->dashboardModel->getActiveEmployeesByDept($_POST['dept_id']);
                        echo json_encode(['success' => true, 'employees' => $emps]); exit;
                    
                    default:
                        // Redirect for actions that don't return JSON
                        header("Location: " . baseUrl('dashboard')); exit;
                }
            } catch (\Exception $e) {
                // Log the error
                error_log($e->getMessage());
                // Redirect with an error message
                header("Location: " . baseUrl('dashboard?error=1')); exit;
            }
        }
        // Default redirect if no action is set
        header("Location: " . baseUrl('dashboard')); exit;
    }
    
    public function ceoIndex()
    {
        $user_role = $_SESSION['approval_role'] ?? '';
        
        if (strcasecmp($user_role, 'CEO') !== 0) {
            // If not CEO, check if they belong in HR Portal
            $user_dept = $_SESSION['dept_id'] ?? 0;
            if (strcasecmp($user_role, 'HR') === 0 || $user_dept == 5) {
                header("Location: " . baseUrl('dashboard')); exit;
            } else {
                header("Location: " . baseUrl('home')); exit;
            }
        }

        // ... (data fetching logic remains the same) ...
        $counts = ['sched' => 0, 'ot' => 0, 'change' => 0, 'leave' => 0, 'loan' => 0];
        $pendingBatches = $this->scheduleBatchModel->getPendingBatches('Pending CEO');
        $counts['sched'] = count($pendingBatches);
        $counts['ot'] = $this->overtimeModel->getPendingOTCount();
        $counts['leave'] = $this->leaveModel->getPendingLeaveCount();
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM employee_loans WHERE status = 'Pending CEO'");
        $stmt->execute();
        $counts['loan'] = $stmt->fetchColumn();
        $totalPending = array_sum($counts);
        $activeTab = $_GET['tab'] ?? 'dashboard';
        
        $cutoff_options = \App\Utils\AppHelpers::generateCutoffPeriods($this->pdo);

        $totalEmp = $this->dashboardModel->getTotalEmployees();
        $manpowerStats = $this->dashboardModel->getManpowerStats();
        $deptStats = $this->dashboardModel->getDepartmentStats();
        $deptLabels = json_encode(array_column($deptStats, 'dept_name'));
        $deptCounts = json_encode(array_column($deptStats, 'count'));

        $approvalData = [];
        if ($activeTab === 'schedule') {
            $approvalData = $pendingBatches;
        } elseif ($activeTab === 'overtime') {
            $approvalData = $this->overtimeModel->getPendingOTList();
        } elseif ($activeTab === 'leave') {
            $approvalData = $this->leaveModel->getPendingLeaveList();
        }
        
        // 2. Fetch Data (Analytics)
        
        // Determine Current Cutoff Range
        $cutoff_options = \App\Utils\AppHelpers::generateCutoffPeriods($this->pdo);
        $today = date('Y-m-d'); 
        
        // Default to current date logic
        $default_cutoff = $cutoff_options[0]['value'] ?? '';
        foreach($cutoff_options as $opt) { 
            $d=explode('|',$opt['value']); 
            if($today>=$d[0]&&$today<=$d[1]) {
                $default_cutoff=$opt['value']; 
                break;
            } 
        }
        
        // Override with User Selection
        if (isset($_GET['cutoff']) && !empty($_GET['cutoff'])) {
            $default_cutoff = $_GET['cutoff'];
        }
        
        $currentWait = explode('|', $default_cutoff);
        $cStart = $currentWait[0]; $cEnd = $currentWait[1];
        
        // Format for View (e.g., "Jan 08 - Jan 22")
        $analytics_range_label = date('M d', strtotime($cStart)) . ' - ' . date('M d', strtotime($cEnd));

        // A-C & Tallies: Use Timecard model (same logic as timecard view)
        $attendanceSummary = $this->timecardModel->getAttendanceSummary($cStart, $cEnd);
        $lateEmployees   = $attendanceSummary['late'];

        // Derive highest absent dept
        $absentByDept = [];
        foreach ($attendanceSummary['absent_tally'] as $row) {
            $absentByDept[$row['department']] = ($absentByDept[$row['department']] ?? 0) + $row['absent_count'];
        }
        arsort($absentByDept);
        $topAbsentDept = array_key_first($absentByDept);
        $highestAbsentDept = $topAbsentDept ? ['dept_name' => $topAbsentDept, 'absent_count' => $absentByDept[$topAbsentDept]] : null;

        // Derive highest late dept
        $lateByDept = [];
        foreach ($attendanceSummary['late_tally'] as $row) {
            $lateByDept[$row['department']] = ($lateByDept[$row['department']] ?? 0) + $row['late_count'];
        }
        arsort($lateByDept);
        $topLateDept = array_key_first($lateByDept);
        $highestLateDept = $topLateDept ? ['dept_name' => $topLateDept, 'late_count' => $lateByDept[$topLateDept]] : null;

        // D. Leaves
        $sqlLeaves = "SELECT e.first_name, e.last_name, d.dept_name, l.leave_type, l.start_date, l.end_date, l.status 
                      FROM leave_applications l 
                      JOIN employees e ON l.emp_id = e.emp_id 
                      LEFT JOIN departments d ON e.dept_id = d.dept_id 
                      WHERE l.status = 'Approved' 
                      AND ((l.start_date BETWEEN ? AND ?) OR (l.end_date BETWEEN ? AND ?)) 
                      ORDER BY l.start_date DESC";
        $stmtLeaves = $this->pdo->prepare($sqlLeaves);
        $stmtLeaves->execute([$cStart, $cEnd, $cStart, $cEnd]);
        $employeesOnLeave = $stmtLeaves->fetchAll(PDO::FETCH_ASSOC);

        $this->render('ceodashboard_view', compact(
            'counts', 'totalPending', 'activeTab', 'totalEmp', 'manpowerStats', 'deptLabels', 
            'deptCounts', 'approvalData', 'depts', 'cutoff_options',
            'highestAbsentDept', 'lateEmployees', 'highestLateDept', 'employeesOnLeave', 'analytics_range_label'
        ));
    }
    
    protected function render(string $view, array $data = [])
    {
        extract($data);
        
        ob_start();
        require BASE_PATH . "/src/Views/{$view}.php";
        $content = ob_get_clean();
        echo $content;
    }

    public function analytics()
    {
        // 1. Access Control (HR & CEO)
        $user_role = trim($_SESSION['approval_role'] ?? 'Employee');
        $user_dept = $_SESSION['dept_id'] ?? 0;
        $is_ceo = strcasecmp($user_role, 'CEO') === 0;
        $is_hr = strcasecmp($user_role, 'HR') === 0 || $user_dept == 5;

        if (!$is_hr && !$is_ceo) {
            header("Location: " . baseUrl('home')); exit;
        }

        // 2. Fetch Data
        
        // A. Department with Highest Absenteeism (Based on finalized_schedule vs biometric_logs)
        // Logic: Schedule exists for past date, but NO check-in.
        $sqlAbsent = "
            SELECT 
                fs.department as dept_name, 
                COUNT(*) as absent_count 
            FROM finalized_schedule fs
            WHERE fs.schedule_date < CURRENT_DATE 
            AND fs.schedule_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) -- Last 30 days
            AND NOT EXISTS (
                SELECT 1 
                FROM biometric_logs.userinfo u 
                JOIN biometric_logs.checkinout c ON u.USERID = c.USERID 
                WHERE u.BADGENUMBER = fs.ac_no 
                AND DATE(c.CHECKTIME) = fs.schedule_date
            )
            GROUP BY fs.department 
            ORDER BY absent_count DESC 
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sqlAbsent);
        $stmt->execute();
        $highestAbsentDept = $stmt->fetch(PDO::FETCH_ASSOC);

        // B. Real-time Late Employees (Weekly) -> For Carousel
        // Logic: Schedule exists, Check-in exists, Check-in Time > Schedule Time In
        $startOfWeek = date('Y-m-d', strtotime('last monday'));
        $endOfWeek = date('Y-m-d', strtotime('next sunday'));
        
        $sqlLate = "
            SELECT 
                fs.name, 
                fs.department, 
                DATE_FORMAT(fs.schedule_date, '%a, %b %d') as date_str,
                TIME_FORMAT(fs.time_in, '%h:%i %p') as scheduled_in,
                TIME_FORMAT(c.CHECKTIME, '%h:%i %p') as actual_in,
                TIMESTAMPDIFF(MINUTE, fs.time_in, TIME(c.CHECKTIME)) as minutes_late
            FROM finalized_schedule fs
            JOIN biometric_logs.userinfo u ON fs.ac_no = u.BADGENUMBER
            JOIN biometric_logs.checkinout c ON u.USERID = c.USERID
            WHERE fs.schedule_date BETWEEN :start AND :end
            AND DATE(c.CHECKTIME) = fs.schedule_date
            AND TIME(c.CHECKTIME) > TIME(fs.time_in)
            AND TIMESTAMPDIFF(MINUTE, fs.time_in, TIME(c.CHECKTIME)) > 0 -- Strict late
            ORDER BY c.CHECKTIME DESC
            LIMIT 20
        ";
        $stmt = $this->pdo->prepare($sqlLate);
        $stmt->execute([':start' => $startOfWeek, ':end' => $endOfWeek]);
        $lateEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // C. Summary of Department with Highest Lates (Frequency)
        $sqlHiLateDept = "
            SELECT 
                fs.department as dept_name, 
                COUNT(*) as late_count 
            FROM finalized_schedule fs
            JOIN biometric_logs.userinfo u ON fs.ac_no = u.BADGENUMBER
            JOIN biometric_logs.checkinout c ON u.USERID = c.USERID
            WHERE fs.schedule_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
            AND DATE(c.CHECKTIME) = fs.schedule_date
            AND TIME(c.CHECKTIME) > TIME(fs.time_in)
            GROUP BY fs.department 
            ORDER BY late_count DESC 
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sqlHiLateDept);
        $stmt->execute();
        $highestLateDept = $stmt->fetch(PDO::FETCH_ASSOC);

        // D. Employees on Leave for Current Cutoff
        // Get current cutoff range first
        $cutoff_options = \App\Utils\AppHelpers::generateCutoffPeriods($this->pdo);
        $today = date('Y-m-d'); 
        $currentWait = explode('|', $cutoff_options[0]['value']);
        $cStart = $currentWait[0]; $cEnd = $currentWait[1];
        
        foreach($cutoff_options as $opt) { 
            $d = explode('|',$opt['value']); 
            if($today >= $d[0] && $today <= $d[1]){
                $cStart = $d[0]; $cEnd = $d[1]; break;
            } 
        }

        $sqlLeaves = "
            SELECT 
                e.first_name, 
                e.last_name, 
                d.dept_name,
                l.leave_type, 
                l.start_date, 
                l.end_date,
                l.status
            FROM leave_applications l
            JOIN employees e ON l.employee_id = e.emp_id
            LEFT JOIN departments d ON e.dept_id = d.dept_id
            WHERE l.status = 'Approved'
            AND (
                (l.start_date BETWEEN :s1 AND :e1) OR 
                (l.end_date BETWEEN :s2 AND :e2)
            )
            ORDER BY l.start_date DESC
        ";
        $stmt = $this->pdo->prepare($sqlLeaves);
        $stmt->execute([':s1'=>$cStart, ':e1'=>$cEnd, ':s2'=>$cStart, ':e2'=>$cEnd]);
        $employeesOnLeave = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->render('analytics_dashboard_view', compact(
            'highestAbsentDept', 
            'lateEmployees', 
            'highestLateDept', 
            'employeesOnLeave',
            'user_role'
        ));
    }
}