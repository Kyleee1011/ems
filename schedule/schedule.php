<?php
session_start();

// ============================================
// 1. CONFIGURATION & DATABASE
// ============================================
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', 0); // Keep off for production, but logs will catch errors

$serverName = "192.168.21.52, 1433"; 
$database = "SchedulerDB";
$username = "sa"; 
$password = "Azzurro2025"; 

$connectionOptions = array(
    "Database" => $database,
    "Uid" => $username,
    "PWD" => $password,
    "CharacterSet" => "UTF-8"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

// ============================================
// 2. HELPER FUNCTIONS
// ============================================
function jsonResponse($success, $message = '', $data = []) {
    // Clear buffer to ensure no previous HTML interferes with JSON
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

if ($conn === false) {
    // If AJAX request, return JSON error
    if (isset($_POST['action'])) {
        jsonResponse(false, "Database Connection Failed.");
    } else {
        die("Database Connection Failed.");
    }
}

function getDynamicCutoffs() {
    $cutoffs = [];
    $current = new DateTime();
    $current->modify('-3 months'); 

    for ($i = 0; $i < 8; $i++) { 
        $year = $current->format('Y');
        $month = $current->format('m');
        
        // --- Period 1: 8th to 22nd ---
        $p1_start = "$year-$month-08";
        $p1_end   = "$year-$month-22";
        
        $cutoffs[] = [
            'value' => "$p1_start|$p1_end", 
            'label' => date('M d', strtotime($p1_start)) . " - " . date('M d', strtotime($p1_end)) . ", $year"
        ];

        // --- Period 2: 23rd to 7th (of the next month) ---
        $p2_start = "$year-$month-23";
        $nextMonthDate = clone $current;
        $nextMonthDate->modify('+1 month');
        $p2_end = $nextMonthDate->format('Y-m-07');
        $nextYearLabel = $nextMonthDate->format('Y');

        $cutoffs[] = [
            'value' => "$p2_start|$p2_end",
            'label' => date('M d', strtotime($p2_start)) . " - " . date('M d', strtotime($p2_end)) . ", $nextYearLabel"
        ];

        $current->modify('+1 month');
    }
    return $cutoffs;
}

function logHistory($conn, $batch_id, $action, $name, $role, $comments) {
    $sql = "INSERT INTO ScheduleHistory (batch_id, action, actor_name, actor_role, comments) VALUES (?, ?, ?, ?, ?)";
    sqlsrv_query($conn, $sql, array($batch_id, $action, $name, $role, $comments));
}

function getDeptSignatory($conn, $deptName) {
    $sql = "SELECT TOP 1 (first_name + ' ' + last_name) as name, signature_path 
            FROM [EmployeeManagementSystem].[dbo].[Employees] e
            JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON e.dept_id = d.dept_id
            WHERE d.dept_name = ? AND e.approval_role = 'DeptHead' AND e.IsActive = 1";
    
    $stmt = sqlsrv_query($conn, $sql, array($deptName));
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if (!empty($row['signature_path'])) {
            $row['signature_path'] = '../' . $row['signature_path'];
        }
        return $row;
    }

    $sqlFallback = "SELECT TOP 1 (first_name + ' ' + last_name) as name, signature_path 
            FROM [EmployeeManagementSystem].[dbo].[Employees] e
            JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON e.dept_id = d.dept_id
            WHERE d.dept_name = ? AND e.IsActive = 1";
    
    $stmtFallback = sqlsrv_query($conn, $sqlFallback, array($deptName));
    if ($rowFallback = sqlsrv_fetch_array($stmtFallback, SQLSRV_FETCH_ASSOC)) {
        if (!empty($rowFallback['signature_path'])) {
            $rowFallback['signature_path'] = '../' . $rowFallback['signature_path'];
        }
        return $rowFallback;
    }

    return ['name' => $deptName . ' Head', 'signature_path' => null];
}

// ============================================
// 3. INITIAL SETUP
// ============================================
$cutoff_options = getDynamicCutoffs();
$today = date('Y-m-d');
$default_cutoff = "";
foreach($cutoff_options as $opt) {
    $dates = explode('|', $opt['value']);
    if($today >= $dates[0] && $today <= $dates[1]) {
        $default_cutoff = $opt['value'];
        break;
    }
}
if(empty($default_cutoff)) $default_cutoff = $cutoff_options[3]['value'] ?? ''; 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_fullname = $_SESSION['full_name'];
$user_role = trim($_SESSION['approval_role'] ?? 'Employee'); 
$user_dept = $_SESSION['dept_id'] ?? 0;

$is_head = (strcasecmp($user_role, 'DeptHead') === 0);
$is_hr   = (strcasecmp($user_role, 'HR') === 0);
$is_ceo  = (strcasecmp($user_role, 'CEO') === 0);
$is_admin_view = ($is_hr || $is_ceo); 

$sqlUserSig = "SELECT signature_path FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE emp_id = ?";
$stmtUserSig = sqlsrv_query($conn, $sqlUserSig, array($current_user_id));
$currentUserSigPath = "";
if($r = sqlsrv_fetch_array($stmtUserSig, SQLSRV_FETCH_ASSOC)) {
    if (!empty($r['signature_path'])) {
        $currentUserSigPath = '../' . $r['signature_path'];
    }
}

// ============================================
// 4. AJAX HANDLERS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- FIX APPLIED HERE ---
    if ($_POST['action'] === 'get_pending_approvals') {
        $target_status = '';
        if ($is_hr) $target_status = 'Pending HR';
        if ($is_ceo) $target_status = 'Pending CEO';

        if ($target_status === '') {
            jsonResponse(true, '', ['approvals' => []]);
        }

        $sql = "SELECT b.batch_id, b.dept_id, d.dept_name, b.cutoff_label, b.cutoff_start, b.cutoff_end, b.updated_at, 
                       (SELECT COUNT(*) FROM EmployeeSchedules es WHERE es.batch_id = b.batch_id) as emp_count
                FROM ScheduleBatches b
                JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON b.dept_id = d.dept_id
                WHERE b.status = ?
                ORDER BY b.updated_at DESC";
        
        $stmt = sqlsrv_query($conn, $sql, array($target_status));
        
        if ($stmt === false) {
            jsonResponse(false, 'Query failed: ' . print_r(sqlsrv_errors(), true));
        }

        $approvals = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // FIX 1: Safely handle DateTime objects for concatenation
            $start = ($row['cutoff_start'] instanceof DateTime) ? $row['cutoff_start']->format('Y-m-d') : $row['cutoff_start'];
            $end   = ($row['cutoff_end'] instanceof DateTime) ? $row['cutoff_end']->format('Y-m-d') : $row['cutoff_end'];
            $range = $start . '|' . $end;

            // FIX 2: Safely handle updated_at if null or object
            $lastUpdated = '-';
            if ($row['updated_at'] instanceof DateTime) {
                $lastUpdated = $row['updated_at']->format('M d, Y h:i A');
            } elseif (!empty($row['updated_at'])) {
                $lastUpdated = (string)$row['updated_at'];
            }

            $approvals[] = [
                'dept_id' => $row['dept_id'],
                'dept_name' => $row['dept_name'],
                'cutoff_label' => $row['cutoff_label'],
                'range_value' => $range,
                'last_updated' => $lastUpdated,
                'count' => $row['emp_count']
            ];
        }
        jsonResponse(true, '', ['approvals' => $approvals]);
    }
    // --- END FIX ---

    if ($_POST['action'] === 'get_employees_by_dept') {
        $dept_id = $_POST['dept_id'];
        $sql = "SELECT emp_id, first_name, last_name, ac_no FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE dept_id = ? AND employee_status = 'Active' AND IsActive = 1 ORDER BY last_name";
        $stmt = sqlsrv_query($conn, $sql, array($dept_id));
        $emps = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $emps[] = [
                    'id' => $row['emp_id'], 
                    'ac_no' => $row['ac_no'], 
                    'name' => $row['first_name'] . ' ' . $row['last_name']
                ];
            }
        }
        jsonResponse(true, '', ['employees' => $emps]);
    }

    if ($_POST['action'] === 'get_schedules') {
        $dept_id = $_POST['dept_id'];
        $range = $_POST['range']; 
        list($dS, $dE) = explode('|', $range);
        
        $sqlBatch = "SELECT b.batch_id, b.status, 
                            e.first_name + ' ' + e.last_name as creator_name, 
                            e.signature_path as creator_sig_path,
                            d.dept_name
                     FROM ScheduleBatches b
                     LEFT JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON b.created_by = e.emp_id
                     LEFT JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON b.dept_id = d.dept_id
                     WHERE b.dept_id = ? AND b.cutoff_start = ?";
        
        $stmtBatch = sqlsrv_query($conn, $sqlBatch, array($dept_id, $dS));
        $batch = sqlsrv_fetch_array($stmtBatch, SQLSRV_FETCH_ASSOC);

        $status = $batch ? $batch['status'] : 'Not Started';
        $batch_id = $batch ? $batch['batch_id'] : 0;
        
        if ($batch) {
            $dept_name = $batch['dept_name'];
        } else {
            $sqlD = "SELECT dept_name FROM [EmployeeManagementSystem].[dbo].[Departments] WHERE dept_id = ?";
            $rD = sqlsrv_fetch_array(sqlsrv_query($conn, $sqlD, array($dept_id)), SQLSRV_FETCH_ASSOC);
            $dept_name = $rD['dept_name'];
        }
        
        $prepared_by = $batch ? $batch['creator_name'] : $current_fullname;
        $prepared_sig_path = null;
        if ($batch && !empty($batch['creator_sig_path'])) {
            $prepared_sig_path = '../' . $batch['creator_sig_path'];
        }

        $sqlHR = "SELECT TOP 1 (first_name + ' ' + last_name) as name, signature_path 
                  FROM [EmployeeManagementSystem].[dbo].[Employees] 
                  WHERE approval_role = 'HR' AND IsActive = 1";
        $stmtHR = sqlsrv_query($conn, $sqlHR);
        
        if ($rowHR = sqlsrv_fetch_array($stmtHR, SQLSRV_FETCH_ASSOC)) {
            $checked_by = $rowHR['name'];
            $checked_sig_path = !empty($rowHR['signature_path']) ? '../' . $rowHR['signature_path'] : null;
        } else {
            $checked_by = "HR Admin";
            $checked_sig_path = null;
        }

        $ownerSignatory = getDeptSignatory($conn, "Owner");
        $approved_by = $ownerSignatory['name'];
        $approved_sig_path = $ownerSignatory['signature_path'];

        $schedules = [];
        $hasFinalized = false;
        if ($status === 'Approved') {
            $sqlCheckF = "SELECT COUNT(*) as c FROM FinalizedSchedule fs 
                          JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON fs.Ac_no = e.ac_no 
                          WHERE e.dept_id = ? AND fs.schedule_Date BETWEEN ? AND ?";
            $stmtCheckF = sqlsrv_query($conn, $sqlCheckF, array($dept_id, $dS, $dE));
            $rowF = sqlsrv_fetch_array($stmtCheckF, SQLSRV_FETCH_ASSOC);
            if ($rowF['c'] > 0) {
                $hasFinalized = true;
            }
        }

        if ($status === 'Approved' && $hasFinalized) {
            $sqlSched = "SELECT e.emp_id as employee_id, fs.schedule_Date as schedule_date, fs.Shift_code, fs.Time_In, fs.Time_Out 
                         FROM FinalizedSchedule fs
                         JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON fs.Ac_no = e.ac_no
                         WHERE e.dept_id = ? AND fs.schedule_Date BETWEEN ? AND ?";
            $stmtSched = sqlsrv_query($conn, $sqlSched, array($dept_id, $dS, $dE));
        } else {
            $sqlSched = "SELECT es.employee_id, es.schedule_date, es.shift_code, st.time_in, st.time_out 
                         FROM EmployeeSchedules es
                         LEFT JOIN shift_types st ON es.shift_code = st.shift_code
                         WHERE es.batch_id = ? AND es.schedule_date BETWEEN ? AND ?";
            $stmtSched = sqlsrv_query($conn, $sqlSched, array($batch_id, $dS, $dE));
        }

        if (isset($stmtSched) && $stmtSched) {
            while ($row = sqlsrv_fetch_array($stmtSched, SQLSRV_FETCH_ASSOC)) {
                $dateObj = ($row['schedule_date'] instanceof DateTime) ? $row['schedule_date'] : new DateTime($row['schedule_date']);
                $dateKey = $dateObj->format('Y-m-d');
                
                $tIn = $row['Time_In'] ?? $row['time_in'] ?? null;
                $tOut = $row['Time_Out'] ?? $row['time_out'] ?? null;
                $code = $row['Shift_code'] ?? $row['shift_code'] ?? '-';
                
                $displayTime = ($tIn && $tOut) ? $tIn->format('h:iA') . '-' . $tOut->format('h:iA') : '-';
                
                if(in_array($code, ['OFF','FLEX','HOLIDAY OFF','LWOP','LWP'])) {
                    $displayTime = $code; 
                }

                $schedules[$row['employee_id']][$dateKey] = [
                    'code' => $code, 
                    'time' => $displayTime
                ];
            }
        }

        jsonResponse(true, '', [
            'status' => $status,
            'dept_name' => $dept_name,
            'signatories' => [
                'prepared' => $prepared_by,
                'prepared_sig' => $prepared_sig_path,
                'checked' => $checked_by,
                'checked_sig' => $checked_sig_path,
                'approved' => $approved_by,
                'approved_sig' => $approved_sig_path
            ],
            'can_edit' => ($is_head && ($status == 'Not Started' || $status == 'Draft' || $status == 'Rejected')),
            'can_reset' => ($status == 'Approved' && ($is_ceo || $is_hr)), 
            'is_hr' => $is_hr,
            'can_approve_hr' => ($is_hr && $status == 'Pending HR'),
            'can_approve_ceo' => ($is_ceo && $status == 'Pending CEO'),
            'schedules' => $schedules
        ]);
    }

    if ($_POST['action'] === 'save_schedule') {
        $emp_id = $_POST['employee_id'];
        $date = $_POST['schedule_date'];
        $code = $_POST['shift_code'];
        $dept_id = $_POST['dept_id'];
        $range = $_POST['range'];
        list($start, $end) = explode('|', $range);

        $sqlCheck = "SELECT batch_id FROM ScheduleBatches WHERE dept_id = ? AND cutoff_start = ?";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($dept_id, $start));
        $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);

        if ($row) {
            $batch_id = $row['batch_id'];
        } else {
            $sqlIns = "INSERT INTO ScheduleBatches (dept_id, cutoff_label, cutoff_start, cutoff_end, status, created_by) VALUES (?, ?, ?, ?, 'Draft', ?); SELECT SCOPE_IDENTITY() as id";
            $stmtIns = sqlsrv_query($conn, $sqlIns, array($dept_id, $start, $start, $end, $current_user_id));
            sqlsrv_next_result($stmtIns); 
            $rowIns = sqlsrv_fetch_array($stmtIns, SQLSRV_FETCH_ASSOC);
            $batch_id = $rowIns['id'];
            logHistory($conn, $batch_id, 'Created', $current_fullname, $user_role, 'Started draft schedule');
        }

        $sqlDel = "DELETE FROM EmployeeSchedules WHERE batch_id = ? AND employee_id = ? AND schedule_date = ?";
        sqlsrv_query($conn, $sqlDel, array($batch_id, $emp_id, $date));

        $sqlAdd = "INSERT INTO EmployeeSchedules (batch_id, employee_id, schedule_date, shift_code) VALUES (?, ?, ?, ?)";
        sqlsrv_query($conn, $sqlAdd, array($batch_id, $emp_id, $date, $code));

        jsonResponse(true);
    }

    if ($_POST['action'] === 'update_status') {
        $dept_id = $_POST['dept_id'];
        $range = $_POST['range'];
        list($start, $end) = explode('|', $range);
        $new_status = $_POST['status'];
        $comments = $_POST['comments'] ?? '';

        $sqlBatch = "SELECT batch_id FROM ScheduleBatches WHERE dept_id = ? AND cutoff_start = ?";
        $stmtBatch = sqlsrv_query($conn, $sqlBatch, array($dept_id, $start));
        $row = sqlsrv_fetch_array($stmtBatch, SQLSRV_FETCH_ASSOC);
        
        if ($row) {
            $batch_id = $row['batch_id'];
            
            $sqlUpd = "UPDATE ScheduleBatches SET status = ?, updated_at = GETDATE() WHERE batch_id = ?";
            sqlsrv_query($conn, $sqlUpd, array($new_status, $batch_id));

            if ($new_status == 'Pending HR') {
                $sqlCreator = "UPDATE ScheduleBatches SET created_by = ? WHERE batch_id = ?";
                sqlsrv_query($conn, $sqlCreator, array($current_user_id, $batch_id));
            }

            if ($new_status == 'Approved') {
                $sqlClean = "DELETE fs FROM FinalizedSchedule fs
                             JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON fs.Ac_no = e.ac_no
                             WHERE e.dept_id = ? AND fs.schedule_Date BETWEEN ? AND ?";
                sqlsrv_query($conn, $sqlClean, array($dept_id, $start, $end));

                $sqlMigrate = "
                    INSERT INTO FinalizedSchedule (Ac_no, schedule_Date, Shift_code, Time_In, Time_Out)
                    SELECT 
                        e.ac_no,
                        es.schedule_date,
                        es.shift_code,
                        st.time_in,
                        st.time_out
                    FROM EmployeeSchedules es
                    INNER JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON es.employee_id = e.emp_id
                    LEFT JOIN shift_types st ON es.shift_code = st.shift_code
                    WHERE es.batch_id = ?
                ";
                sqlsrv_query($conn, $sqlMigrate, array($batch_id));
            }

            if ($new_status == 'Draft') {
                $sqlReset = "DELETE fs FROM FinalizedSchedule fs
                             JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON fs.Ac_no = e.ac_no
                             WHERE e.dept_id = ? AND fs.schedule_Date BETWEEN ? AND ?";
                sqlsrv_query($conn, $sqlReset, array($dept_id, $start, $end));
            }

            $action = $new_status == 'Pending HR' ? 'Submitted' : $new_status;
            logHistory($conn, $batch_id, $action, $current_fullname, $user_role, $comments);
        }
        jsonResponse(true);
    }
}

