<?php
session_start();

// ============================================
// 1. DATABASE & CONFIG
// ============================================
function getDBConnection() {
    $serverName = "192.168.21.52,1433"; 
    $database = "SchedulerDB"; 
    $username = "sa"; 
    $password = "Azzurro2025"; 

    try {
        $conn = new PDO("sqlsrv:server=$serverName;Database=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) { die("Connection failed: " . $e->getMessage()); }
}

function getDynamicCutoffs() {
    $cutoffs = [];
    $current = new DateTime();
    $current->modify('-3 months'); 
    for ($i = 0; $i < 8; $i++) { 
        $year = $current->format('Y'); $month = $current->format('m');
        $cutoffs[] = ['value' => "$year-$month-10|$year-$month-24", 'label' => date('M d', strtotime("$year-$month-10")) . " - " . date('M d', strtotime("$year-$month-24")) . ", $year"];
        $nextMonth = clone $current; $nextMonth->modify('+1 month');
        $cutoffs[] = ['value' => "$year-$month-25|".$nextMonth->format('Y-m-09'), 'label' => date('M d', strtotime("$year-$month-25")) . " - " . date('M d', strtotime($nextMonth->format('Y-m-09'))) . ", " . $nextMonth->format('Y')];
        $current->modify('+1 month');
    }
    return $cutoffs;
}

// User Context
$current_user_id = $_SESSION['user_id'] ?? 1; 
$current_fullname = $_SESSION['full_name'] ?? 'System Admin';
$user_role = trim($_SESSION['approval_role'] ?? 'HR'); 
$user_dept = $_SESSION['dept_id'] ?? 0;

$is_head = (strcasecmp($user_role, 'DeptHead') === 0);
$is_hr   = (strcasecmp($user_role, 'HR') === 0);
$is_ceo  = (strcasecmp($user_role, 'CEO') === 0);

$conn = getDBConnection();

// ============================================
// 2. POST HANDLERS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- LEAVE/OT APPROVALS ---
    if (isset($_POST['action']) && $_POST['action'] === 'process_leave') {
        $stmt = $conn->prepare("UPDATE [EmployeeManagementSystem].[dbo].[LeaveApplications] SET status = ?, approved_by = ?, approval_date = GETDATE() WHERE leave_id = ?");
        $stmt->execute([$_POST['decision'] === 'approve' ? 'Approved' : 'Rejected', $current_user_id, $_POST['leave_id']]);
        header("Location: dashboard.php?tab=approvals"); exit;
    }
    if (isset($_POST['action']) && $_POST['action'] === 'process_ot') {
        $stmt = $conn->prepare("UPDATE [EmployeeManagementSystem].[dbo].[OvertimeApplications] SET status = ?, approved_by = ?, approval_date = GETDATE() WHERE ot_id = ?");
        $stmt->execute([$_POST['decision'] === 'approve' ? 'Approved' : 'Rejected', $current_user_id, $_POST['ot_id']]);
        header("Location: dashboard.php?tab=approvals"); exit;
    }

    // --- PAYROLL CONFIG UPDATES ---
    
    // 1. SSS Update
    if (isset($_POST['action']) && $_POST['action'] === 'update_sss') {
        $stmt = $conn->prepare("UPDATE Payroll_SSS_Table SET min_salary=?, max_salary=?, ee_share=? WHERE id=?");
        foreach ($_POST['sss'] as $id => $d) $stmt->execute([$d['min'], $d['max'], $d['ee'], $id]);
        header("Location: dashboard.php?tab=payroll&sub=sss"); exit;
    }

    // 2. PhilHealth Update
    if (isset($_POST['action']) && $_POST['action'] === 'update_philhealth') {
        $stmt = $conn->prepare("UPDATE Payroll_PhilHealth_Table SET min_salary=?, max_salary=?, rate=? WHERE id=?");
        foreach ($_POST['ph'] as $id => $d) $stmt->execute([$d['min'], $d['max'], $d['rate'], $id]);
        header("Location: dashboard.php?tab=payroll&sub=philhealth"); exit;
    }

    // 3. Pag-IBIG Update
    if (isset($_POST['action']) && $_POST['action'] === 'update_pagibig') {
        $stmt = $conn->prepare("UPDATE Payroll_PagIBIG_Table SET fixed_amt=? WHERE id=?");
        foreach ($_POST['pi'] as $id => $d) $stmt->execute([$d['fixed'], $id]);
        header("Location: dashboard.php?tab=payroll&sub=pagibig"); exit;
    }

    // 4. Tax Update
    if (isset($_POST['action']) && $_POST['action'] === 'update_tax') {
        $stmt = $conn->prepare("UPDATE Payroll_Tax_Table SET min_salary=?, max_salary=?, base_tax=?, excess_rate=? WHERE id=?");
        foreach ($_POST['tax'] as $id => $d) $stmt->execute([$d['min'], $d['max'], $d['base'], $d['rate'], $id]);
        header("Location: dashboard.php?tab=payroll&sub=tax"); exit;
    }

    // 5. Overtime Rules Update
    if (isset($_POST['action']) && $_POST['action'] === 'update_ot') {
        $stmt = $conn->prepare("UPDATE Payroll_OvertimeRules SET ot_multiplier = ?, night_diff_percent = ?, updated_at = GETDATE() WHERE id = ?");
        foreach ($_POST['ot_id'] as $idx => $id) $stmt->execute([$_POST['ot_multiplier'][$idx], $_POST['nd_multiplier'][$idx], $id]);
        header("Location: dashboard.php?tab=payroll&sub=ot"); exit;
    }

    // 6. Holiday Rules Update
    if (isset($_POST['action']) && $_POST['action'] === 'update_holiday') {
        $stmt = $conn->prepare("UPDATE Payroll_HolidayRules SET pay_if_unworked = ?, pay_if_worked = ?, updated_at = GETDATE() WHERE id = ?");
        foreach ($_POST['hol_id'] as $idx => $id) $stmt->execute([$_POST['pay_unworked'][$idx], $_POST['pay_worked'][$idx], $id]);
        header("Location: dashboard.php?tab=payroll&sub=holiday"); exit;
    }

    // 7. General Settings Update
    if (isset($_POST['action']) && $_POST['action'] === 'update_general') {
        $stmt = $conn->prepare("UPDATE Payroll_GeneralSettings SET setting_value = ? WHERE setting_key = ?");
        foreach ($_POST['settings'] as $key => $val) $stmt->execute([$val, $key]);
        header("Location: dashboard.php?tab=payroll&sub=general"); exit;
    }

    // 8. ALLOWANCE TYPES UPDATE
    if (isset($_POST['action']) && $_POST['action'] === 'update_allowance_type') {
        $name = $_POST['name'];
        $amount = $_POST['amount'];
        $deduct = $_POST['deduction'];
        $freq = $_POST['frequency'];
        
        $stmt = $conn->prepare("INSERT INTO Payroll_AllowanceTypes (name, amount, deduction_per_absent, frequency) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $amount, $deduct, $freq]);
        
        header("Location: dashboard.php?tab=payroll&sub=allowances"); exit;
    }

    // 9. ASSIGN ALLOWANCE (UPDATED: ASSIGN TO ALL ACTIVE EMPLOYEES)
    if (isset($_POST['action']) && $_POST['action'] === 'assign_allowance') {
        $allow_id = $_POST['allowance_id'];
        
        // 1. Fetch All Active Employees
        $activeEmps = $conn->query("SELECT emp_id FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE IsActive = 1")->fetchAll(PDO::FETCH_COLUMN);
        
        // 2. Prepare Statements
        $stmtCheck = $conn->prepare("SELECT id FROM Payroll_EmployeeAllowances WHERE emp_id=? AND allowance_id=?");
        $stmtInsert = $conn->prepare("INSERT INTO Payroll_EmployeeAllowances (emp_id, allowance_id) VALUES (?, ?)");
        
        // 3. Loop and Assign
        foreach ($activeEmps as $emp_id) {
            $stmtCheck->execute([$emp_id, $allow_id]);
            if(!$stmtCheck->fetch()) {
                $stmtInsert->execute([$emp_id, $allow_id]);
            }
        }
        
        header("Location: dashboard.php?tab=payroll&sub=allowances"); exit;
    }

    // --- SCHEDULE AJAX ---
    if (isset($_POST['action']) && $_POST['action'] === 'get_employees_by_dept') {
        $dept_id = $_POST['dept_id'];
        $stmt = $conn->prepare("SELECT emp_id, first_name, last_name FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE dept_id = ? AND employee_status = 'Active' ORDER BY last_name");
        $stmt->execute([$dept_id]);
        $emps = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $emps[] = ['id' => $row['emp_id'], 'name' => $row['first_name'] . ' ' . $row['last_name']]; }
        echo json_encode(['success' => true, 'employees' => $emps]); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'get_schedules') {
        $dept_id = $_POST['dept_id'];
        $range = $_POST['range']; 
        list($dS, $dE) = explode('|', $range);
        
        $stmtBatch = $conn->prepare("SELECT batch_id, status FROM ScheduleBatches WHERE dept_id = ? AND cutoff_start = ?");
        $stmtBatch->execute([$dept_id, $dS]);
        $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);
        $status = $batch ? $batch['status'] : 'Not Started';
        $batch_id = $batch ? $batch['batch_id'] : 0;

        $can_approve_hr = ($is_hr && $status == 'Pending HR');
        $can_approve_ceo = ($is_ceo && $status == 'Pending CEO');

        $schedules = [];
        $sqlSched = "";
        
        if ($status === 'Approved') {
            $sqlSched = "SELECT e.emp_id as employee_id, fs.schedule_Date, fs.Shift_code, fs.Time_In, fs.Time_Out 
                         FROM FinalizedSchedule fs
                         JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON fs.Ac_no = e.ac_no
                         WHERE e.dept_id = ? AND fs.schedule_Date BETWEEN ? AND ?";
             $stmtSched = $conn->prepare($sqlSched);
             $stmtSched->execute([$dept_id, $dS, $dE]);
        } elseif ($batch_id) {
            $sqlSched = "SELECT es.employee_id, es.schedule_date, es.shift_code, st.time_in, st.time_out 
                         FROM EmployeeSchedules es
                         LEFT JOIN shift_types st ON es.shift_code = st.shift_code
                         WHERE es.batch_id = ? AND es.schedule_date BETWEEN ? AND ?";
            $stmtSched = $conn->prepare($sqlSched);
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
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $dept_id = $_POST['dept_id']; $range = $_POST['range']; list($start, $end) = explode('|', $range);
        $new_status = $_POST['status']; 
        
        $stmtBatch = $conn->prepare("SELECT batch_id FROM ScheduleBatches WHERE dept_id = ? AND cutoff_start = ?");
        $stmtBatch->execute([$dept_id, $start]);
        $row = $stmtBatch->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $batch_id = $row['batch_id'];
            $conn->prepare("UPDATE ScheduleBatches SET status = ? WHERE batch_id = ?")->execute([$new_status, $batch_id]);
            
            $conn->prepare("INSERT INTO ScheduleHistory (batch_id, action, actor_name, actor_role, comments) VALUES (?, ?, ?, ?, ?)")
                 ->execute([$batch_id, $new_status, $current_fullname, $user_role, 'Status update via Dashboard']);

            if ($new_status === 'Approved') {
                $conn->prepare("DELETE FROM FinalizedSchedule WHERE Ac_no IN (SELECT e.ac_no FROM EmployeeSchedules es JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON es.employee_id = e.emp_id WHERE es.batch_id = ?) AND schedule_Date BETWEEN ? AND ?")->execute([$batch_id, $start, $end]);
                
                $sqlFin = "INSERT INTO FinalizedSchedule (Ac_no, Name, Department, schedule_Date, Shift_code, Time_In, Time_Out)
                SELECT e.ac_no, (e.first_name + ' ' + e.last_name), d.dept_name, es.schedule_date, es.shift_code,
                    CASE WHEN st.time_in IS NULL THEN NULL ELSE CAST(es.schedule_date AS DATETIME) + CAST(st.time_in AS DATETIME) END,
                    CASE WHEN st.time_out IS NULL THEN NULL 
                         WHEN st.time_out < st.time_in THEN DATEADD(day, 1, CAST(es.schedule_date AS DATETIME)) + CAST(st.time_out AS DATETIME)
                         ELSE CAST(es.schedule_date AS DATETIME) + CAST(st.time_out AS DATETIME) END
                FROM EmployeeSchedules es
                JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON es.employee_id = e.emp_id
                JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON e.dept_id = d.dept_id
                LEFT JOIN shift_types st ON es.shift_code = st.shift_code
                WHERE es.batch_id = ?";
                $conn->prepare($sqlFin)->execute([$batch_id]);
            }
        }
        echo json_encode(['success' => true]); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'get_pending_batches') {
        $target = $is_hr ? 'Pending HR' : ($is_ceo ? 'Pending CEO' : '');
        $pending = [];
        if($target) {
            $stmt = $conn->prepare("SELECT b.batch_id, b.cutoff_start, b.cutoff_end, b.dept_id, d.dept_name FROM ScheduleBatches b LEFT JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON b.dept_id = d.dept_id WHERE b.status = ? ORDER BY b.updated_at DESC");
            $stmt->execute([$target]);
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cS = is_object($row['cutoff_start']) ? $row['cutoff_start']->format('Y-m-d') : $row['cutoff_start'];
                $cE = is_object($row['cutoff_end']) ? $row['cutoff_end']->format('Y-m-d') : $row['cutoff_end'];
                $pending[] = [
                    'dept_name' => $row['dept_name'],
                    'range_val' => $cS . '|' . $cE,
                    'dept_id' => $row['dept_id'],
                    'display' => date('M d', strtotime($cS)) . ' - ' . date('M d, Y', strtotime($cE))
                ];
            }
        }
        echo json_encode(['success'=>true, 'data'=>$pending]); exit;
    }
}

