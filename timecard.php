<?php
session_start();

// ============================================
// 1. DATABASE CONNECTIONS
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
    } catch (PDOException $e) {
        die("System DB Connection failed: " . $e->getMessage());
    }
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
    } catch (PDOException $e) {
        die("Biologs DB Connection failed: " . $e->getMessage());
    }
}

// ============================================
// 2. AUTHENTICATION & PERMISSIONS
// ============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$conn = getDBConnection();
$bioConn = getBiologsConnection();

$current_user_id = $_SESSION['user_id'];
$current_role = $_SESSION['approval_role'] ?? 'Employee';
$is_hr = ($current_role === 'HR');

// Check for Holiday Helper
if(file_exists('payslip/get_holiday.php')) {
    require_once 'payslip/get_holiday.php';
} else {
    function getHolidayType($conn, $date) { return 'REGULAR_DAY'; } 
}

// ============================================
// 3. HR MANUAL ADJUSTMENT HANDLER
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_log') {
    if (!$is_hr) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

    $target_ac = $_POST['ac_no'];
    $target_date = $_POST['date']; 
    $type = $_POST['type']; // IN or OUT
    $new_time = $_POST['new_time']; 

    if (empty($target_ac) || empty($target_date) || empty($type)) {
        echo json_encode(['success' => false, 'message' => 'Missing data']); exit;
    }

    try {
        $check = $conn->prepare("SELECT id FROM TimecardAdjustments WHERE ac_no=? AND adj_date=? AND adj_type=?");
        $check->execute([$target_ac, $target_date, $type]);
        $exists = $check->fetch();

        if ($exists) {
            $sql = "UPDATE TimecardAdjustments SET time_value = ?, updated_at = GETDATE() WHERE id = ?";
            $conn->prepare($sql)->execute([$new_time, $exists['id']]);
        } else {
            $sql = "INSERT INTO TimecardAdjustments (ac_no, adj_date, adj_type, time_value) VALUES (?, ?, ?, ?)";
            $conn->prepare($sql)->execute([$target_ac, $target_date, $type, $new_time]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================
// 4. DATA FETCHING & LOGIC
// ============================================

$target_ac_no = $_SESSION['ac_no']; 
$target_name = $_SESSION['full_name'];

if ($is_hr && isset($_GET['search_ac']) && !empty($_GET['search_ac'])) {
    $target_ac_no = $_GET['search_ac'];
    $stmt = $conn->prepare("SELECT first_name, last_name FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE ac_no = ?");
    $stmt->execute([$target_ac_no]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) $target_name = $res['first_name'] . ' ' . $res['last_name'];
}

// --- CUTOFF GENERATION ---
function generateCutoffPeriods() {
    $periods = [];
    $current = new DateTime();
    $current->modify('-4 months'); 

    for ($i = 0; $i < 8; $i++) { 
        $year = $current->format('Y');
        $month = $current->format('m');
        
        $p1_start = "$year-$month-08";
        $p1_end   = "$year-$month-22";
        $periods[] = ['val' => "$p1_start|$p1_end", 'label' => date('M d', strtotime($p1_start)) . " - " . date('M d', strtotime($p1_end)) . ", $year"];

        $p2_start = "$year-$month-23";
        $nextMonthDate = clone $current;
        $nextMonthDate->modify('+1 month');
        $p2_end = $nextMonthDate->format('Y-m-07');
        $nextYearLabel = $nextMonthDate->format('Y');
        $periods[] = ['val' => "$p2_start|$p2_end", 'label' => date('M d', strtotime($p2_start)) . " - " . date('M d', strtotime($p2_end)) . ", $nextYearLabel"];

        $current->modify('+1 month');
    }
    return array_reverse($periods);
}

$cutoffs = generateCutoffPeriods();
$selected_cutoff = $_GET['cutoff'] ?? $cutoffs[0]['val'];
list($start_date, $end_date) = explode('|', $selected_cutoff);

function normalizeDate($input) {
    if ($input instanceof DateTime) return $input->format('Y-m-d');
    return date('Y-m-d', strtotime($input));
}

// Initialize Data Grid
$data = [];
$period = new DatePeriod(new DateTime($start_date), new DateInterval('P1D'), (new DateTime($end_date))->modify('+1 day'));
foreach ($period as $dt) {
    $data[$dt->format('Y-m-d')] = [
        'date' => $dt->format('Y-m-d'),
        'day' => $dt->format('l'),
        'sched_in' => null, 'sched_out' => null, 'sched_code' => '-', 
        'actual_in' => null, 'actual_out' => null,
        'is_manual_in' => false, 'is_manual_out' => false, 
        'hours' => 0, 'late_mins' => 0, 'ut_mins' => 0, 'ot_hours' => 0, 'remarks' => ''
    ];
}

// 4.1 FETCH SCHEDULE
$scheduleFound = false;
$sqlSched = "SELECT schedule_Date, Shift_code, Time_In, Time_Out FROM [SchedulerDB].[dbo].[FinalizedSchedule] WHERE Ac_no = ? AND schedule_Date BETWEEN ? AND ?";
$stmt = $conn->prepare($sqlSched);
$stmt->execute([$target_ac_no, $start_date, $end_date]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $scheduleFound = true;
    $dStr = normalizeDate($row['schedule_Date']);
    if (isset($data[$dStr])) {
        $data[$dStr]['sched_code'] = $row['Shift_code'];
        $tIn = $row['Time_In'] ? ($row['Time_In'] instanceof DateTime ? $row['Time_In'] : new DateTime($row['Time_In'])) : null;
        $tOut = $row['Time_Out'] ? ($row['Time_Out'] instanceof DateTime ? $row['Time_Out'] : new DateTime($row['Time_Out'])) : null;
        
        if($tIn) { $data[$dStr]['sched_in_obj'] = $tIn; $data[$dStr]['sched_in'] = $tIn->format('H:i'); }
        if($tOut) { $data[$dStr]['sched_out_obj'] = $tOut; $data[$dStr]['sched_out'] = $tOut->format('H:i'); }
    }
}

if (!$scheduleFound) {
    $sqlDraft = "SELECT es.schedule_date, es.shift_code, st.time_in, st.time_out 
                 FROM EmployeeSchedules es
                 JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON es.employee_id = e.emp_id
                 LEFT JOIN shift_types st ON es.shift_code = st.shift_code
                 WHERE e.ac_no = ? AND es.schedule_date BETWEEN ? AND ?";
    $stmtDraft = $conn->prepare($sqlDraft);
    $stmtDraft->execute([$target_ac_no, $start_date, $end_date]);
    while ($row = $stmtDraft->fetch(PDO::FETCH_ASSOC)) {
        $dStr = normalizeDate($row['schedule_date']);
        if (isset($data[$dStr])) {
            $data[$dStr]['sched_code'] = $row['shift_code'];
            $tIn = $row['time_in'] ? ($row['time_in'] instanceof DateTime ? $row['time_in'] : new DateTime($row['time_in'])) : null;
            $tOut = $row['time_out'] ? ($row['time_out'] instanceof DateTime ? $row['time_out'] : new DateTime($row['time_out'])) : null;
            if($tIn) { $data[$dStr]['sched_in_obj'] = $tIn; $data[$dStr]['sched_in'] = $tIn->format('H:i'); }
            if($tOut) { $data[$dStr]['sched_out_obj'] = $tOut; $data[$dStr]['sched_out'] = $tOut->format('H:i'); }
            $data[$dStr]['remarks'] = '(Draft)';
        }
    }
}

// 4.2 FETCH RAW LOGS
$sqlLogs = "SELECT CAST(LogTime AS DATE) as LogDate, LogTime, CheckType FROM AttendanceData WHERE AC_No = ? AND CAST(LogTime AS DATE) BETWEEN ? AND DATEADD(day, 1, ?) ORDER BY LogTime ASC";
$stmt = $bioConn->prepare($sqlLogs);
$stmt->execute([$target_ac_no, $start_date, $end_date]);
$rawLogs = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

// 4.3 FETCH ADJUSTMENTS
$sqlAdj = "SELECT adj_date, adj_type, time_value FROM TimecardAdjustments WHERE ac_no = ? AND adj_date BETWEEN ? AND ?";
$stmtAdj = $conn->prepare($sqlAdj);
$stmtAdj->execute([$target_ac_no, $start_date, $end_date]);
$adjustments = [];
while($row = $stmtAdj->fetch(PDO::FETCH_ASSOC)) {
    $adjustments[$row['adj_date']][$row['adj_type']] = $row['time_value'];
}

// --------------------------------------------------------
// PROCESS LOGIC
// --------------------------------------------------------
$isLogTypeIn = function($type) { $t = strtolower(trim($type)); return ($t === '0' || $t === 'i' || strpos($t, 'in') !== false); };

foreach ($data as $dStr => &$day) {
    // 1. Identify Time In/Out
    $schedInObj = $day['sched_in_obj'] ?? null;
    $schedOutObj = $day['sched_out_obj'] ?? null;
    $isNightShift = ($schedInObj && $schedOutObj && ($schedInObj > $schedOutObj || (int)$schedInObj->format('H') >= 20));

    $foundIn = null; $foundOut = null;
    $nextDayStr = date('Y-m-d', strtotime($dStr . ' +1 day'));

    if ($isNightShift) {
        if (isset($rawLogs[$dStr])) {
            foreach ($rawLogs[$dStr] as $log) {
                $time = new DateTime($log['LogTime']);
                if ($isLogTypeIn($log['CheckType']) || (int)$time->format('H') >= 18) { $foundIn = $time->format('H:i'); break; }
            }
            if (!$foundIn && !empty($rawLogs[$dStr])) $foundIn = (new DateTime(end($rawLogs[$dStr])['LogTime']))->format('H:i');
        }
        if (isset($rawLogs[$nextDayStr])) {
            foreach ($rawLogs[$nextDayStr] as $log) {
                $time = new DateTime($log['LogTime']);
                if ((int)$time->format('H') < 14) {
                    if (!$isLogTypeIn($log['CheckType'])) { $foundOut = $time->format('H:i'); } 
                    else { if ($foundOut === null) $foundOut = $time->format('H:i'); }
                }
            }
        }
    } else {
        if (isset($rawLogs[$dStr])) {
            $logs = $rawLogs[$dStr];
            if (count($logs) > 0) {
                $foundIn = (new DateTime($logs[0]['LogTime']))->format('H:i');
                if (count($logs) > 1) $foundOut = (new DateTime(end($logs)['LogTime']))->format('H:i');
                elseif (!$isLogTypeIn($logs[0]['CheckType'])) { $foundOut = $foundIn; $foundIn = null; }
            }
        }
    }

    if (isset($adjustments[$dStr]['IN'])) { $foundIn = $adjustments[$dStr]['IN']; $day['is_manual_in'] = true; }
    if (isset($adjustments[$dStr]['OUT'])) { $foundOut = $adjustments[$dStr]['OUT']; $day['is_manual_out'] = true; }

    $day['actual_in'] = $foundIn;
    $day['actual_out'] = $foundOut;

    // 2. Identify Status (Working, Off, Leave, Holiday)
    $schedCode = $day['sched_code'];
    $holidayType = function_exists('getHolidayType') ? getHolidayType($conn, $dStr) : 'REGULAR_DAY';
    
    $isOff = in_array($schedCode, ['OFF', 'HOLIDAY OFF', 'REST DAY']);
    $isLeave = in_array($schedCode, ['VL', 'SL', 'EL', 'BL', 'ML', 'PL', 'SPL']);
    $isLWOP = ($schedCode === 'LWOP');
    $hasLog = !empty($foundIn);
    
    // --- FILL REMARKS LOGIC ---
    if (!$hasLog) {
        // Employee is ABSENT or it's a NON-WORKING day
        if ($isLeave) {
            $day['remarks'] = $schedCode . " (Leave)";
            $day['status_color'] = 'bg-blue-50'; // Leave color
        } elseif ($holidayType == 'REGULAR') {
            $day['remarks'] = "Regular Holiday";
            $day['status_color'] = 'bg-primary-50'; // Holiday color
        } elseif ($holidayType == 'SPECIAL') {
            $day['remarks'] = "Special Holiday";
            $day['status_color'] = 'bg-primary-50';
        } elseif ($isOff) {
            $day['remarks'] = "Day Off";
            $day['status_color'] = 'bg-gray-100'; // Rest Day
        } elseif ($isLWOP) {
            $day['remarks'] = "LWOP";
            $day['status_color'] = 'bg-red-50';
        } elseif ($schedCode !== '-') {
            $day['remarks'] = "ABSENT";
            $day['status_color'] = 'bg-red-50 text-red-700'; // Absent Alert
        }
    } else {
        // Employee is PRESENT
        if ($holidayType == 'REGULAR' || $holidayType == 'SPECIAL') {
            $day['remarks'] = $holidayType . " HOLIDAY WORK";
        } elseif ($isOff) {
            $day['remarks'] = "Rest Day Duty";
        }
        
        // 3. Calculate Hours, Late, UT (ONLY IF PRESENT)
        if (!empty($foundOut)) {
            $tsIn = strtotime("$dStr $foundIn");
            $tsOut = strtotime("$dStr $foundOut");
            if ($tsOut < $tsIn) $tsOut += 86400;
            $day['hours'] = number_format(($tsOut - $tsIn) / 3600, 2);
        }

        if (!$isOff && !$isLeave && !$isLWOP && $schedCode !== '-') {
            $sInStr = $day['sched_in'];
            $sOutStr = $day['sched_out'];
            
            // Normalize Schedule Dates
            $tsSchedIn = $sInStr ? strtotime("$dStr $sInStr") : null;
            $tsSchedOut = $sOutStr ? strtotime("$dStr $sOutStr") : null;
            if ($tsSchedOut && $tsSchedIn && $tsSchedOut < $tsSchedIn) $tsSchedOut += 86400;

            // Late Calculation
            if ($tsSchedIn) {
                $tsActIn = strtotime("$dStr $foundIn");
                if ($tsActIn > $tsSchedIn) {
                    $day['late_mins'] = floor(($tsActIn - $tsSchedIn) / 60);
                }
            }
            
            // Undertime / OT Calculation
            if ($tsSchedOut && !empty($foundOut)) {
                $tsActOut = strtotime("$dStr $foundOut");
                if ($tsActOut < strtotime("$dStr $foundIn")) $tsActOut += 86400;

                if ($tsActOut < $tsSchedOut) {
                    $day['ut_mins'] = floor(($tsSchedOut - $tsActOut) / 60);
                } 
                elseif ($tsActOut > $tsSchedOut) {
                    $day['ot_hours'] = number_format(($tsActOut - $tsSchedOut) / 3600, 2);
                }
            }
        }
    }
}
unset($day);

$empList = [];
if ($is_hr) {
    $stmt = $conn->query("SELECT ac_no, first_name, last_name FROM [EmployeeManagementSystem].[dbo].[Employees] ORDER BY last_name");
    $empList = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTR | EMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
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
        .editable { cursor: pointer; position: relative; }
        .editable:hover { background-color: #fef3c7; color: #d97706; font-weight: bold; border: 1px dashed #d97706; }
        .editable:hover::after { content: "✎ Edit"; position: absolute; top: -15px; right: 0; font-size: 9px; background: #d97706; color: white; padding: 2px 4px; border-radius: 4px; }
        .manual-entry { color: #d97706; font-weight: bold; position: relative; }
        .manual-entry::after { content: "•"; position: absolute; top: -5px; right: -5px; color: orange; font-size: 10px; }
        .stat-card { background: white; border: 1px solid #e5e7eb; border-radius: 0.625rem; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1); }
        .select2-container .select2-selection--single { height: 40px; border-color: #d1d5db; border-radius: 0.5rem; padding: 5px; }
        @media print { .no-print { display: none !important; } .print-only { display: block; } body { background: white; } .shadow-sm, .border, .stat-card { box-shadow: none; border: none; } }
    </style>
</head>
<body class="p-4 md:p-8">

    <div class="max-w-5xl mx-auto">
        <div class="stat-card p-6 mb-6 no-print">
            <div class="mb-2">
        <a href="index.php" class="inline-flex items-center gap-2 text-gray-400 hover:text-gray-900 transition-colors text-xs font-bold uppercase tracking-widest"><i class="fa-solid fa-arrow-left"></i> Return</a>
    </div>
            <div class="flex flex-col md:flex-row justify-between items-end gap-4">
                <div class="w-full md:w-auto">
                    <h1 class="text-xl font-bold text-gray-900 mb-4"><i class="fa-regular fa-clock text-primary-600 mr-2"></i>Daily Time Record</h1>
                    <form method="GET" class="flex flex-col md:flex-row gap-4">
                        <?php if ($is_hr): ?>
                        <div class="w-full md:w-64">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Employee</label>
                            <select name="search_ac" id="hr_search" class="w-full" onchange="this.form.submit()">
                                <option value="<?php echo $_SESSION['ac_no']; ?>">Myself</option>
                                <?php foreach ($empList as $emp): ?>
                                    <option value="<?php echo $emp['ac_no']; ?>" <?php echo ($target_ac_no == $emp['ac_no']) ? 'selected' : ''; ?>>
                                        <?php echo $emp['last_name'] . ', ' . $emp['first_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="w-full md:w-64">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Period</label>
                            <select name="cutoff" class="w-full border border-gray-300 rounded-lg p-2 text-sm outline-none bg-white h-10" onchange="this.form.submit()">
                                <?php foreach ($cutoffs as $c): ?>
                                    <option value="<?php echo $c['val']; ?>" <?php echo ($selected_cutoff == $c['val']) ? 'selected' : ''; ?>>
                                        <?php echo $c['label']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <button onclick="window.print()" class="bg-gray-900 hover:bg-black text-white font-medium px-5 py-2.5 rounded-lg flex items-center gap-2 transition shadow-lg"><i class="fa-solid fa-print"></i> Print DTR</button>
            </div>
        </div>

        <div class="stat-card p-8" id="printable">
            <div class="text-center mb-8 border-b-2 border-gray-900 pb-4">
                <h1 class="text-2xl font-bold text-gray-900 tracking-wider">AZZURRO HOTEL</h1>
                <p class="text-sm font-semibold text-gray-500 tracking-[0.2em] mt-1">OFFICIAL TIME RECORD</p>
            </div>
            
            <div class="grid grid-cols-2 gap-x-12 gap-y-2 mb-6 text-sm">
                <div class="flex justify-between border-b border-gray-100 pb-1"><span class="font-bold text-gray-500">NAME</span><span class="font-bold text-gray-900 uppercase"><?php echo htmlspecialchars($target_name); ?></span></div>
                <div class="flex justify-between border-b border-gray-100 pb-1"><span class="font-bold text-gray-500">ID NUMBER</span><span class="font-bold text-gray-900 font-mono"><?php echo htmlspecialchars($target_ac_no); ?></span></div>
                <div class="flex justify-between border-b border-gray-100 pb-1"><span class="font-bold text-gray-500">PERIOD</span><span class="font-bold text-gray-900"><?php echo date('M d', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?></span></div>
                <div class="flex justify-between border-b border-gray-100 pb-1"><span class="font-bold text-gray-500">DEPARTMENT</span><span class="font-bold text-gray-900">--</span></div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300 text-xs text-center">
                    <thead>
                        <tr class="bg-gray-100 text-gray-700">
                            <th rowspan="2" class="border border-gray-300 p-2 w-16">Date</th>
                            <th rowspan="2" class="border border-gray-300 p-2 w-12">Day</th>
                            <th colspan="2" class="border border-gray-300 p-1 bg-gray-50">Schedule</th>
                            <th colspan="2" class="border border-gray-300 p-1 bg-gray-50">Actual Log <?php if($is_hr) echo '<i class="fa-solid fa-pen-to-square text-orange-500 ml-1"></i>'; ?></th>
                            <th rowspan="2" class="border border-gray-300 p-2 w-16">Total<br>Hrs</th>
                            <th rowspan="2" class="border border-gray-300 p-2 w-12 bg-gray-50 text-gray-800">OT<br>Hrs</th>
                            <th colspan="2" class="border border-gray-300 p-1 bg-gray-50">Variance</th>
                            <th rowspan="2" class="border border-gray-300 p-2">Remarks</th>
                        </tr>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 p-1 text-gray-600">IN</th>
                            <th class="border border-gray-300 p-1 text-gray-600">OUT</th>
                            <th class="border border-gray-300 p-1 text-gray-900 font-bold">IN</th>
                            <th class="border border-gray-300 p-1 text-gray-900 font-bold">OUT</th>
                            <th class="border border-gray-300 p-1 text-red-600">Late</th>
                            <th class="border border-gray-300 p-1 text-red-600">UT</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-gray-700">
                        <?php foreach ($data as $day): 
                            $rowColor = $day['status_color'] ?? ''; 
                            $editIn = $is_hr ? "onclick=\"editTime('{$day['date']}', 'IN', '{$day['actual_in']}')\" class='editable'" : "";
                            $editOut = $is_hr ? "onclick=\"editTime('{$day['date']}', 'OUT', '{$day['actual_out']}')\" class='editable'" : "";
                            
                            $clsIn = $day['is_manual_in'] ? 'manual-entry' : '';
                            $clsOut = $day['is_manual_out'] ? 'manual-entry' : '';
                        ?>
                        <tr class="<?php echo $rowColor; ?>">
                            <td class="border border-gray-300 p-2 font-sans"><?php echo date('m/d', strtotime($day['date'])); ?></td>
                            <td class="border border-gray-300 p-2 font-sans text-[10px] uppercase font-bold text-gray-500"><?php echo substr($day['day'], 0, 3); ?></td>
                            <td class="border border-gray-300 p-2 text-gray-500"><?php echo $day['sched_in'] ?: $day['sched_code']; ?></td>
                            <td class="border border-gray-300 p-2 text-gray-500"><?php echo $day['sched_out']; ?></td>
                            
                            <td <?php echo $editIn; ?> class="border border-gray-300 p-2 font-bold <?php echo $clsIn; ?> <?php echo $day['late_mins'] > 0 ? 'text-red-600' : ''; ?>">
                                <?php echo $day['actual_in'] ?: '<span class="text-gray-300">-</span>'; ?>
                            </td>
                            
                            <td <?php echo $editOut; ?> class="border border-gray-300 p-2 font-bold <?php echo $clsOut; ?> <?php echo $day['ut_mins'] > 0 ? 'text-red-600' : ''; ?>">
                                <?php echo $day['actual_out'] ?: '<span class="text-gray-300">-</span>'; ?>
                            </td>
                            
                            <td class="border border-gray-300 p-2 font-bold"><?php echo $day['hours'] > 0 ? $day['hours'] : ''; ?></td>
                            <td class="border border-gray-300 p-2 font-bold text-primary-700 bg-primary-50/20"><?php echo $day['ot_hours'] > 0 ? $day['ot_hours'] : '-'; ?></td>
                            <td class="border border-gray-300 p-2 <?php echo $day['late_mins'] > 0 ? 'bg-red-50 text-red-600' : ''; ?>"><?php echo $day['late_mins'] > 0 ? $day['late_mins'] : ''; ?></td>
                            <td class="border border-gray-300 p-2 <?php echo $day['ut_mins'] > 0 ? 'bg-red-50 text-red-600' : ''; ?>"><?php echo $day['ut_mins'] > 0 ? $day['ut_mins'] : ''; ?></td>
                            <td class="border border-gray-300 p-2 text-[10px] text-left font-sans font-semibold text-gray-600">
                                <?php echo htmlspecialchars($day['remarks']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-12 grid grid-cols-2 gap-20 no-print">
                <div class="text-center"><div class="border-b border-gray-900 w-full mb-2"></div><p class="text-xs font-bold text-gray-600 uppercase">Employee Signature</p></div>
                <div class="text-center"><div class="border-b border-gray-900 w-full mb-2"></div><p class="text-xs font-bold text-gray-600 uppercase">Department Head / HR</p></div>
            </div>
            
            <div class="mt-4 text-center text-[10px] text-gray-400 print-only">Generated by EMS System on <?php echo date('Y-m-d H:i:s'); ?></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() { $('#hr_search').select2({ placeholder: "Search Employee...", width: '100%' }); });

        function editTime(date, type, currentVal) {
            let newVal = prompt(`FORCE ADJUST ${type} for ${date}\nEnter Time (HH:MM):`, currentVal);
            if (newVal !== null) { 
                $.post('', {
                    action: 'update_log', ac_no: '<?php echo $target_ac_no; ?>',
                    date: date, type: type, new_time: newVal
                }, function(res) {
                    if (res.success) location.reload(); else alert("Error: " + res.message);
                }, 'json');
            }
        }
    </script>
</body>
</html>