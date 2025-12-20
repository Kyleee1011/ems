<?php
session_start();

// ============================================
// 1. INCLUDES & INTEGRITY CHECKS
// ============================================
$required_files = [
    'payslip/get_sss.php',
    'payslip/get_philhealth.php',
    'payslip/get_pagibig.php',
    'payslip/get_tax.php',
    'payslip/get_nightdiff.php',
    'payslip/get_overtime.php',
    'payslip/get_undertime.php'
];

$missing_files = [];
foreach ($required_files as $file) {
    if (file_exists($file)) {
        require_once $file;
    } else {
        $missing_files[] = $file;
    }
}

if(file_exists('payslip/get_holiday.php')) {
    require_once 'payslip/get_holiday.php';
} else {
    function getHolidayType($conn, $date) { return 'REGULAR_DAY'; } 
}

if (!empty($missing_files)) {
    die("<div style='color:red; font-family:sans-serif; padding:20px; border:1px solid red; background:#ffebeb;'>
            <strong>System Error:</strong> Missing files: " . implode(', ', $missing_files) . "
         </div>");
}

function getDBConnection() {
    $serverName = "192.168.21.52,1433"; 
    $database = "SchedulerDB"; 
    $username = "sa"; 
    $password = "Azzurro2025"; 
    try {
        $conn = new PDO("sqlsrv:server=$serverName;Database=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) { die("System DB Connection failed."); }
}

function getBiologsConnection() {
    $serverName = "192.168.21.52,1433"; 
    $database = "biologs_db"; 
    $username = "sa"; 
    $password = "Azzurro2025"; 
    try {
        $conn = new PDO("sqlsrv:server=$serverName;Database=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) { die("Biologs DB Connection failed."); }
}

// ============================================
// 2. AUTH & SETUP
// ============================================
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$conn = getDBConnection();
$bioConn = getBiologsConnection();
$is_hr = ($_SESSION['approval_role'] ?? 'Employee') === 'HR';
$my_ac_no = $_SESSION['ac_no'];

$target_ac_no = $my_ac_no;
if ($is_hr && isset($_GET['search_ac']) && !empty($_GET['search_ac'])) {
    $target_ac_no = $_GET['search_ac'];
}

// Fetch Employee List (HR Only)
$empList = [];
if ($is_hr) {
    $stmtList = $conn->query("SELECT ac_no, first_name, last_name, job_title FROM [EmployeeManagementSystem].[dbo].[Employees] ORDER BY last_name");
    $empList = $stmtList->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================
// 3. PERIOD GENERATION
// ============================================
$cutoffs = [];
$current = new DateTime();
$current->modify('-4 months'); 

for ($i = 0; $i < 8; $i++) { 
    $year = $current->format('Y');
    $month = $current->format('m');
    
    // --- Period 1: 8th to 22nd ---
    $p1_start = "$year-$month-08";
    $p1_end   = "$year-$month-22";
    
    $cutoffs[] = [
        'val' => "$p1_start|$p1_end", 
        'label' => date('M 08', strtotime($p1_start)) . " - " . date('M 22, Y', strtotime($p1_end))
    ];

    // --- Period 2: 23rd to 7th (of the next month) ---
    $p2_start = "$year-$month-23";
    $nextMonthDate = clone $current;
    $nextMonthDate->modify('+1 month');
    $p2_end = $nextMonthDate->format('Y-m-07');
    $nextYearLabel = $nextMonthDate->format('Y');

    $cutoffs[] = [
        'val' => "$p2_start|$p2_end",
        'label' => date('M 23', strtotime($p2_start)) . " - " . date('M 07, Y', strtotime($p2_end))
    ];

    $current->modify('+1 month');
}
$cutoffs = array_reverse($cutoffs);

$sel_cutoff = $_GET['cutoff'] ?? $cutoffs[0]['val'];
list($start_date, $end_date) = explode('|', $sel_cutoff);

// ============================================
// 4. PAYROLL STATUS & LOAN POSTING LOGIC
// ============================================

// Check Current Status
$statusStmt = $conn->prepare("SELECT is_posted, updated_by FROM PayrollPeriodStatus WHERE start_date = ? AND end_date = ?");
$statusStmt->execute([$start_date, $end_date]);
$statusRow = $statusStmt->fetch(PDO::FETCH_ASSOC);
$is_posted = $statusRow ? (bool)$statusRow['is_posted'] : false;
$poster_name = $statusRow['updated_by'] ?? 'N/A';

// Handle Posting/Unposting (HR Only)
if ($is_hr && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_posting') {
    $new_status = $_POST['new_status']; // 1 = Post, 0 = Unpost
    
    $conn->beginTransaction();
    try {
        // 1. Update the Payroll Status Flag
        $checkStmt = $conn->prepare("SELECT id FROM PayrollPeriodStatus WHERE start_date = ? AND end_date = ?");
        $checkStmt->execute([$start_date, $end_date]);
        if ($checkStmt->fetch()) {
            $conn->prepare("UPDATE PayrollPeriodStatus SET is_posted = ?, updated_by = ?, updated_at = GETDATE() WHERE start_date = ? AND end_date = ?")->execute([$new_status, $_SESSION['full_name'], $start_date, $end_date]);
        } else {
            $conn->prepare("INSERT INTO PayrollPeriodStatus (start_date, end_date, is_posted, updated_by) VALUES (?, ?, ?, ?)")->execute([$start_date, $end_date, $new_status, $_SESSION['full_name']]);
        }

        // 2. PROCESS LOAN PAYMENTS (Accounting)
        $paymentDate = $end_date; 

        if ($new_status == '1') {
            // --- POSTING: CREATE DEDUCTIONS ---
            $stmtLoans = $conn->query("SELECT loan_id, per_cutoff_deduction, remaining_balance FROM [EmployeeManagementSystem].[dbo].[EmployeeLoans] WHERE status = 'Active' AND remaining_balance > 0");
            $activeLoans = $stmtLoans->fetchAll(PDO::FETCH_ASSOC);

            foreach ($activeLoans as $loan) {
                $chk = $conn->prepare("SELECT payment_id FROM [EmployeeManagementSystem].[dbo].[LoanPayments] WHERE loan_id = ? AND payment_date = ?");
                $chk->execute([$loan['loan_id'], $paymentDate]);
                if($chk->fetch()) continue; // Already paid in this period

                $amount = min($loan['per_cutoff_deduction'], $loan['remaining_balance']);
                
                if ($amount > 0) {
                    $insPay = $conn->prepare("INSERT INTO [EmployeeManagementSystem].[dbo].[LoanPayments] (loan_id, amount_paid, payment_date, notes) VALUES (?, ?, ?, 'Payroll Deduction')");
                    $insPay->execute([$loan['loan_id'], $amount, $paymentDate]);

                    $newBal = $loan['remaining_balance'] - $amount;
                    $status = ($newBal <= 0) ? 'Paid' : 'Active';
                    $updLoan = $conn->prepare("UPDATE [EmployeeManagementSystem].[dbo].[EmployeeLoans] SET remaining_balance = ?, status = ? WHERE loan_id = ?");
                    $updLoan->execute([$newBal, $status, $loan['loan_id']]);
                }
            }

        } else {
            // --- UNPOSTING: REVERSE DEDUCTIONS ---
            $getPay = $conn->prepare("SELECT payment_id, loan_id, amount_paid FROM [EmployeeManagementSystem].[dbo].[LoanPayments] WHERE payment_date = ? AND notes = 'Payroll Deduction'");
            $getPay->execute([$paymentDate]);
            $payments = $getPay->fetchAll(PDO::FETCH_ASSOC);

            foreach ($payments as $p) {
                $updRev = $conn->prepare("UPDATE [EmployeeManagementSystem].[dbo].[EmployeeLoans] SET remaining_balance = remaining_balance + ?, status = 'Active' WHERE loan_id = ?");
                $updRev->execute([$p['amount_paid'], $p['loan_id']]);

                $delPay = $conn->prepare("DELETE FROM [EmployeeManagementSystem].[dbo].[LoanPayments] WHERE payment_id = ?");
                $delPay->execute([$p['payment_id']]);
            }
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        die("Error processing payroll posting: " . $e->getMessage());
    }
    
    header("Location: payslip.php?cutoff=" . urlencode($sel_cutoff) . "&search_ac=" . urlencode($target_ac_no));
    exit;
}

$access_denied = (!$is_hr && !$is_posted);

$employee = null;
$breakdown = [];
$allowances = [];
$loans = [];
$net_pay = 0;

if (!$access_denied) {
    // --- Fetch Employee ---
    $stmt = $conn->prepare("SELECT emp_id, ac_no, first_name, last_name, salary_rate, daily_rate, hourly_rate, job_title, employee_status FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE ac_no = ?");
    $stmt->execute([$target_ac_no]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) die("<div class='p-4 text-red-600'>Employee record not found.</div>");

    $monthly_salary = $employee['salary_rate'];
    if ($employee['daily_rate'] > 0 && $employee['hourly_rate'] > 0) {
        $daily_rate = $employee['daily_rate'];
        $hourly_rate = $employee['hourly_rate'];
    } else {
        $workdays_month = 26.0833; 
        $daily_rate = $monthly_salary > 0 ? ($monthly_salary / $workdays_month) : 0;
        $hourly_rate = $daily_rate / 8;
    }
    $minute_rate = $hourly_rate / 60;

    // --- Fetch Loans (Display) ---
    $total_loan_deductions = 0;
    $stmtLoans = $conn->prepare("SELECT loan_category, description, per_cutoff_deduction, remaining_balance FROM [EmployeeManagementSystem].[dbo].[EmployeeLoans] WHERE emp_id = ? AND status = 'Active' AND remaining_balance > 0");
    $stmtLoans->execute([$employee['emp_id']]);
    while ($loan = $stmtLoans->fetch(PDO::FETCH_ASSOC)) {
        $deduction = min($loan['per_cutoff_deduction'], $loan['remaining_balance']);
        if ($deduction > 0) {
            $label = $loan['loan_category'] . (!empty($loan['description']) ? " (" . $loan['description'] . ")" : "");
            $loans[] = ['type' => $label, 'amount' => $deduction, 'balance' => $loan['remaining_balance'] - $deduction];
            $total_loan_deductions += $deduction;
        }
    }

    // --- Fetch Attendance ---
    $sched_map = [];
    $stmt = $conn->prepare("SELECT schedule_Date, Shift_code, Time_In, Time_Out FROM [SchedulerDB].[dbo].[FinalizedSchedule] WHERE Ac_no = ? AND schedule_Date BETWEEN ? AND ?");
    $stmt->execute([$target_ac_no, $start_date, $end_date]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = date('Y-m-d', strtotime($row['schedule_Date']));
        $sched_map[$d] = $row;
    }

    $logs_map = [];
    $stmt = $bioConn->prepare("SELECT CAST(LogTime AS DATE) as LogDate, MIN(LogTime) as Ti, MAX(LogTime) as ToVal FROM AttendanceData WHERE AC_No = ? AND CAST(LogTime AS DATE) BETWEEN ? AND ? GROUP BY CAST(LogTime AS DATE)");
    $stmt->execute([$target_ac_no, $start_date, $end_date]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = date('Y-m-d', strtotime($row['LogDate']));
        $logs_map[$d] = $row;
    }

    $adj_map = [];
    $stmt = $conn->prepare("SELECT adj_date, adj_type, time_value FROM TimecardAdjustments WHERE ac_no = ? AND adj_date BETWEEN ? AND ?");
    $stmt->execute([$target_ac_no, $start_date, $end_date]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = date('Y-m-d', strtotime($row['adj_date']));
        $adj_map[$d][$row['adj_type']] = $row['time_value'];
    }

    $breakdown = [
        'days_worked' => 0, 'days_absent' => 0,
        'hours_ot' => 0, 'mins_late' => 0, 'mins_ut' => 0,
        'pay_basic' => 0, 'pay_holiday' => 0, 'pay_overtime' => 0, 'pay_nightdiff' => 0,
        'deduct_late' => 0, 'deduct_ut' => 0
    ];

    $period = new DatePeriod(new DateTime($start_date), new DateInterval('P1D'), (new DateTime($end_date))->modify('+1 day'));

    foreach ($period as $dt) {
        $dateStr = $dt->format('Y-m-d');
        $sched = $sched_map[$dateStr] ?? null;
        $log = $logs_map[$dateStr] ?? null;
        $adj = $adj_map[$dateStr] ?? null;
        
        $holidayType = function_exists('getHolidayType') ? getHolidayType($conn, $dateStr) : 'REGULAR_DAY'; 

        $aIn = ($log && $log['Ti']) ? new DateTime($log['Ti']) : null;
        $aOut = ($log && $log['ToVal'] && $log['ToVal'] != $log['Ti']) ? new DateTime($log['ToVal']) : null;
        if ($adj && isset($adj['IN'])) $aIn = new DateTime($dateStr . ' ' . $adj['IN']);
        if ($adj && isset($adj['OUT'])) $aOut = new DateTime($dateStr . ' ' . $adj['OUT']);

        // --- FIX: Treat 00:00 as NULL (No Log) ---
        if ($aIn && $aIn->format('H:i') === '00:00') $aIn = null;
        if ($aOut && $aOut->format('H:i') === '00:00') $aOut = null;

        $sIn = null; $sOut = null;
        if ($sched && $sched['Time_In']) {
            $tVal = $sched['Time_In'];
            $tStr = ($tVal instanceof DateTime) ? $tVal->format('H:i:s') : date('H:i:s', strtotime($tVal));
            $sIn = new DateTime("$dateStr $tStr");
        }
        if ($sched && $sched['Time_Out']) {
            $tVal = $sched['Time_Out'];
            $tStr = ($tVal instanceof DateTime) ? $tVal->format('H:i:s') : date('H:i:s', strtotime($tVal));
            $sOut = new DateTime("$dateStr $tStr");
        }

        if($sIn && $sOut && $sOut < $sIn) $sOut->modify('+1 day');
        if($aIn && $aOut && $aOut < $aIn) $aOut->modify('+1 day');

        $is_scheduled_workday = ($sched && !in_array($sched['Shift_code'], ['OFF', 'FLEX', 'HOLIDAY OFF', 'LWOP', '-']));

        // --- ATTENDANCE LOGIC ---
        if ($aIn) {
            // PRESENT
            $breakdown['days_worked']++;
            $breakdown['pay_basic'] += $daily_rate;

            if ($holidayType == 'REGULAR') $breakdown['pay_holiday'] += $daily_rate; 
            elseif ($holidayType == 'SPECIAL') $breakdown['pay_holiday'] += ($daily_rate * 0.3);
            elseif ($holidayType == 'DOUBLE') $breakdown['pay_holiday'] += ($daily_rate * 2.0);

            $ot_hrs = 0;
            if($sOut && $aOut && $aOut > $sOut) {
                $ot_hrs = ($aOut->getTimestamp() - $sOut->getTimestamp()) / 3600;
            } elseif (!$sOut && $aOut) {
                $worked = ($aOut->getTimestamp() - $aIn->getTimestamp()) / 3600;
                if($worked > 9) $ot_hrs = $worked - 9;
            }
            if ($ot_hrs > 0) {
                $breakdown['hours_ot'] += $ot_hrs;
                $breakdown['pay_overtime'] += computeOvertimePay($ot_hrs, $hourly_rate, $holidayType);
            }

            if($sIn && $aIn > $sIn) {
                $l_mins = floor(($aIn->getTimestamp() - $sIn->getTimestamp()) / 60);
                if ($l_mins > 0) {
                    $breakdown['mins_late'] += $l_mins;
                    $breakdown['deduct_late'] += ($l_mins * $minute_rate);
                }
            }
            
            if($sOut && $aOut) {
                $u_mins = calculateUndertimeMinutes($sOut, $aOut);
                if ($u_mins > 0) {
                    $breakdown['mins_ut'] += $u_mins;
                    $breakdown['deduct_ut'] += computeUndertimeDeduction($u_mins, $minute_rate);
                }
            }

            $breakdown['pay_nightdiff'] += calculateNightDiffAmount($aIn, $aOut, $hourly_rate);

        } else {
            // ABSENT or NON-WORKING
            if ($is_scheduled_workday && $holidayType === 'REGULAR_DAY') {
                $breakdown['days_absent']++;
            } elseif ($holidayType == 'REGULAR') {
                $breakdown['pay_basic'] += $daily_rate; // Paid Holiday
            }
        }
    }

    // --- 5d. Allowances (DEDUCT 65 per ABSENT) ---
    $total_allowance_pay = 0;
    $stmtAllow = $conn->prepare("
        SELECT t.name, t.amount, t.deduction_per_absent, t.frequency 
        FROM Payroll_EmployeeAllowances ea
        JOIN Payroll_AllowanceTypes t ON ea.allowance_id = t.allowance_id
        WHERE ea.emp_id = ? AND t.is_active = 1
    ");
    $stmtAllow->execute([$employee['emp_id']]);

    while ($row = $stmtAllow->fetch(PDO::FETCH_ASSOC)) {
        $pay = 0;
        $notes = "";
        
        // Logic: Attendance Allowance (Semi-Monthly) gets deducted 65 per absent
        if ($row['frequency'] === 'Semi-Monthly') {
            $deduction = $breakdown['days_absent'] * 65.00;
            $pay = max(0, $row['amount'] - $deduction);
            if ($deduction > 0) {
                $notes = "(-" . number_format($deduction,0) . " for absents)";
            }
        } 
        elseif ($row['frequency'] === 'Daily') {
            $pay = $breakdown['days_worked'] * $row['amount'];
            $notes = "({$breakdown['days_worked']} days)";
        }
        
        if ($pay > 0) {
            $allowances[] = ['name' => $row['name'], 'amount' => $pay, 'notes' => $notes];
            $total_allowance_pay += $pay;
        }
    }

    // --- 5e. Totals ---
    $gross_pay = $breakdown['pay_basic'] + $breakdown['pay_holiday'] + $breakdown['pay_overtime'] + $breakdown['pay_nightdiff'] + $total_allowance_pay;
    $total_tardiness = $breakdown['deduct_late'] + $breakdown['deduct_ut'];

    // Govt Deductions (Once a Month Rule)
    $startDay = (int)date('d', strtotime($start_date));
    $isDeductionPeriod = ($startDay >= 23); 

    if ($isDeductionPeriod) {
        $cut_sss = function_exists('getSSSDeduction') ? getSSSDeduction($conn, $monthly_salary) : 0; 
        $cut_ph = function_exists('getPhilHealthDeduction') ? getPhilHealthDeduction($conn,  $monthly_salary) : 0;
        $cut_pi = function_exists('getPagIBIGDeduction') ? getPagIBIGDeduction($conn, $monthly_salary) : 0;
    } else {
        $cut_sss = 0; $cut_ph = 0; $cut_pi = 0;
    }
    
    $cut_tax = function_exists('calculateTax') ? calculateTax($conn, $monthly_salary) : 0; 

    $total_gov_deductions = $cut_sss + $cut_ph + $cut_pi + $cut_tax;
    $total_deductions = $total_gov_deductions + $total_tardiness + $total_loan_deductions;
    $net_pay = $gross_pay - $total_deductions;
}
function fmt($n) { return number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip | EMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'], mono: ['"Roboto Mono"', 'monospace'] },
                    colors: {
                        primary: { DEFAULT: '#a3e635', 50: '#f7fee7', 100: '#ecfccb', 500: '#84cc16', 600: '#65a30d', foreground: '#1F1F1F' }
                    },
                    borderRadius: { lg: '0.625rem' }
                }
            }
        }
    </script>
    <style>
        body { background-color: #FAFAFA; color: #1F1F1F; }
        .stat-card { background: white; border: 1px solid #e5e7eb; border-radius: 0.625rem; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1); }
        .select2-container .select2-selection--single { height: 42px; padding: 6px; border-color: #d1d5db; border-radius: 0.5rem; }
        @media print {
            @page { size: A4 portrait; margin: 5mm; }
            body { background: white; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; zoom: 90%; }
            .no-print { display: none !important; }
            .payslip-container { box-shadow: none !important; border: 2px solid #000 !important; width: 100% !important; max-width: 200mm !important; margin: 0 auto !important; padding: 15px !important; page-break-inside: avoid; }
            .print-compact-y { margin-bottom: 4px !important; }
            .print-compact-gap { gap: 10px !important; }
            .print-compact-text { font-size: 10px !important; }
            table td { padding-top: 2px !important; padding-bottom: 2px !important; font-size: 11px !important; }
            .bg-gray-100 { background-color: #f3f4f6 !important; }
            .bg-gray-900 { background-color: #111827 !important; color: white !important; }
            .watermark-container img { width: 300px !important; opacity: 0.08 !important; }
        }
    </style>
</head>
<body class="p-6">

    <div class="w-full max-w-4xl mx-auto px-4 py-8 print:p-0">
        
        <div class="stat-card p-6 mb-6 no-print">
            <div class="stat-card p-6 mb-6 no-print">
    <div class="mb-4">
        <a href="index.php" class="inline-flex items-center gap-2 text-gray-400 hover:text-gray-900 transition-colors text-xs font-bold uppercase tracking-widest"><i class="fa-solid fa-arrow-left"></i> Return to Home</a>
    </div>
            <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                <div class="w-full md:w-auto flex-1">
                    <h1 class="text-xl font-bold text-gray-900 mb-2">Payslip Generator</h1>
                    
                    <div class="mb-4">
                        <?php if($is_posted): ?>
                            <span class="bg-primary-50 text-primary-700 text-xs font-bold px-2.5 py-1 rounded border border-primary-200 uppercase tracking-wide">
                                <i class="fa-solid fa-check-circle mr-1"></i> Posted
                            </span>
                            <span class="text-xs text-gray-400 ml-2">By <?php echo $poster_name; ?></span>
                        <?php else: ?>
                            <span class="bg-gray-100 text-gray-600 text-xs font-bold px-2.5 py-1 rounded border border-gray-200 uppercase tracking-wide">
                                <i class="fa-solid fa-pen-ruler mr-1"></i> Draft / Not Posted
                            </span>
                        <?php endif; ?>
                    </div>

                    <form method="GET" class="flex flex-col md:flex-row gap-4 mb-4">
                        <?php if ($is_hr): ?>
                        <div class="w-full md:w-72">
                            <label class="block text-xs font-bold text-gray-500 mb-1">Search Employee</label>
                            <select name="search_ac" id="employee_search" class="w-full" onchange="this.form.submit()">
                                <option value="<?php echo $_SESSION['ac_no']; ?>">-- My Payslip --</option>
                                <?php foreach ($empList as $emp): ?>
                                    <option value="<?php echo $emp['ac_no']; ?>" <?php echo ($target_ac_no == $emp['ac_no']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="w-full md:w-64">
                            <label class="block text-xs font-bold text-gray-500 mb-1">Period</label>
                            <select name="cutoff" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm bg-white" onchange="this.form.submit()">
                                <?php foreach ($cutoffs as $c): ?>
                                    <option value="<?php echo $c['val']; ?>" <?php echo ($sel_cutoff == $c['val']) ? 'selected' : ''; ?>>
                                        <?php echo $c['label']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>

                    <?php if ($is_hr): ?>
                        <form method="POST" onsubmit="return confirm('<?php echo $is_posted ? "Unposting will REVERSE loan deductions. Continue?" : "Posting will DEDUCT loans from balances. Continue?"; ?>');">
                            <input type="hidden" name="action" value="toggle_posting">
                            <input type="hidden" name="new_status" value="<?php echo $is_posted ? '0' : '1'; ?>">
                            <?php if($is_posted): ?>
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-bold underline">
                                    <i class="fa-solid fa-ban"></i> Unpost / Hide Payslips
                                </button>
                            <?php else: ?>
                                <button type="submit" class="bg-primary-500 hover:bg-primary-600 text-primary-foreground text-sm font-bold px-4 py-2 rounded shadow transition">
                                    <i class="fa-solid fa-bullhorn mr-1"></i> POST PAYROLL NOW
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if(!$access_denied): ?>
                <button onclick="window.print()" class="bg-gray-900 hover:bg-black text-white font-medium px-5 py-2.5 rounded-lg flex items-center gap-2 transition shadow-lg">
                    <i class="fa-solid fa-print"></i> Print (A4)
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($access_denied): ?>
            <div class="bg-white border-2 border-dashed border-gray-300 rounded-xl p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                    <i class="fa-regular fa-clock text-2xl text-gray-400"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Payslip Not Available Yet</h2>
                <p class="text-gray-500 max-w-md mx-auto">
                    The payroll for the period <strong><?php echo date('M d', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?></strong> is currently being processed. Please check back later once HR has posted it.
                </p>
            </div>
        <?php else: ?>
            <div class="bg-white border border-gray-300 payslip-container shadow-lg mx-auto p-8 relative overflow-hidden">
                <div class="watermark-container absolute inset-0 flex justify-center items-center pointer-events-none z-0">
                    <img src="azzurro1.png" class="w-full max-w-lg opacity-[0.08] grayscale" alt="Watermark">
                </div>

                <div class="relative z-10 border-b-2 border-gray-900 pb-4 mb-4 print-compact-y">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center gap-3">
                            <img src="azzurro1.png" alt="Logo" class="h-14 w-auto grayscale">
                            <div>
                                <h1 class="text-2xl font-bold tracking-tight text-gray-900">AZZURRO HOTEL</h1>
                                <p class="text-xs text-gray-500 uppercase tracking-widest">Official Payslip</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-semibold text-gray-500 uppercase">Period Covered</div>
                            <div class="text-lg font-bold text-gray-900"><?php echo date('M d', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?></div>
                        </div>
                    </div>
                </div>

                <div class="relative z-10 bg-gray-50/80 p-3 rounded-lg mb-4 border border-gray-200 backdrop-blur-sm print-compact-y">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm print-compact-text">
                        <div>
                            <span class="block text-[10px] text-gray-500 uppercase font-bold">Employee Name</span>
                            <span class="font-bold text-gray-900 text-base"><?php echo htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name']); ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-500 uppercase font-bold">ID Number</span>
                            <span class="font-mono text-gray-900 font-bold"><?php echo $employee['ac_no']; ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-500 uppercase font-bold">Designation</span>
                            <span class="text-gray-900 font-bold"><?php echo $employee['job_title'] ?: 'N/A'; ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-500 uppercase font-bold">Rates</span>
                            <div class="flex gap-3">
                                <span class="text-xs text-gray-600">Daily: <b><?php echo fmt($daily_rate); ?></b></span>
                                <span class="text-xs text-gray-600">Hourly: <b><?php echo fmt($hourly_rate); ?></b></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative z-10 grid grid-cols-1 md:grid-cols-2 gap-8 mb-4 print-compact-gap">
                    
                    <div>
                        <h3 class="text-xs font-bold text-primary-600 uppercase border-b-2 border-primary-500 pb-1 mb-2">Earnings</h3>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-dashed divide-gray-200">
                                <tr>
                                    <td class="py-2 text-gray-600">Basic Pay <span class="text-[10px] text-gray-400 ml-1">(<?php echo $breakdown['days_worked']; ?> days)</span></td>
                                    <td class="py-2 text-right font-mono font-medium"><?php echo fmt($breakdown['pay_basic']); ?></td>
                                </tr>
                                
                                <?php foreach($allowances as $allw): ?>
                                <tr>
                                    <td class="py-2 text-blue-600">
                                        <?php echo htmlspecialchars($allw['name']); ?>
                                        <span class="text-[9px] text-gray-400 block"><?php echo $allw['notes']; ?></span>
                                    </td>
                                    <td class="py-2 text-right font-mono font-medium text-blue-600"><?php echo fmt($allw['amount']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if($breakdown['pay_holiday'] > 0): ?>
                                <tr>
                                    <td class="py-2 text-gray-600">Holiday Premium</td>
                                    <td class="py-2 text-right font-mono font-medium"><?php echo fmt($breakdown['pay_holiday']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if($breakdown['pay_overtime'] > 0): ?>
                                <tr>
                                    <td class="py-2 text-gray-600">Overtime <span class="text-[10px] text-gray-400 ml-1">(<?php echo fmt($breakdown['hours_ot']); ?> hrs)</span></td>
                                    <td class="py-2 text-right font-mono font-medium"><?php echo fmt($breakdown['pay_overtime']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if($breakdown['pay_nightdiff'] > 0): ?>
                                <tr>
                                    <td class="py-2 text-gray-600">Night Differential</td>
                                    <td class="py-2 text-right font-mono font-medium"><?php echo fmt($breakdown['pay_nightdiff']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="pt-2 font-bold text-gray-900 text-xs uppercase">Total Earnings</td>
                                    <td class="pt-2 text-right font-bold font-mono text-gray-900"><?php echo fmt($gross_pay); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div>
                        <h3 class="text-xs font-bold text-red-600 uppercase border-b-2 border-red-500 pb-1 mb-2">Deductions</h3>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-dashed divide-gray-200">
                                <?php if($breakdown['deduct_late'] > 0): ?>
                                <tr>
                                    <td class="py-2 text-red-600">Late <span class="text-[10px] text-red-400 ml-1">(<?php echo $breakdown['mins_late']; ?> mins)</span></td>
                                    <td class="py-2 text-right font-mono text-red-600"><?php echo fmt($breakdown['deduct_late']); ?></td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if($breakdown['deduct_ut'] > 0): ?>
                                <tr>
                                    <td class="py-2 text-red-600">Undertime <span class="text-[10px] text-red-400 ml-1">(<?php echo $breakdown['mins_ut']; ?> mins)</span></td>
                                    <td class="py-2 text-right font-mono text-red-600"><?php echo fmt($breakdown['deduct_ut']); ?></td>
                                </tr>
                                <?php endif; ?>

                                <tr>
                                    <td class="py-2 text-gray-600">SSS Contribution</td>
                                    <td class="py-2 text-right font-mono"><?php echo fmt($cut_sss); ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">PhilHealth</td>
                                    <td class="py-2 text-right font-mono"><?php echo fmt($cut_ph); ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">Pag-IBIG</td>
                                    <td class="py-2 text-right font-mono"><?php echo fmt($cut_pi); ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">Withholding Tax</td>
                                    <td class="py-2 text-right font-mono"><?php echo fmt($cut_tax); ?></td>
                                </tr>

                                <?php foreach($loans as $loan): ?>
                                <tr>
                                    <td class="py-2 text-red-600 italic">
                                        <?php echo htmlspecialchars($loan['type']); ?>
                                        <span class="text-[10px] text-gray-400 ml-1 block">Bal: <?php echo fmt($loan['balance']); ?></span>
                                    </td>
                                    <td class="py-2 text-right font-mono font-semibold text-red-600"><?php echo fmt($loan['amount']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            <tfoot>
                                <tr>
                                    <td class="pt-2 font-bold text-gray-900 text-xs uppercase">Total Deductions</td>
                                    <td class="pt-2 text-right font-bold font-mono text-red-600">(<?php echo fmt($total_deductions); ?>)</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="relative z-10 bg-gray-900 text-white p-4 rounded-lg flex flex-row justify-between items-center shadow-md print:bg-gray-900 print:text-white print-compact-y">
                    <div class="text-left">
                        <p class="text-xs text-gray-400 uppercase tracking-widest">Net Pay</p>
                        <p class="text-[10px] text-gray-500">Amount Received</p>
                    </div>
                    <div class="text-3xl font-bold font-mono tracking-tight">
                        <span class="text-lg align-top mr-1">PHP</span><?php echo fmt($net_pay); ?>
                    </div>
                </div>

                <div class="relative z-10 mt-8 grid grid-cols-2 gap-20 print:mt-4 print:gap-12">
                    <div class="text-center">
                        <div class="border-b border-gray-400 w-full mb-2"></div>
                        <p class="text-[10px] font-bold text-gray-500 uppercase">Employee Signature</p>
                    </div>
                    <div class="text-center">
                        <div class="border-b border-gray-400 w-full mb-2"></div>
                        <p class="text-[10px] font-bold text-gray-500 uppercase">Authorized By (HR)</p>
                    </div>
                </div>

                <div class="relative z-10 mt-4 text-center text-[9px] text-gray-400">
                    System Generated: <?php echo date('Y-m-d H:i:s'); ?> | <?php echo $_SESSION['full_name']; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#employee_search').select2({
                placeholder: "Type name to search...",
                allowClear: false
            });
        });
    </script>
</body>
</html>