// Data Loading and Categorization Logic
$depts = [];
$dept_stmt = sqlsrv_query($conn, "SELECT dept_id, dept_name FROM [EmployeeManagementSystem].[dbo].[Departments] ORDER BY dept_name");
if($dept_stmt) { while($d = sqlsrv_fetch_array($dept_stmt, SQLSRV_FETCH_ASSOC)) { $depts[] = $d; } }

// Define Groups
$grouped_shifts = [
    'AM Shifts (05:00 - 09:59)' => [],
    'Mid Shifts (10:00 - 13:59)' => [],
    'PM Shifts (14:00 - 17:59)' => [],
    'Graveyard Shifts (18:00+)' => [],
    'Leaves / Special' => []
];

$shift_stmt = sqlsrv_query($conn, "SELECT shift_code, shift_name, time_in, time_out, is_special FROM shift_types");
if($shift_stmt) { 
    while($s = sqlsrv_fetch_array($shift_stmt, SQLSRV_FETCH_ASSOC)) { 
        if ($s['is_special'] == 1) {
            $grouped_shifts['Leaves / Special'][] = $s;
        } else if ($s['time_in'] instanceof DateTime) {
            $H = (int)$s['time_in']->format('G');
            if ($H >= 5 && $H < 10) {
                $grouped_shifts['AM Shifts (05:00 - 09:59)'][] = $s;
            } else if ($H >= 10 && $H < 14) {
                $grouped_shifts['Mid Shifts (10:00 - 13:59)'][] = $s;
            } else if ($H >= 14 && $H < 18) {
                $grouped_shifts['PM Shifts (14:00 - 17:59)'][] = $s;
            } else {
                $grouped_shifts['Graveyard Shifts (18:00+)'][] = $s;
            }
        }
    } 
}