// ============================================
// 3. FETCH DATA (VIEW)
// ============================================

// A. Stats Cards
$totalEmp = $conn->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE IsActive = 1 AND employee_status = 'Active'")->fetchColumn();
$newHires = $conn->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE IsActive = 1 AND MONTH(date_hired) = MONTH(GETDATE())")->fetchColumn();
$avgSalary = $conn->query("SELECT AVG(salary_rate) FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE salary_type = 'Monthly' AND salary_rate > 0")->fetchColumn();

// --- Gender Data ---
$maleCount = $conn->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE IsActive = 1 AND gender = 'Male'")->fetchColumn();
$femaleCount = $conn->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE IsActive = 1 AND gender = 'Female'")->fetchColumn();
$totalGender = $maleCount + $femaleCount;
$malePercent = $totalGender > 0 ? round(($maleCount / $totalGender) * 100, 1) : 0;
$femalePercent = $totalGender > 0 ? round(($femaleCount / $totalGender) * 100, 1) : 0;

// B. Pending Counts
$pendingLeavesCount = $conn->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[LeaveApplications] WHERE status = 'Submitted' OR status = 'Pending'")->fetchColumn();
$pendingOTCount = $conn->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[OvertimeApplications] WHERE status = 'Pending'")->fetchColumn();
$pendingSchedCount = $conn->query("SELECT COUNT(*) FROM ScheduleBatches WHERE status = 'Pending HR' OR status = 'Pending CEO'")->fetchColumn();
$totalPending = $pendingLeavesCount + $pendingOTCount + $pendingSchedCount;

// C. Charts Data
$deptStats = $conn->query("SELECT d.dept_name, COUNT(e.emp_id) as count FROM [EmployeeManagementSystem].[dbo].[Employees] e JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON e.dept_id = d.dept_id WHERE e.IsActive = 1 GROUP BY d.dept_name")->fetchAll(PDO::FETCH_ASSOC);
$deptLabels = json_encode(array_column($deptStats, 'dept_name'));
$deptCounts = json_encode(array_column($deptStats, 'count'));

$statusStats = $conn->query("SELECT employment_status, COUNT(*) as count FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE IsActive = 1 GROUP BY employment_status")->fetchAll(PDO::FETCH_ASSOC);
$statusLabels = json_encode(array_column($statusStats, 'employment_status'));
$statusCounts = json_encode(array_column($statusStats, 'count'));

// D. Lists
$pendingLeaveList = $conn->query("SELECT TOP 5 l.*, e.first_name, e.last_name FROM [EmployeeManagementSystem].[dbo].[LeaveApplications] l JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON l.emp_id = e.emp_id WHERE l.status IN ('Submitted', 'Pending') ORDER BY l.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
$pendingOTList = $conn->query("SELECT TOP 5 ot.*, e.first_name, e.last_name FROM [EmployeeManagementSystem].[dbo].[OvertimeApplications] ot JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON ot.emp_id = e.emp_id WHERE ot.status = 'Pending' ORDER BY ot.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- PAYROLL CONFIG DATA ---
$sssData = $conn->query("SELECT * FROM Payroll_SSS_Table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC);
$phData = $conn->query("SELECT * FROM Payroll_PhilHealth_Table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC);
$piData = $conn->query("SELECT * FROM Payroll_PagIBIG_Table")->fetchAll(PDO::FETCH_ASSOC); 
$taxData = $conn->query("SELECT * FROM Payroll_Tax_Table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC); 

$otRules = $conn->query("SELECT * FROM Payroll_OvertimeRules ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$holRules = $conn->query("SELECT * FROM Payroll_HolidayRules ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$genSettingsRaw = $conn->query("SELECT * FROM Payroll_GeneralSettings")->fetchAll(PDO::FETCH_ASSOC);
$genSettings = [];
foreach($genSettingsRaw as $row) { $genSettings[$row['setting_key']] = $row['setting_value']; }

// --- FETCH ALLOWANCE TYPES ---
$allowanceTypes = $conn->query("SELECT * FROM Payroll_AllowanceTypes WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

// E. Dropdowns
$depts = $conn->query("SELECT * FROM [EmployeeManagementSystem].[dbo].[Departments] ORDER BY dept_name")->fetchAll(PDO::FETCH_ASSOC);
$cutoff_options = getDynamicCutoffs();
$today = date('Y-m-d'); $default_cutoff = $cutoff_options[3]['value'];
foreach($cutoff_options as $opt) { $d=explode('|',$opt['value']); if($today>=$d[0]&&$today<=$d[1]){$default_cutoff=$opt['value']; break;} }

$activeTab = $_GET['tab'] ?? 'dashboard';
$activePaySub = $_GET['sub'] ?? 'sss';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HRCore Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --background: 0 0% 100%;
            --foreground: 0 0% 12.5%;
            --card: 0 0% 100%;
            --card-foreground: 0 0% 37%;
            --primary: 133 76% 59%;
            --primary-foreground: 0 0% 12.5%;
            --muted: 0 0% 96%;
            --muted-foreground: 0 0% 45%;
            --border: 0 0% 90%;
            --radius: 0.625rem;
            --chart-1: #a3e635;
            --chart-2: #3b82f6;
            --chart-3: #f472b6;
            --chart-4: #facc15;
            --chart-5: #cbd5e1;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #FAFAFA;
            color: #1F1F1F;
        }

        .fade-in { animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .schedule-table th { position: sticky; top: 0; background: #f9fafb; z-index: 10; font-weight: 600; letter-spacing: 0.025em; text-transform: uppercase; font-size: 0.7rem; color: #6b7280; padding: 0.75rem 0.5rem; border-bottom: 1px solid #e5e7eb; }
        .schedule-table td:first-child { position: sticky; left: 0; background: white; z-index: 20; font-weight: 600; border-right: 1px solid #e5e7eb; }
        
        .nav-link { color: #6b7280; font-weight: 500; padding: 0.5rem 1rem; border-radius: var(--radius); transition: all 0.2s; }
        .nav-link:hover { color: #111827; background-color: #f3f4f6; }
        .nav-link.active { background-color: rgba(163, 230, 53, 0.2); color: #1a2e05; font-weight: 700; }

        .pay-sub-link { color: #6b7280; border-bottom: 2px solid transparent; transition: all 0.2s; }
        .pay-sub-link:hover { color: #111827; }
        .pay-sub-link.active { color: #4d7c0f; border-bottom-color: #a3e635; font-weight: 700; }

        /* Card styles matching the requested theme */
        .stat-card {
            background-color: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: var(--radius);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        border: 'hsl(var(--border))',
                        background: 'hsl(var(--background))',
                        foreground: 'hsl(var(--foreground))',
                        primary: {
                            DEFAULT: '#a3e635',
                            foreground: '#1F1F1F',
                            50: '#f7fee7',
                            100: '#ecfccb',
                            500: '#84cc16',
                            600: '#65a30d',
                        },
                        muted: {
                            DEFAULT: '#f3f4f6',
                            foreground: '#6b7280'
                        },
                        card: {
                            DEFAULT: '#ffffff',
                            foreground: '#374151'
                        }
                    },
                    borderRadius: {
                        lg: 'var(--radius)',
                        md: 'calc(var(--radius) - 2px)',
                        sm: 'calc(var(--radius) - 4px)',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-slate-600 font-sans min-h-screen flex flex-col">

    <header class="bg-white/80 backdrop-blur-md border-b border-border sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-12">
                <a href="index.php" class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-primary text-primary-foreground flex items-center justify-center text-lg"><i class="fa-solid fa-layer-group"></i></div>
                    <span class="font-bold text-xl text-gray-900 tracking-tight">HRCore</span>
                </a>
                <nav class="hidden md:flex gap-2 text-sm">
                    <a href="index.php" class="flex items-center gap-2 text-gray-500 hover:text-gray-900 transition-colors font-medium text-sm">
                    <i class="fa-solid fa-arrow-left"></i> Return to Home
                    </a>
                    <button onclick="switchTab('dashboard')" id="nav-dashboard" class="nav-link <?php echo $activeTab=='dashboard'?'active':''; ?>">Overview</button>
                    <a href="employee.php" class="nav-link">Employees</a>
                    <button onclick="switchTab('approvals')" id="nav-approvals" class="nav-link relative <?php echo $activeTab=='approvals'?'active':''; ?>">
                        Approvals
                        <?php if($totalPending > 0): ?><span class="absolute top-0.5 right-0 w-2 h-2 bg-red-500 rounded-full"></span><?php endif; ?>
                    </button>
                    <button onclick="switchTab('global_schedule')" id="nav-global_schedule" class="nav-link <?php echo $activeTab=='global_schedule'?'active':''; ?>">Global Schedule</button>
                    <button onclick="switchTab('payroll')" id="nav-payroll" class="nav-link <?php echo $activeTab=='payroll'?'active':''; ?>">Payroll</button>
                    <a href="loan.php" class="nav-link">Loans</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex flex-col text-right">
                    <span class="text-sm font-bold text-gray-900 leading-none"><?php echo htmlspecialchars($current_fullname); ?></span>
                    <span class="text-[10px] text-gray-500 font-medium uppercase"><?php echo substr($user_role,0,20); ?></span>
                </div>
                <div class="w-9 h-9 rounded-full bg-gray-100 border border-gray-200 text-gray-600 flex items-center justify-center text-xs font-bold">
                    <?php echo substr($current_fullname, 0, 1); ?>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-7xl mx-auto px-6 py-8 w-full">

        <div id="view-dashboard" class="tab-view space-y-6 fade-in <?php echo $activeTab!='dashboard'?'hidden':''; ?>">
            
            <div class="mb-2">
                <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                <p class="text-gray-500 text-sm">Welcome back, here's what's happening today.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="stat-card">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-primary-100 rounded-lg text-primary-600"><i class="fa-solid fa-users"></i></div>
                        <?php if($newHires > 0): ?><span class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-full">+<?php echo $newHires; ?> New</span><?php endif; ?>
                    </div>
                    <p class="text-gray-500 text-xs font-medium uppercase tracking-wider">Total Employees</p>
                    <h2 class="text-3xl font-bold text-gray-900 mt-1"><?php echo number_format($totalEmp); ?></h2>
                </div>
                
                <div class="stat-card">
                    <div class="flex justify-between items-start mb-4">
                         <div class="p-2 bg-blue-50 rounded-lg text-blue-600"><i class="fa-solid fa-briefcase"></i></div>
                    </div>
                    <p class="text-gray-500 text-xs font-medium uppercase tracking-wider">New Hires (Mo)</p>
                    <h2 class="text-3xl font-bold text-gray-900 mt-1"><?php echo number_format($newHires); ?></h2>
                </div>
                
                <div class="stat-card">
                    <div class="flex justify-between items-start mb-4">
                         <div class="p-2 bg-purple-50 rounded-lg text-purple-600"><i class="fa-solid fa-money-bill-wave"></i></div>
                    </div>
                    <p class="text-gray-500 text-xs font-medium uppercase tracking-wider">Avg. Salary</p>
                    <h2 class="text-3xl font-bold text-gray-900 mt-1">₱<?php echo number_format($avgSalary/1000, 1); ?>k</h2>
                </div>
                
                <div class="stat-card bg-red-50 border-red-100">
                    <div class="flex justify-between items-start mb-4">
                         <div class="p-2 bg-white rounded-lg text-red-600 shadow-sm"><i class="fa-solid fa-bell"></i></div>
                    </div>
                    <p class="text-red-500 text-xs font-bold uppercase tracking-wider">Pending Actions</p>
                    <h2 class="text-3xl font-bold text-red-700 mt-1"><?php echo $totalPending; ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-gray-900">Workforce Demographics</h3>
                    <div class="flex gap-4">
                        <div class="flex items-center gap-2 text-xs font-bold text-gray-600"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Male</div>
                        <div class="flex items-center gap-2 text-xs font-bold text-gray-600"><span class="w-2.5 h-2.5 rounded-full bg-pink-500"></span> Female</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                    <div class="flex items-center justify-center gap-8 border-r border-dashed border-gray-200 pr-8">
                        <div class="relative w-40 h-40">
                            <canvas id="genderDonutChart"></canvas>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <p class="text-xs text-gray-400 font-bold uppercase">Male</p>
                                <div class="flex items-baseline gap-2">
                                    <h3 class="text-2xl font-bold text-gray-900"><?php echo number_format($maleCount); ?></h3>
                                    <span class="text-xs bg-blue-50 text-blue-600 font-bold px-1.5 py-0.5 rounded"><?php echo $malePercent; ?>%</span>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-bold uppercase">Female</p>
                                <div class="flex items-baseline gap-2">
                                    <h3 class="text-2xl font-bold text-gray-900"><?php echo number_format($femaleCount); ?></h3>
                                    <span class="text-xs bg-pink-50 text-pink-500 font-bold px-1.5 py-0.5 rounded"><?php echo $femalePercent; ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="h-48 w-full">
                         <canvas id="genderBarChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 stat-card">
                    <h3 class="font-bold text-gray-900 mb-6">Department Distribution</h3>
                    <div class="h-64 w-full"><canvas id="deptChart"></canvas></div>
                </div>
                <div class="stat-card flex flex-col">
                    <h3 class="font-bold text-gray-900 mb-2">Employment Status</h3>
                    <div class="flex-1 flex items-center justify-center relative">
                        <canvas id="statusChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none mt-2">
                            <span class="text-3xl font-extrabold text-gray-900"><?php echo $totalEmp; ?></span>
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div id="view-approvals" class="tab-view space-y-6 fade-in <?php echo $activeTab!='approvals'?'hidden':''; ?>">
            <div class="mb-2">
                <h1 class="text-2xl font-bold text-gray-900">Approvals</h1>
                <p class="text-gray-500 text-sm">Manage pending requests and schedules.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <div class="col-span-1 lg:col-span-2 stat-card bg-blue-50/30 border-blue-100">
                    <div class="flex justify-between items-center mb-4 border-b border-blue-100 pb-2">
                        <h3 class="font-bold text-blue-900 flex items-center gap-2"><i class="fa-solid fa-calendar-check"></i>Pending Schedule Batches</h3>
                        <span class="text-xs text-blue-500 font-medium">Click "Review Batch" to take action</span>
                    </div>
                    <div id="batch_approval_list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <p class="text-gray-400 italic text-sm col-span-full py-4 text-center">Loading pending batches...</p>
                    </div>
                </div>

                <div class="stat-card">
                    <h3 class="font-bold text-gray-900 mb-4 flex items-center justify-between">
                        <span>Leave Requests</span>
                        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-1 rounded-full"><?php echo count($pendingLeaveList); ?></span>
                    </h3>
                    <div class="space-y-3">
                        <?php foreach($pendingLeaveList as $l): ?>
                        <div class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-100">
                            <div>
                                <p class="text-sm font-bold text-gray-900"><?php echo $l['first_name'].' '.$l['last_name']; ?></p>
                                <p class="text-xs text-gray-500 font-medium"><?php echo $l['leave_type']; ?> • <?php echo date('M d', strtotime($l['start_date'])); ?></p>
                            </div>
                            <form method="POST" class="flex gap-2">
                                <input type="hidden" name="action" value="process_leave"><input type="hidden" name="leave_id" value="<?php echo $l['leave_id']; ?>">
                                <button name="decision" value="approve" class="bg-white text-green-600 border border-green-200 hover:bg-green-50 w-8 h-8 flex items-center justify-center rounded-md shadow-sm transition"><i class="fa-solid fa-check text-xs"></i></button>
                                <button name="decision" value="reject" class="bg-white text-red-600 border border-red-200 hover:bg-red-50 w-8 h-8 flex items-center justify-center rounded-md shadow-sm transition"><i class="fa-solid fa-xmark text-xs"></i></button>
                            </form>
                        </div>
                        <?php endforeach; if(empty($pendingLeaveList)) echo "<p class='text-xs text-gray-400 italic text-center py-4'>No pending leaves.</p>"; ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3 class="font-bold text-gray-900 mb-4 flex items-center justify-between">
                        <span>Overtime Requests</span>
                        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-1 rounded-full"><?php echo count($pendingOTList); ?></span>
                    </h3>
                    <div class="space-y-3">
                        <?php foreach($pendingOTList as $ot): ?>
                        <div class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-100">
                            <div>
                                <p class="text-sm font-bold text-gray-900"><?php echo $ot['first_name'].' '.$ot['last_name']; ?></p>
                                <p class="text-xs text-gray-500 font-medium"><?php echo $ot['ot_hours']; ?> hrs • <?php echo date('M d', strtotime($ot['ot_date'])); ?></p>
                            </div>
                            <form method="POST" class="flex gap-2">
                                <input type="hidden" name="action" value="process_ot"><input type="hidden" name="ot_id" value="<?php echo $ot['ot_id']; ?>">
                                <button name="decision" value="approve" class="bg-white text-green-600 border border-green-200 hover:bg-green-50 w-8 h-8 flex items-center justify-center rounded-md shadow-sm transition"><i class="fa-solid fa-check text-xs"></i></button>
                                <button name="decision" value="reject" class="bg-white text-red-600 border border-red-200 hover:bg-red-50 w-8 h-8 flex items-center justify-center rounded-md shadow-sm transition"><i class="fa-solid fa-xmark text-xs"></i></button>
                            </form>
                        </div>
                        <?php endforeach; if(empty($pendingOTList)) echo "<p class='text-xs text-gray-400 italic text-center py-4'>No pending overtime.</p>"; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="view-global_schedule" class="tab-view space-y-6 fade-in <?php echo $activeTab!='global_schedule'?'hidden':''; ?>">
            <div class="stat-card">
                <div class="flex flex-col md:flex-row justify-between items-end mb-6 gap-4 border-b border-gray-100 pb-4">
                    <div>
                        <h3 class="font-bold text-gray-900 text-lg">Global Schedule Viewer</h3>
                        <p class="text-xs text-gray-500">View finalized schedules for any department across cutoffs.</p>
                    </div>
                    <div class="flex gap-3 w-full md:w-auto">
                        <select id="viewer_dept" class="border border-gray-300 rounded-md p-2 text-sm w-full md:w-48 focus:ring-2 focus:ring-primary-500 outline-none">
                            <option value="">-- Select Dept --</option>
                            <?php foreach($depts as $d): ?><option value="<?php echo $d['dept_id']; ?>"><?php echo $d['dept_name']; ?></option><?php endforeach; ?>
                        </select>
                        <select id="viewer_range" class="border border-gray-300 rounded-md p-2 text-sm w-full md:w-48 focus:ring-2 focus:ring-primary-500 outline-none">
                            <?php foreach($cutoff_options as $opt): ?><option value="<?php echo $opt['value']; ?>" <?php echo $opt['value']==$default_cutoff?'selected':''; ?>><?php echo $opt['label']; ?></option><?php endforeach; ?>
                        </select>
                        <button onclick="loadGlobalGrid()" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-md text-sm font-bold transition">Load</button>
                    </div>
                </div>
                <div class="overflow-x-auto border border-gray-200 rounded-lg max-h-[600px] shadow-inner">
                    <table class="w-full text-center text-xs border-collapse schedule-table">
                        <thead id="viewer_head"></thead>
                        <tbody class="divide-y divide-gray-100 bg-white" id="viewer_body">
                            <tr><td colspan="15" class="p-10 text-gray-400 font-medium">Select Department and Cutoff to view data.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="view-payroll" class="tab-view space-y-6 fade-in <?php echo $activeTab!='payroll'?'hidden':''; ?>">
            <div class="stat-card p-0 overflow-hidden">
                
                <div class="flex border-b border-border bg-gray-50 px-6 pt-4 gap-6 overflow-x-auto">
                    <button onclick="switchPaySub('sss')" id="sub-nav-sss" class="pay-sub-link pb-3 text-sm font-bold <?php echo $activePaySub=='sss'?'active':''; ?>">SSS</button>
                    <button onclick="switchPaySub('philhealth')" id="sub-nav-philhealth" class="pay-sub-link pb-3 text-sm font-bold <?php echo $activePaySub=='philhealth'?'active':''; ?>">PhilHealth</button>
                    <button onclick="switchPaySub('pagibig')" id="sub-nav-pagibig" class="pay-sub-link pb-3 text-sm font-bold <?php echo $activePaySub=='pagibig'?'active':''; ?>">Pag-IBIG</button>
                    <button onclick="switchPaySub('tax')" id="sub-nav-tax" class="pay-sub-link pb-3 text-sm font-bold <?php echo $activePaySub=='tax'?'active':''; ?>">Tax Table</button>
                    <button onclick="switchPaySub('ot')" id="sub-nav-ot" class="pay-sub-link pb-3 text-sm font-bold <?php echo $activePaySub=='ot'?'active':''; ?>">OT Rates</button>
                    <button onclick="switchPaySub('holiday')" id="sub-nav-holiday" class="pay-sub-link pb-3 text-sm font-bold <?php echo $activePaySub=='holiday'?'active':''; ?>">Holiday Pay</button>
                    <button onclick="switchPaySub('general')" id="sub-nav-general" class="pay-sub-link pb-3 text-sm font-bold <?php echo $activePaySub=='general'?'active':''; ?>">General / Lates</button>
                    <button onclick="switchPaySub('allowances')" id="sub-nav-allowances" class="pay-sub-link pb-3 text-sm font-bold <?php echo $activePaySub=='allowances'?'active':''; ?>">Allowances</button>
                </div>

                <div class="p-6 bg-white min-h-[400px]">
                    
                    <div id="pay-sub-allowances" class="pay-sub-view hidden">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <div>
                                <h4 class="font-bold text-gray-900 mb-4">Allowance Rules</h4>
                                <form method="POST" class="mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <input type="hidden" name="action" value="update_allowance_type">
                                    <div class="grid grid-cols-2 gap-4 mb-4">
                                        <div><label class="text-[10px] font-bold text-gray-500 uppercase">Name</label><input type="text" name="name" class="w-full border p-2 rounded-md text-sm mt-1" required></div>
                                        <div><label class="text-[10px] font-bold text-gray-500 uppercase">Frequency</label>
                                            <select name="frequency" class="w-full border p-2 rounded-md text-sm mt-1">
                                                <option value="Semi-Monthly">Per Cutoff</option>
                                                <option value="Daily">Daily Rate</option>
                                            </select>
                                        </div>
                                        <div><label class="text-[10px] font-bold text-gray-500 uppercase">Amount</label><input type="number" step="0.01" name="amount" class="w-full border p-2 rounded-md text-sm mt-1" required></div>
                                        <div><label class="text-[10px] font-bold text-gray-500 uppercase">Deduct/Absent</label><input type="number" step="0.01" name="deduction" value="0" class="w-full border p-2 rounded-md text-sm mt-1"></div>
                                    </div>
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm w-full font-bold transition">Add Rule</button>
                                </form>

                                <div class="border rounded-lg overflow-hidden">
                                    <table class="w-full text-xs text-left">
                                        <thead class="bg-gray-100 font-bold text-gray-600"><tr><th class="p-3">Name</th><th class="p-3">Amount</th><th class="p-3">Deduct</th><th class="p-3">Freq</th></tr></thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <?php foreach($allowanceTypes as $a): ?>
                                            <tr>
                                                <td class="p-3 font-bold text-gray-800"><?php echo htmlspecialchars($a['name']); ?></td>
                                                <td class="p-3 text-green-600"><?php echo number_format($a['amount'], 2); ?></td>
                                                <td class="p-3 text-red-500"><?php echo number_format($a['deduction_per_absent'], 2); ?></td>
                                                <td class="p-3 text-gray-500"><?php echo $a['frequency']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            </div>
                    </div>

                    <div id="pay-sub-sss" class="pay-sub-view <?php echo $activePaySub!='sss'?'hidden':''; ?>">
                        <h3 class="font-bold text-gray-900 mb-4">SSS Contribution Table</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_sss">
                            <div class="overflow-auto max-h-[500px] border border-gray-200 rounded-lg mb-6 shadow-sm">
                                <table class="w-full text-sm text-left"><thead class="bg-gray-50 sticky top-0 text-gray-500 uppercase text-xs"><tr><th class="p-3 font-bold">Min Salary</th><th class="p-3 font-bold">Max Salary</th><th class="p-3 font-bold">EE Share</th></tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach($sssData as $r): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-2"><input type="number" step="0.01" name="sss[<?php echo $r['id']; ?>][min]" value="<?php echo $r['min_salary']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs"></td>
                                        <td class="p-2"><input type="number" step="0.01" name="sss[<?php echo $r['id']; ?>][max]" value="<?php echo $r['max_salary']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs"></td>
                                        <td class="p-2"><input type="number" step="0.01" name="sss[<?php echo $r['id']; ?>][ee]" value="<?php echo $r['ee_share']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs font-bold text-blue-600 bg-blue-50/50"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody></table>
                            </div>
                            <button type="submit" class="bg-primary-50 text-primary-700 border border-primary-200 font-bold px-6 py-2.5 rounded-md hover:bg-primary-100 transition shadow-sm">Save Changes</button>
                        </form>
                    </div>

                    <div id="pay-sub-philhealth" class="pay-sub-view <?php echo $activePaySub!='philhealth'?'hidden':''; ?>">
                         <h3 class="font-bold text-gray-900 mb-4">PhilHealth Contribution Table</h3>
                         <form method="POST">
                            <input type="hidden" name="action" value="update_philhealth">
                            <div class="overflow-auto max-h-[500px] border border-gray-200 rounded-lg mb-6 shadow-sm">
                                <table class="w-full text-sm text-left"><thead class="bg-gray-50 sticky top-0 text-gray-500 uppercase text-xs"><tr><th class="p-3 font-bold">Min Salary</th><th class="p-3 font-bold">Max Salary</th><th class="p-3 font-bold">Rate</th></tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach($phData as $r): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-2"><input type="number" step="0.01" name="ph[<?php echo $r['id']; ?>][min]" value="<?php echo $r['min_salary']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs"></td>
                                        <td class="p-2"><input type="number" step="0.01" name="ph[<?php echo $r['id']; ?>][max]" value="<?php echo $r['max_salary']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs"></td>
                                        <td class="p-2"><input type="number" step="0.0001" name="ph[<?php echo $r['id']; ?>][rate]" value="<?php echo $r['rate']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs font-bold text-green-600 bg-green-50/50"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody></table>
                            </div>
                            <button type="submit" class="bg-primary-50 text-primary-700 border border-primary-200 font-bold px-6 py-2.5 rounded-md hover:bg-primary-100 transition shadow-sm">Save Changes</button>
                        </form>
                    </div>

                    <div id="pay-sub-pagibig" class="pay-sub-view hidden">
                        <h3 class="font-bold text-gray-900 mb-4">Pag-IBIG Contribution</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_pagibig">
                            <div class="overflow-auto max-h-[500px] border border-gray-200 rounded-lg mb-6 shadow-sm">
                                <table class="w-full text-sm text-left">
                                    <thead class="bg-gray-50 sticky top-0 text-gray-500 uppercase text-xs">
                                        <tr><th class="p-3 font-bold">Contribution Type</th><th class="p-3 font-bold">Fixed Amount</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach($piData as $r): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-2 text-gray-700 font-medium">Employee Share (Standard)</td>
                                            <td class="p-2"><input type="number" step="0.01" name="pi[<?php echo $r['id']; ?>][fixed]" value="<?php echo $r['fixed_amt']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs font-bold text-blue-600 bg-blue-50/50"></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" class="bg-primary-50 text-primary-700 border border-primary-200 font-bold px-6 py-2.5 rounded-md hover:bg-primary-100 transition shadow-sm">Save Changes</button>
                        </form>
                    </div>

                    <div id="pay-sub-tax" class="pay-sub-view hidden">
                        <h3 class="font-bold text-gray-900 mb-4">Withholding Tax Table</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_tax">
                            <div class="overflow-auto max-h-[500px] border border-gray-200 rounded-lg mb-6 shadow-sm">
                                <table class="w-full text-sm text-left">
                                    <thead class="bg-gray-50 sticky top-0 text-gray-500 uppercase text-xs">
                                        <tr>
                                            <th class="p-3 font-bold">Min Salary</th>
                                            <th class="p-3 font-bold">Max Salary</th>
                                            <th class="p-3 font-bold">Base Tax</th>
                                            <th class="p-3 font-bold">Excess Rate (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach($taxData as $r): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-2"><input type="number" step="0.01" name="tax[<?php echo $r['id']; ?>][min]" value="<?php echo $r['min_salary']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs"></td>
                                            <td class="p-2"><input type="number" step="0.01" name="tax[<?php echo $r['id']; ?>][max]" value="<?php echo $r['max_salary']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs"></td>
                                            <td class="p-2"><input type="number" step="0.01" name="tax[<?php echo $r['id']; ?>][base]" value="<?php echo $r['base_tax']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs font-bold text-red-600 bg-red-50/50"></td>
                                            <td class="p-2"><input type="number" step="0.0001" name="tax[<?php echo $r['id']; ?>][rate]" value="<?php echo $r['excess_rate']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs"></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" class="bg-primary-50 text-primary-700 border border-primary-200 font-bold px-6 py-2.5 rounded-md hover:bg-primary-100 transition shadow-sm">Save Changes</button>
                        </form>
                    </div>

                    <div id="pay-sub-ot" class="pay-sub-view hidden">
                        <h3 class="font-bold text-gray-900 mb-4">Overtime & Night Diff Rates</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_ot">
                            <div class="overflow-auto border border-gray-200 rounded-lg mb-6 shadow-sm">
                                <table class="w-full text-sm text-left">
                                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                                        <tr>
                                            <th class="p-3 font-bold">Day Type</th>
                                            <th class="p-3 font-bold">OT Multiplier (e.g. 1.25)</th>
                                            <th class="p-3 font-bold">Night Diff % (e.g. 0.10)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach($otRules as $r): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-3 font-bold text-gray-700">
                                                <input type="hidden" name="ot_id[]" value="<?php echo $r['id']; ?>">
                                                <?php echo $r['day_type']; ?>
                                            </td>
                                            <td class="p-2"><input type="number" step="0.01" name="ot_multiplier[]" value="<?php echo $r['ot_multiplier']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs bg-yellow-50/50 font-bold text-yellow-700"></td>
                                            <td class="p-2"><input type="number" step="0.01" name="nd_multiplier[]" value="<?php echo $r['night_diff_percent']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs"></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" class="bg-primary-50 text-primary-700 border border-primary-200 font-bold px-6 py-2.5 rounded-md hover:bg-primary-100 transition shadow-sm">Save Changes</button>
                        </form>
                    </div>

                    <div id="pay-sub-holiday" class="pay-sub-view hidden">
                        <h3 class="font-bold text-gray-900 mb-4">Holiday Pay Rules</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_holiday">
                            <div class="overflow-auto border border-gray-200 rounded-lg mb-6 shadow-sm">
                                <table class="w-full text-sm text-left">
                                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                                        <tr>
                                            <th class="p-3 font-bold">Holiday Type</th>
                                            <th class="p-3 font-bold">Pay if Unworked (%)</th>
                                            <th class="p-3 font-bold">Pay if Worked (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach($holRules as $r): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-3 font-bold text-gray-700">
                                                <input type="hidden" name="hol_id[]" value="<?php echo $r['id']; ?>">
                                                <?php echo $r['holiday_type']; ?>
                                            </td>
                                            <td class="p-2"><input type="number" step="0.01" name="pay_unworked[]" value="<?php echo $r['pay_if_unworked']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs"></td>
                                            <td class="p-2"><input type="number" step="0.01" name="pay_worked[]" value="<?php echo $r['pay_if_worked']; ?>" class="w-full border-gray-200 rounded px-2 py-1 text-xs font-bold text-green-600 bg-green-50/50"></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" class="bg-primary-50 text-primary-700 border border-primary-200 font-bold px-6 py-2.5 rounded-md hover:bg-primary-100 transition shadow-sm">Save Changes</button>
                        </form>
                    </div>

                    <div id="pay-sub-general" class="pay-sub-view hidden">
                        <h3 class="font-bold text-gray-900 mb-4">General Payroll Settings</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_general">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <label class="text-xs font-bold text-gray-500 uppercase mb-2 block">Late Grace Period (Minutes)</label>
                                    <input type="number" name="settings[late_grace_period]" value="<?php echo $genSettings['late_grace_period'] ?? 15; ?>" class="w-full border-gray-300 rounded-md p-2 text-sm">
                                    <p class="text-[10px] text-gray-400 mt-1">Minutes before late deduction applies.</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <label class="text-xs font-bold text-gray-500 uppercase mb-2 block">Days per Month (For Daily Rate Calc)</label>
                                    <input type="number" step="0.01" name="settings[workdays_per_month]" value="<?php echo $genSettings['workdays_per_month'] ?? 26.0833; ?>" class="w-full border-gray-300 rounded-md p-2 text-sm">
                                    <p class="text-[10px] text-gray-400 mt-1">Standard divisor (e.g., 26.0833 or 22).</p>
                                </div>
                            </div>
                            <button type="submit" class="bg-primary-50 text-primary-700 border border-primary-200 font-bold px-6 py-2.5 rounded-md hover:bg-primary-100 transition shadow-sm">Save Settings</button>
                        </form>
                    </div>
                    
                    </div>
            </div>
        </div>

    </main>

    <div id="reviewModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-6xl h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <div><h3 class="text-lg font-bold text-gray-900">Review Schedule Batch</h3><p class="text-xs text-gray-500 font-mono" id="review_subtitle">...</p></div>
                <div class="flex gap-3">
                    <input type="hidden" id="review_dept_id"><input type="hidden" id="review_range">
                    <button onclick="updateBatchStatus('Pending CEO')" id="btn_approve_hr" class="hidden px-4 py-2 bg-primary-50 text-primary-700 border border-primary-200 rounded-md text-sm font-bold hover:bg-primary-100 shadow-sm transition">Approve (HR)</button>
                    <button onclick="updateBatchStatus('Approved')" id="btn_approve_ceo" class="hidden px-4 py-2 bg-primary-50 text-primary-700 border border-primary-200 rounded-md text-sm font-bold hover:bg-primary-100 shadow-sm transition">Final Approve</button>
                    <button onclick="updateBatchStatus('Rejected')" class="px-4 py-2 bg-red-50 text-red-600 border border-red-100 rounded-md text-sm font-bold hover:bg-red-100 transition">Reject</button>
                    <button onclick="closeReviewModal()" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-bold hover:bg-gray-50 transition">Close</button>
                </div>
            </div>
            <div class="flex-1 overflow-auto p-0 bg-white">
                <table class="w-full text-center text-xs border-collapse schedule-table">
                    <thead id="review_head"></thead>
                    <tbody class="divide-y divide-gray-100" id="review_body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // === TABS ===
        function switchTab(tabId) {
            document.querySelectorAll('.tab-view').forEach(el => el.classList.add('hidden'));
            document.getElementById('view-' + tabId).classList.remove('hidden');
            document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
            document.getElementById('nav-' + tabId).classList.add('active');
            if(tabId === 'approvals') loadBatchApprovals();
        }

        function switchPaySub(subId) {
            document.querySelectorAll('.pay-sub-view').forEach(el => el.classList.add('hidden'));
            document.getElementById('pay-sub-' + subId).classList.remove('hidden');
            document.querySelectorAll('.pay-sub-link').forEach(el => el.classList.remove('active'));
            document.getElementById('sub-nav-' + subId).classList.add('active');
        }

        // === CHART INITIALIZATION (DASHBOARD) - Updated Colors ===
        window.addEventListener('load', function() {
            // Palette Colors
            const c1 = '#a3e635'; // Primary
            const c2 = '#3b82f6'; // Blue
            const c3 = '#f472b6'; // Pink
            const c4 = '#cbd5e1'; // Slate

            const ctxDept = document.getElementById('deptChart').getContext('2d');
            const gradient = ctxDept.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(163, 230, 53, 0.4)'); gradient.addColorStop(1, 'rgba(163, 230, 53, 0)');
            
            new Chart(ctxDept, {
                type: 'line',
                data: {
                    labels: <?php echo $deptLabels; ?>,
                    datasets: [{ label: 'Employees', data: <?php echo $deptCounts; ?>, borderColor: c1, backgroundColor: gradient, borderWidth: 3, tension: 0.4, fill: true, pointRadius: 4, pointBackgroundColor: '#fff', pointBorderColor: c1 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { borderDash: [2, 4], color: '#f3f4f6' } }, x: { grid: { display: false } } } }
            });

            const ctxStatus = document.getElementById('statusChart').getContext('2d');
            new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: <?php echo $statusLabels; ?>,
                    datasets: [{ data: <?php echo $statusCounts; ?>, backgroundColor: [c2, c1, c3, c4], borderWidth: 0, hoverOffset: 5 }]
                },
                options: { cutout: '80%', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });

            // --- GENDER CHARTS ---
            const ctxGenderDonut = document.getElementById('genderDonutChart').getContext('2d');
            new Chart(ctxGenderDonut, {
                type: 'doughnut',
                data: {
                    labels: ['Male', 'Female'],
                    datasets: [{ data: [<?php echo $maleCount; ?>, <?php echo $femaleCount; ?>], backgroundColor: [c2, c3], borderWidth: 0, hoverOffset: 4 }]
                },
                options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false } } }
            });

            const ctxGenderBar = document.getElementById('genderBarChart').getContext('2d');
            new Chart(ctxGenderBar, {
                type: 'bar',
                data: {
                    labels: ['Male', 'Female'],
                    datasets: [{ label: 'Count', data: [<?php echo $maleCount; ?>, <?php echo $femaleCount; ?>], backgroundColor: [c2, c3], borderRadius: 6, barThickness: 40 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { display: false, grid: { display: false } }, x: { grid: { display: false } } } }
            });
        });

        // === APPROVALS LOGIC (Same JS as before, just class updates in HTML injection) ===
        function loadBatchApprovals() {
            $.post('', { action: 'get_pending_batches' }, function(res) {
                const data = JSON.parse(res).data;
                let html = '';
                if(data.length > 0) {
                    data.forEach(d => {
                        html += `<div class="bg-white border border-gray-200 p-4 rounded-lg flex flex-col justify-between h-32 hover:shadow-md transition">
                            <div><h4 class="font-bold text-gray-900">${d.dept_name}</h4><p class="text-xs text-gray-500 font-mono">${d.display}</p></div>
                            <button onclick="openReviewModal('${d.dept_id}', '${d.range_val}', '${d.dept_name}')" class="w-full py-2 bg-white border border-gray-800 text-gray-800 text-xs font-bold rounded-md hover:bg-gray-50 transition">Review Batch</button>
                        </div>`;
                    });
                } else {
                    html = '<p class="col-span-full text-gray-400 text-sm text-center py-4">No schedule batches pending your approval.</p>';
                }
                $('#batch_approval_list').html(html);
            });
        }

        function openReviewModal(deptId, range, deptName) {
            $('#review_dept_id').val(deptId);
            $('#review_range').val(range);
            $('#review_subtitle').text(`${deptName} | ${range}`);
            $.post('', { action: 'get_employees_by_dept', dept_id: deptId }, function(res1) {
                const emps = JSON.parse(res1).employees;
                $.post('', { action: 'get_schedules', dept_id: deptId, range: range }, function(res2) {
                    const data = JSON.parse(res2);
                    $('#btn_approve_hr').addClass('hidden');
                    $('#btn_approve_ceo').addClass('hidden');
                    if(data.can_approve_hr) $('#btn_approve_hr').removeClass('hidden');
                    if(data.can_approve_ceo) $('#btn_approve_ceo').removeClass('hidden');
                    renderGrid('#review_head', '#review_body', emps, data.schedules, range);
                    $('#reviewModal').removeClass('hidden');
                });
            });
        }

        function updateBatchStatus(status) {
            if(!confirm('Confirm action: ' + status + '?')) return;
            $.post('', { action: 'update_status', status: status, dept_id: $('#review_dept_id').val(), range: $('#review_range').val() }, function() {
                closeReviewModal();
                loadBatchApprovals();
            });
        }

        function closeReviewModal() { $('#reviewModal').addClass('hidden'); }

        function loadGlobalGrid() {
            const dept = $('#viewer_dept').val();
            const range = $('#viewer_range').val();
            if(!dept) { alert('Select Department'); return; }
            $('#viewer_body').html('<tr><td colspan="15" class="p-10 text-gray-400 font-medium animate-pulse">Loading schedule data...</td></tr>');
            $.post('', { action: 'get_employees_by_dept', dept_id: dept }, function(res1) {
                const emps = JSON.parse(res1).employees;
                $.post('', { action: 'get_schedules', dept_id: dept, range: range }, function(res2) {
                    const data = JSON.parse(res2);
                    renderGrid('#viewer_head', '#viewer_body', emps, data.schedules, range);
                });
            });
        }

        function renderGrid(headId, bodyId, emps, schedules, range) {
            const [sStr, eStr] = range.split('|');
            const start = new Date(sStr); const end = new Date(eStr);
            let headHtml = '<tr class="h-10"><th>Employee</th>';
            for(let d = new Date(start); d <= end; d.setDate(d.getDate()+1)) {
                headHtml += `<th class="px-1 min-w-[60px] border-l border-gray-100">${d.getDate()}<br><span class="text-[9px] font-normal text-gray-400">${d.toLocaleDateString('en-US',{weekday:'short'})}</span></th>`;
            }
            $(headId).html(headHtml + '</tr>');
            let bodyHtml = '';
            emps.forEach(e => {
                bodyHtml += `<tr class="hover:bg-gray-50 h-10"><td class="p-2 text-left text-xs font-bold text-gray-700 whitespace-nowrap border-b border-gray-100 bg-white sticky left-0 z-20 shadow-[1px_0_0_0_rgba(229,231,235,1)]">${e.name}</td>`;
                for(let d = new Date(start); d <= end; d.setDate(d.getDate()+1)) {
                    const dStr = d.toISOString().split('T')[0];
                    const cell = (schedules[e.id] && schedules[e.id][dStr]) ? schedules[e.id][dStr] : {code:'-', time:'-'};
                    let bg = 'bg-white'; let txt = 'text-gray-300'; let border = 'border-gray-100';
                    if(['OFF','HOLIDAY OFF'].includes(cell.code)) { bg='bg-orange-50'; txt='text-orange-600 font-bold'; }
                    else if(['LWOP','LWP'].includes(cell.code)) { bg='bg-red-50'; txt='text-red-600 font-bold'; }
                    else if(cell.code !== '-') { bg='bg-green-50/30'; txt='text-green-700 font-bold'; }
                    bodyHtml += `<td class="border-b border-l ${border} p-1 ${bg} ${txt} transition-colors"><div class="text-[10px]">${cell.code}</div>${(cell.time !== '-' && cell.time !== cell.code) ? `<div class="text-[9px] opacity-60 font-normal">${cell.time}</div>` : ''}</td>`;
                }
                bodyHtml += '</tr>';
            });
            $(bodyId).html(bodyHtml);
        }
    </script>
</body>
</html>