$special_codes = ['OFF', 'FLEX', 'HOLIDAY OFF', 'LWOP', 'LWP'];
foreach($special_codes as $code) {
    $grouped_shifts['Leaves / Special'][] = [
        'shift_code' => $code,
        'shift_name' => $code,
        'time_in' => null,
        'time_out' => null
    ];
}

// Sort each group chronologically
foreach ($grouped_shifts as $key => &$items) {
    usort($items, function($a, $b) {
        if (!isset($a['time_in']) || !isset($b['time_in']) || $a['time_in'] == null || $b['time_in'] == null) return 0;
        return $a['time_in']->getTimestamp() - $b['time_in']->getTimestamp();
    });
}
unset($items);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Manager - A4 Document</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #0f766e; --bg: #f1f5f9; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); margin: 0; padding: 20px; color: #1e293b; }
        
        .header { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .controls { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 20px; align-items: flex-end; }
        
        /* TABS STYLING */
        .tab-nav { display: flex; gap: 10px; margin-bottom: 15px; }
        .tab-btn { padding: 10px 20px; border: none; background: white; border-radius: 8px; font-weight: 600; cursor: pointer; color: #64748b; transition: 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .tab-btn.active { background: var(--primary); color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* DASHBOARD STYLING */
        .dashboard-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .dashboard-table th { background: #f8fafc; text-align: left; padding: 15px; font-size: 0.85rem; color: #64748b; font-weight: 700; border-bottom: 1px solid #e2e8f0; }
        .dashboard-table td { padding: 15px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .dashboard-table tr:hover { background: #f1f5f9; }
        .dashboard-table tr:last-child td { border-bottom: none; }

        .document-page {
            width: 297mm; min-height: 210mm; background: white; margin: 0 auto;
            padding: 10mm 15mm; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); position: relative;
        }

        .doc-header { text-align: center; margin-bottom: 10px; }
        .doc-logo { width: 300px; display: block; margin: 0 auto 5px auto; } 
        .doc-info { text-align: left; font-size: 10pt; font-weight: 700; margin-bottom: 5px; }
        .doc-dept { text-transform: uppercase; font-weight: bold; margin-bottom: 10px; font-size: 11pt; text-decoration: underline; }

        .doc-table { width: 100%; border-collapse: collapse; font-size: 8pt; table-layout: fixed; }
        .doc-table th, .doc-table td { border: 1px solid black; padding: 2px; text-align: center; vertical-align: middle; word-wrap: break-word; }
        .doc-table th { background: transparent; font-weight: bold; font-size: 8pt; }
        .doc-table td { height: 35px; }
        .doc-table th:first-child, .doc-table td:first-child { text-align: left; width: 130px; padding-left: 5px; }

        .doc-footer { margin-top: 25px; display: flex; justify-content: space-between; page-break-inside: avoid; }
        .sign-box { width: 30%; }
        .sign-label { font-weight: bold; font-size: 10pt; margin-bottom: 5px; }
        .sign-area { 
            border-bottom: 1px solid black; min-height: 50px; 
            display: flex; flex-direction: column; justify-content: flex-end; align-items: center; 
            padding-bottom: 2px; font-weight: bold; text-transform: uppercase; font-size: 10pt; position: relative;
        }
        .sign-dept { font-size: 9pt; margin-top: 5px; text-align: center; }
        .signature-img { max-height: 80px; position: absolute; bottom: 5px; left: 25%; z-index: 10; }

        .cell-edit { cursor: pointer; }
        .cell-edit:hover { background: #e0f2fe; }
        
        .status-badge { padding: 4px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .status-Draft { background: #f1f5f9; color: #475569; }
        .status-Pending-HR { background: #fff7ed; color: #c2410c; }
        .status-Pending-CEO { background: #e0f2fe; color: #0284c7; }
        .status-Approved { background: #dcfce7; color: #166534; }
        
        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: 500; font-size: 0.9rem; transition: 0.2s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-approve { background: #059669; color: white; }

        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 50; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 25px; border-radius: 12px; width: 650px; max-width: 95%; max-height: 85vh; overflow-y: auto; }
        
        /* Modal Grid Groups */
        .shift-group-title { margin-top: 15px; margin-bottom: 8px; font-size: 0.85rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .shift-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
        .shift-card { border: 1px solid #e2e8f0; padding: 10px; text-align: center; border-radius: 6px; cursor: pointer; transition: 0.1s; }
        .shift-card:hover { border-color: var(--primary); background: #f0fdfa; }
        .shift-card.special { background: #fff7ed; font-weight: bold; color: #c2410c; }

        @media print {
            @page { size: A4 landscape; margin: 0; }
            body { background: white; padding: 0; margin: 0; }
            .header, .controls, #workflow_actions, .modal, .tab-nav, #tab_dashboard { display: none !important; }
            #tab_schedule { display: block !important; }
            .document-page { box-shadow: none; margin: 0; width: 100%; padding: 10mm 15mm; border: none; }
        }
    </style>
</head>
<body>

<div class="header">
    <div>
        <h1 style="margin:0; font-size:1.5rem;">Schedule Manager</h1>
        <small><?php echo $current_fullname; ?> (<?php echo $user_role; ?>)</small>
    </div>
    <a href="../index.php" class="btn btn-primary" style="text-decoration:none;">Back to Dashboard</a>
</div>

<?php if ($is_admin_view): ?>
<div class="tab-nav">
    <button class="tab-btn active" onclick="switchTab('dashboard')">Pending Approvals</button>
    <button class="tab-btn" onclick="switchTab('schedule')">Schedule View</button>
</div>
<?php endif; ?>

<div id="tab_dashboard" class="tab-content <?php echo $is_admin_view ? 'active' : ''; ?>">
    <div style="background:white; padding:20px; border-radius:12px;">
        <h3 style="margin-top:0;">Departments Needing Approval</h3>
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Cutoff Period</th>
                    <th>Date Submitted</th>
                    <th>Employees</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="approval_list">
                <tr><td colspan="5" style="text-align:center;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div id="tab_schedule" class="tab-content <?php echo !$is_admin_view ? 'active' : ''; ?>">
    <div class="controls">
        <div style="flex:1;">
            <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:5px;">Department</label>
            <?php if($is_hr || $is_ceo): ?>
                <select id="dept_select" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                    <option value="">-- Select --</option>
                    <?php foreach($depts as $d): ?><option value="<?php echo $d['dept_id']; ?>" <?php if($d['dept_id'] == $user_dept) echo 'selected'; ?>><?php echo $d['dept_name']; ?></option><?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="text" value="<?php foreach($depts as $d) { if($d['dept_id'] == $user_dept) echo $d['dept_name']; } ?>" readonly style="width:100%; padding:8px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px;">
                <input type="hidden" id="dept_select" value="<?php echo $user_dept; ?>">
            <?php endif; ?>
        </div>
        <div style="flex:1;">
            <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:5px;">Cutoff Period</label>
            <select id="cutoff_select" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                <?php foreach($cutoff_options as $opt): ?>
                    <option value="<?php echo $opt['value']; ?>" <?php if($opt['value'] === $default_cutoff) echo 'selected'; ?>><?php echo $opt['label']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="padding-bottom:5px;">
            <span id="status_display" class="status-badge status-Draft"></span>
        </div>
        <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
    </div>

    <div id="workflow_actions" style="margin-bottom:20px; display:none; justify-content:center; gap:10px;">
        <button id="btn_submit" class="btn btn-approve" onclick="handleTwoStepSubmit()" style="display:none;" data-step="1">
            <i class="fa-solid fa-pen-nib"></i> Sign & Submit
        </button>
        <button id="btn_approve_hr" class="btn btn-approve" onclick="updateStatus('Pending CEO')" style="display:none;">Approve (HR)</button>
        <button id="btn_approve_ceo" class="btn btn-approve" onclick="updateStatus('Approved')" style="display:none;">Final Approve</button>
        <button id="btn_reject" class="btn" style="background:#dc2626; color:white; display:none;" onclick="updateStatus('Rejected')">Reject</button>
        
        <button id="btn_reset" class="btn" style="background:#7f1d1d; color:white; display:none;" onclick="updateStatus('Draft')">
            <i class="fa-solid fa-lock-open"></i> Unlock / Reset to Draft
        </button>
    </div>

    <div class="document-page" id="document_container">
        <div class="doc-header">
            <img src="azzurro.png" alt="Azzurro Hotel" class="doc-logo">
        </div>

        <div class="doc-info" id="doc_cutoff_label">Cut-Off Period: ...</div>
        <div class="doc-dept" id="doc_dept_name">MIS DEPARTMENT</div>

        <table class="doc-table">
            <thead id="doc_table_head"></thead>
            <tbody id="doc_table_body">
                <tr><td colspan="100" style="padding:20px;">Select Department and Period to load data...</td></tr>
            </tbody>
        </table>

        <div style="font-size:8pt; font-style:italic; margin-top:5px;">Note: Subjected to changes for events/functions as required.</div>

        <div class="doc-footer">
            <div class="sign-box">
                <div class="sign-label">Prepared By:</div>
                <div class="sign-area" id="area_prepared">
                    <div id="sig_img_prepared"></div>
                    <span id="name_prepared"></span>
                </div>
                <div class="sign-dept" id="dept_prepared"></div>
            </div>
            <div class="sign-box">
                <div class="sign-label">Checked By:</div>
                <div class="sign-area" id="area_checked">
                     <div id="sig_img_checked"></div>
                     <span id="name_checked"></span>
                </div>
                <div class="sign-dept">Human Resources Department Head</div>
            </div>
            <div class="sign-box">
                <div class="sign-label">Approved By:</div>
                <div class="sign-area" id="area_approved">
                     <div id="sig_img_approved"></div>
                     <span id="name_approved"></span>
                </div>
                <div class="sign-dept">President & CEO</div>
            </div>
        </div>
    </div>
</div>

<div id="shiftModal" class="modal">
    <div class="modal-content">
        <h3>Select Shift</h3>
        <?php foreach ($grouped_shifts as $groupName => $shiftsInGroup): ?>
            <?php if (!empty($shiftsInGroup)): ?>
                <div class="shift-group-title"><?php echo $groupName; ?></div>
                <div class="shift-grid">
                    <?php foreach ($shiftsInGroup as $s): ?>
                        <div onclick="saveShift('<?php echo $s['shift_code']; ?>')" class="shift-card <?php echo ($groupName == 'Leaves / Special') ? 'special' : ''; ?>">
                            <?php if ($groupName == 'Leaves / Special'): ?>
                                <div><?php echo $s['shift_code']; ?></div>
                            <?php else: ?>
                                <strong><?php echo $s['shift_name']; ?></strong><br>
                                <small style="font-size:0.75rem; color:#64748b;">
                                    <?php echo $s['time_in'] ? $s['time_in']->format('H:i') . ' - ' . $s['time_out']->format('H:i') : ''; ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <button onclick="$('.modal').removeClass('active')" class="btn" style="width:100%; margin-top:20px; background:#e2e8f0;">Cancel</button>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let currentEditing = { empId: null, date: null };
let canEdit = false;
let currentUserSigPath = "<?php echo $currentUserSigPath; ?>"; 
let isAdminView = <?php echo $is_admin_view ? 'true' : 'false'; ?>;

$(document).ready(function() {
    if (isAdminView) {
        loadDashboard();
    } else {
        loadData();
    }
    
    $('#dept_select, #cutoff_select').on('change', function() { loadData(); });
});

function switchTab(tabName) {
    $('.tab-content').removeClass('active');
    $('.tab-btn').removeClass('active');
    $('#tab_' + tabName).addClass('active');
    
    if (tabName === 'dashboard') {
        $('.tab-btn:eq(0)').addClass('active');
        loadDashboard();
    } else {
        $('.tab-btn:eq(1)').addClass('active');
    }
}

function loadDashboard() {
    $.post('', { action: 'get_pending_approvals' }, function(res) {
        let html = '';
        if (res.approvals.length === 0) {
            html = '<tr><td colspan="5" style="text-align:center; padding:30px; color:#94a3b8;">No schedules currently pending your approval.</td></tr>';
        } else {
            res.approvals.forEach(function(row) {
                html += `
                    <tr>
                        <td style="font-weight:bold;">${row.dept_name}</td>
                        <td>${row.cutoff_label}</td>
                        <td>${row.last_updated}</td>
                        <td>${row.count} Employees</td>
                        <td>
                            <button class="btn btn-primary" onclick="reviewBatch(${row.dept_id}, '${row.range_value}')">
                                Review & Approve
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        $('#approval_list').html(html);
    });
}

function reviewBatch(deptId, rangeValue) {
    // 1. Set values
    $('#dept_select').val(deptId);
    $('#cutoff_select').val(rangeValue);
    
    // 2. Switch Tab
    switchTab('schedule');
    
    // 3. Load Data
    loadData();
}

function loadData() {
    const dept = $('#dept_select').val();
    const range = $('#cutoff_select').val(); 
    if(!dept) return;

    // Reset Submit Button State
    $('#btn_submit').data('step', 1).html('<i class="fa-solid fa-pen-nib"></i> Sign & Submit').removeClass('btn-primary').addClass('btn-approve');

    $.post('', { action: 'get_employees_by_dept', dept_id: dept }, function(res) {
        const employees = res.employees;
        $.post('', { action: 'get_schedules', dept_id: dept, range: range }, function(data) {
            
            const result = data;
            canEdit = result.can_edit;
            
            $('#status_display').text(result.status).attr('class', 'status-badge status-' + result.status.replace(/ /g, '-'));
            $('#doc_dept_name').text(result.dept_name.toUpperCase() + ' DEPARTMENT');
            $('#dept_prepared').text(result.dept_name + ' Department Head');

            renderDocTable(employees, result.schedules, range);

            const renderSig = (areaId, name, path, showSig) => {
                $(`#name_${areaId}`).text(name || '');
                if (showSig && path) {
                    $(`#sig_img_${areaId}`).html(`<img src="${path}" class="signature-img" alt="Sig">`);
                } else {
                    $(`#sig_img_${areaId}`).html('');
                }
            };

            const isDraft = (result.status === 'Draft' || result.status === 'Not Started' || result.status === 'Rejected');
            
            renderSig('prepared', result.signatories.prepared, result.signatories.prepared_sig, !isDraft);
            renderSig('checked', result.signatories.checked, result.signatories.checked_sig, (result.status === 'Pending CEO' || result.status === 'Approved'));
            renderSig('approved', result.signatories.approved, result.signatories.approved_sig, (result.status === 'Approved'));

            // --- BUTTON LOGIC ---
            $('#workflow_actions').css('display', 'flex');
            $('#btn_submit, #btn_approve_hr, #btn_approve_ceo, #btn_reject, #btn_reset').hide();

            if (canEdit) $('#btn_submit').show();
            if (result.can_approve_hr) { $('#btn_approve_hr').show(); $('#btn_reject').show(); }
            if (result.can_approve_ceo) { $('#btn_approve_ceo').show(); $('#btn_reject').show(); }
            
            if (result.can_reset) { $('#btn_reset').show(); }
        });
    });
}

function handleTwoStepSubmit() {
    const btn = $('#btn_submit');
    const step = btn.data('step');

    if (step == 1) {
        if (currentUserSigPath) {
            $('#sig_img_prepared').html(`<img src="${currentUserSigPath}" class="signature-img" alt="My Sig">`);
        } else {
            alert("No e-signature found in your profile settings.");
        }
        btn.data('step', 2);
        btn.html('<i class="fa-solid fa-check"></i> Confirm Submit');
        btn.removeClass('btn-approve').addClass('btn-primary'); 
    } else {
        updateStatus('Pending HR');
    }
}

function updateStatus(newStatus) {
    let msg = `Confirm status change to: ${newStatus}?`;
    if(newStatus === 'Draft') msg = "WARNING: This will unlock the schedule and CLEAR any finalized entries for this period so you can resubmit. Continue?";
    
    if(!confirm(msg)) return;
    
    $.post('', {
        action: 'update_status',
        dept_id: $('#dept_select').val(),
        range: $('#cutoff_select').val(),
        status: newStatus
    }, function(res) {
        loadData();
        // If approved/rejected, they might want to go back to dashboard
        if (isAdminView) {
             // Optional: automatically go back to dashboard after approval?
             // switchTab('dashboard'); 
        }
    });
}

function renderDocTable(employees, schedules, range) {
    const [startStr, endStr] = range.split('|');
    const sDate = new Date(startStr);
    const eDate = new Date(endStr);
    
    const options = { month: 'short', day: 'numeric', year: 'numeric' };
    $('#doc_cutoff_label').text(`Cut-Off Period: ${sDate.toLocaleDateString('en-US', options)} – ${eDate.toLocaleDateString('en-US', options)}`);

    let dates = [];
    let curr = new Date(sDate);
    
    let row1 = `<tr><th rowspan="2" style="text-align:left; width:150px;">Employee’s Name</th>`;
    let row2 = `<tr>`;
    
    while (curr <= eDate) {
        dates.push(new Date(curr));
        const dNum = curr.getDate();
        const dMon = curr.toLocaleDateString('en-US', {month:'short'}).toUpperCase();
        row1 += `<th>${dNum}-${dMon}</th>`;
        row2 += `<th>${curr.toLocaleDateString('en-US', {weekday:'short'}).toUpperCase()}</th>`;
        curr.setDate(curr.getDate() + 1);
    }
    row1 += `<th>-</th></tr>`;
    row2 += `<th>-</th></tr>`;
    
    $('#doc_table_head').html(row1 + row2);

    let htmlBody = '';
    employees.forEach(emp => {
        htmlBody += `<tr><td style="font-weight:bold; text-align:left;">${emp.name}</td>`;
        dates.forEach(d => {
            const dateStr = d.toISOString().split('T')[0];
            let display = '';
            if (schedules[emp.id] && schedules[emp.id][dateStr]) {
                display = schedules[emp.id][dateStr]['time'];
            }
            let click = canEdit ? `onclick="openShiftModal(${emp.id}, '${dateStr}')"` : '';
            let cls = canEdit ? 'cell-edit' : '';
            htmlBody += `<td class="${cls}" ${click}>${display}</td>`;
        });
        htmlBody += `<td>-</td></tr>`;
    });
    $('#doc_table_body').html(htmlBody);
}

function openShiftModal(empId, date) {
    currentEditing = { empId, date };
    $('#shiftModal').addClass('active');
}

function saveShift(code) {
    $.post('', {
        action: 'save_schedule',
        employee_id: currentEditing.empId,
        schedule_date: currentEditing.date,
        shift_code: code,
        dept_id: $('#dept_select').val(),
        range: $('#cutoff_select').val()
    }, function() {
        $('#shiftModal').removeClass('active');
        loadData(); 
    });
}
</script>
</body>
</html>