<?php
session_start();

// ============================================
// SQL SERVER CONNECTION (SchedulerDB)
// ============================================
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

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// ============================================
// SESSION & PERMISSION CHECKS
// ============================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_fullname = $_SESSION['full_name'];
$user_role = trim($_SESSION['approval_role'] ?? 'Employee'); 
$user_dept = $_SESSION['dept_id'] ?? 0;

// Permissions (Case-Insensitive)
$is_head = (strcasecmp($user_role, 'DeptHead') === 0);
$is_hr   = (strcasecmp($user_role, 'HR') === 0);
$is_ceo  = (strcasecmp($user_role, 'CEO') === 0);

// ============================================
// AJAX ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. GET EMPLOYEES 
    if ($_POST['action'] === 'get_employees_by_dept') {
        $dept_id = $_POST['dept_id'];
        $sql = "SELECT emp_id, first_name, last_name FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE dept_id = ? AND employee_status = 'Active' ORDER BY last_name";
        $stmt = sqlsrv_query($conn, $sql, array($dept_id));
        $emps = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $emps[] = ['id' => $row['emp_id'], 'name' => $row['first_name'] . ' ' . $row['last_name']];
            }
        }
        echo json_encode(['success' => true, 'employees' => $emps]);
        exit;
    }

    // 2. GET SCHEDULE DATA
    if ($_POST['action'] === 'get_schedules') {
        $dept_id = $_POST['dept_id'];
        $cutoff = $_POST['cutoff_period'];
        
        $sqlBatch = "SELECT batch_id, status FROM ScheduleBatches WHERE dept_id = ? AND cutoff_label = ?";
        $stmtBatch = sqlsrv_query($conn, $sqlBatch, array($dept_id, $cutoff));
        $batch = sqlsrv_fetch_array($stmtBatch, SQLSRV_FETCH_ASSOC);

        $status = $batch ? $batch['status'] : 'Not Started';
        $batch_id = $batch ? $batch['batch_id'] : 0;

        $can_edit = false;
        // Edit allowed for Head if not yet pending approval
        // HR/CEO usually don't edit the grid directly, they just approve/reject, 
        // but if you want them to edit, add $is_hr || $is_ceo here.
        if ($is_head && ($status == 'Not Started' || $status == 'Draft' || $status == 'Rejected')) $can_edit = true;
        
        $can_approve_hr = ($is_hr && $status == 'Pending HR');
        $can_approve_ceo = ($is_ceo && $status == 'Pending CEO');

        $schedules = [];
        if ($batch_id) {
            $sqlSched = "SELECT employee_id, schedule_date, shift_code FROM EmployeeSchedules WHERE batch_id = ?";
            $stmtSched = sqlsrv_query($conn, $sqlSched, array($batch_id));
            while ($row = sqlsrv_fetch_array($stmtSched, SQLSRV_FETCH_ASSOC)) {
                $date = $row['schedule_date']->format('Y-m-d');
                $schedules[$row['employee_id']][$date] = $row['shift_code'];
            }
        }

        echo json_encode([
            'success' => true,
            'status' => $status,
            'can_edit' => $can_edit,
            'can_approve_hr' => $can_approve_hr,
            'can_approve_ceo' => $can_approve_ceo,
            'schedules' => $schedules
        ]);
        exit;
    }

    // 3. SAVE SHIFT
    if ($_POST['action'] === 'save_schedule') {
        $emp_id = $_POST['employee_id'];
        $date = $_POST['schedule_date'];
        $code = $_POST['shift_code'];
        $dept_id = $_POST['dept_id'];
        $cutoff = $_POST['cutoff_period'];
        $start = $_POST['cutoff_start'];
        $end = $_POST['cutoff_end'];

        $sqlCheck = "SELECT batch_id FROM ScheduleBatches WHERE dept_id = ? AND cutoff_label = ?";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($dept_id, $cutoff));
        $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);

        if ($row) {
            $batch_id = $row['batch_id'];
        } else {
            $sqlIns = "INSERT INTO ScheduleBatches (dept_id, cutoff_label, cutoff_start, cutoff_end, status, created_by) VALUES (?, ?, ?, ?, 'Draft', ?); SELECT SCOPE_IDENTITY() as id";
            $stmtIns = sqlsrv_query($conn, $sqlIns, array($dept_id, $cutoff, $start, $end, $current_user_id));
            sqlsrv_next_result($stmtIns); 
            $rowIns = sqlsrv_fetch_array($stmtIns, SQLSRV_FETCH_ASSOC);
            $batch_id = $rowIns['id'];
            logHistory($conn, $batch_id, 'Created', $current_fullname, $user_role, 'Started draft schedule');
        }

        $sqlDel = "DELETE FROM EmployeeSchedules WHERE batch_id = ? AND employee_id = ? AND schedule_date = ?";
        sqlsrv_query($conn, $sqlDel, array($batch_id, $emp_id, $date));

        $sqlAdd = "INSERT INTO EmployeeSchedules (batch_id, employee_id, schedule_date, shift_code) VALUES (?, ?, ?, ?)";
        sqlsrv_query($conn, $sqlAdd, array($batch_id, $emp_id, $date, $code));

        echo json_encode(['success' => true]);
        exit;
    }

    // 4. UPDATE STATUS
    if ($_POST['action'] === 'update_status') {
        $dept_id = $_POST['dept_id'];
        $cutoff = $_POST['cutoff_period'];
        $new_status = $_POST['status'];
        $comments = $_POST['comments'] ?? '';

        $sqlBatch = "SELECT batch_id FROM ScheduleBatches WHERE dept_id = ? AND cutoff_label = ?";
        $stmtBatch = sqlsrv_query($conn, $sqlBatch, array($dept_id, $cutoff));
        $row = sqlsrv_fetch_array($stmtBatch, SQLSRV_FETCH_ASSOC);
        
        if ($row) {
            $batch_id = $row['batch_id'];
            $sqlUpd = "UPDATE ScheduleBatches SET status = ? WHERE batch_id = ?";
            sqlsrv_query($conn, $sqlUpd, array($new_status, $batch_id));

            $action = $new_status == 'Pending HR' ? 'Submitted' : $new_status;
            logHistory($conn, $batch_id, $action, $current_fullname, $user_role, $comments);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Batch not found']);
        }
        exit;
    }

    // 5. GET PENDING APPROVALS
    if ($_POST['action'] === 'get_pending_approvals') {
        $target_status = '';
        if ($is_hr) $target_status = 'Pending HR';
        if ($is_ceo) $target_status = 'Pending CEO';

        if ($target_status) {
            $sql = "SELECT b.batch_id, b.cutoff_label, b.cutoff_start, b.cutoff_end, b.dept_id, d.dept_name, b.status 
                    FROM ScheduleBatches b
                    LEFT JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON b.dept_id = d.dept_id
                    WHERE b.status = ? ORDER BY b.updated_at DESC";
            $stmt = sqlsrv_query($conn, $sql, array($target_status));
            
            $pending = [];
            while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $pending[] = [
                    'dept_name' => $row['dept_name'],
                    'cutoff' => $row['cutoff_label'],
                    'dept_id' => $row['dept_id'],
                    'range' => $row['cutoff_start']->format('M d') . ' - ' . $row['cutoff_end']->format('M d')
                ];
            }
            echo json_encode(['success' => true, 'data' => $pending]);
        } else {
            echo json_encode(['success' => true, 'data' => []]);
        }
        exit;
    }

    // 6. GET HISTORY
    if ($_POST['action'] === 'get_history') {
        $dept_id = $_POST['dept_id'];
        $cutoff = $_POST['cutoff_period'];
        $sql = "SELECT h.* FROM ScheduleHistory h JOIN ScheduleBatches b ON h.batch_id = b.batch_id WHERE b.dept_id = ? AND b.cutoff_label = ? ORDER BY h.timestamp DESC";
        $stmt = sqlsrv_query($conn, $sql, array($dept_id, $cutoff));
        $logs = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $logs[] = ['action' => $row['action'], 'actor' => $row['actor_name'] . ' (' . $row['actor_role'] . ')', 'time' => $row['timestamp']->format('M d, Y h:i A'), 'comments' => $row['comments']];
        }
        echo json_encode(['success' => true, 'logs' => $logs]);
        exit;
    }
}

function logHistory($conn, $batch_id, $action, $name, $role, $comments) {
    $sql = "INSERT INTO ScheduleHistory (batch_id, action, actor_name, actor_role, comments) VALUES (?, ?, ?, ?, ?)";
    sqlsrv_query($conn, $sql, array($batch_id, $action, $name, $role, $comments));
}

// Initial Data Fetch
$depts = [];
$dept_sql = "SELECT dept_id, dept_name FROM [EmployeeManagementSystem].[dbo].[Departments] ORDER BY dept_name";
$dept_stmt = sqlsrv_query($conn, $dept_sql);
while($d = sqlsrv_fetch_array($dept_stmt, SQLSRV_FETCH_ASSOC)) { $depts[] = $d; }

$shifts = [];
$shift_sql = "SELECT shift_code, shift_name, time_in, time_out, is_special FROM shift_types";
$shift_stmt = sqlsrv_query($conn, $shift_sql);
while($s = sqlsrv_fetch_array($shift_stmt, SQLSRV_FETCH_ASSOC)) { $shifts[] = $s; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #0f766e; --bg: #f8fafc; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); margin: 0; padding: 20px; color: #1e293b; }
        
        .header { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 2px; }
        .tab { padding: 10px 20px; cursor: pointer; font-weight: 600; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -4px; transition: 0.2s; }
        .tab.active { color: var(--primary); border-bottom-color: var(--primary); }
        .tab:hover { color: var(--primary); }

        .panel { display: none; }
        .panel.active { display: block; }

        .controls { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end; }
        .approval-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .approval-card { background: white; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .approval-card h4 { margin: 0 0 10px 0; color: var(--primary); }
        
        .schedule-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; background: white; }
        .schedule-table th, .schedule-table td { border: 1px solid #e2e8f0; padding: 10px; text-align: center; }
        .schedule-table th { background: #f1f5f9; font-weight: 600; position: sticky; top: 0; }
        .schedule-table td:first-child { position: sticky; left: 0; background: white; z-index: 10; font-weight: 600; text-align: left; }

        .status-badge { padding: 4px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .status-Draft { background: #f1f5f9; color: #475569; }
        .status-Pending-HR { background: #fff7ed; color: #c2410c; }
        .status-Pending-CEO { background: #e0f2fe; color: #0284c7; }
        .status-Approved { background: #dcfce7; color: #166534; }
        .status-Rejected { background: #fef2f2; color: #b91c1c; }

        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: 500; font-size: 0.9rem; transition: 0.2s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-approve { background: #059669; color: white; }
        .btn-reject { background: #dc2626; color: white; }
        .btn-secondary { background: #e2e8f0; color: #334155; text-decoration: none; display: inline-block; }

        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 50; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 25px; border-radius: 12px; width: 500px; max-width: 90%; }
        
        /* Interactive Cells */
        .cell-edit { cursor: pointer; }
        .cell-edit:hover { background: #f0f9ff; }
    </style>
</head>
<body>

<div class="header">
    <div>
        <h1 style="margin:0; font-size:1.5rem;">Schedule Manager</h1>
        <div style="color:#64748b; font-size:0.9rem;">Logged in as: <strong><?php echo $current_fullname; ?></strong> (<?php echo $user_role; ?>)</div>
    </div>
    <a href="../index.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

<!-- TABS -->
<div class="tabs">
    <div class="tab active" onclick="switchTab('scheduler')" id="tab_scheduler">
        <?php echo $is_head ? 'My Department Schedule' : 'Schedule Viewer'; ?>
    </div>
    <?php if($is_hr || $is_ceo): ?>
    <div class="tab" onclick="switchTab('approvals')" id="tab_approvals">
        For Approval <span id="badge_count" style="background:#dc2626; color:white; padding:1px 6px; border-radius:10px; font-size:0.7rem; display:none;">0</span>
    </div>
    <?php endif; ?>
</div>

<!-- SCHEDULER PANEL -->
<div id="panel_scheduler" class="panel active">
    <div class="controls">
        <!-- Dept Selection: Locked for Heads/Employees, Open for HR/CEO -->
        <div>
            <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:5px;">Department</label>
            <?php if($is_hr || $is_ceo): ?>
                <select id="dept_select" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                    <option value="">-- Select Department --</option>
                    <?php foreach($depts as $d): ?>
                        <option value="<?php echo $d['dept_id']; ?>" <?php if($d['dept_id'] == $user_dept) echo 'selected'; ?>>
                            <?php echo $d['dept_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="text" value="<?php 
                    foreach($depts as $d) { if($d['dept_id'] == $user_dept) echo $d['dept_name']; }
                ?>" readonly style="width:100%; padding:8px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; color:#64748b;">
                <input type="hidden" id="dept_select" value="<?php echo $user_dept; ?>">
            <?php endif; ?>
        </div>
        
        <div>
            <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:5px;">Cutoff Period</label>
            <select id="cutoff_select" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                <option value="23-09">Nov 23, 2025 - Dec 09, 2025</option>
                <option value="10-22">Dec 10, 2025 - Dec 22, 2025</option>
            </select>
        </div>

        <div style="padding-bottom:5px;">
            <span id="status_display" class="status-badge status-Draft">Ready</span>
        </div>

        <div style="text-align:right;">
            <button class="btn btn-secondary" onclick="viewHistory()"><i class="fa-solid fa-clock-rotate-left"></i> Logs</button>
        </div>
    </div>

    <!-- Actions Bar -->
    <div id="workflow_actions" style="margin-bottom:15px; display:none; gap:10px; justify-content:flex-end; background:#f0fdf4; padding:10px; border-radius:8px; border:1px solid #bbf7d0;">
        <span style="align-self:center; font-weight:600; color:#166534; margin-right:auto;">Action Required:</span>
        <button id="btn_submit" class="btn btn-approve" onclick="updateStatus('Pending HR')" style="display:none;">Submit to HR</button>
        <button id="btn_approve_hr" class="btn btn-approve" onclick="updateStatus('Pending CEO')" style="display:none;">Approve (HR)</button>
        <button id="btn_approve_ceo" class="btn btn-approve" onclick="updateStatus('Approved')" style="display:none;">Final Approve</button>
        <button id="btn_reject" class="btn btn-reject" onclick="updateStatus('Rejected')" style="display:none;">Reject</button>
    </div>

    <div style="overflow-x:auto; border-radius:12px; border:1px solid #e2e8f0;">
        <table class="schedule-table">
            <thead><tr id="table_header"></tr></thead>
            <tbody id="table_body"><tr><td colspan="15" style="padding:40px; color:#94a3b8;">Select options/cutoff to view data</td></tr></tbody>
        </table>
    </div>
</div>

<!-- APPROVALS PANEL -->
<div id="panel_approvals" class="panel">
    <div class="approval-list" id="approval_container">
        <!-- Loaded via JS -->
    </div>
</div>

<!-- Shift Modal -->
<div id="shiftModal" class="modal">
    <div class="modal-content">
        <h3>Select Shift</h3>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:15px;">
            <?php foreach($shifts as $s): ?>
                <div onclick="saveShift('<?php echo $s['shift_code']; ?>')" style="border:1px solid #e2e8f0; padding:10px; text-align:center; border-radius:6px; cursor:pointer; background:<?php echo $s['is_special']?'#fff7ed':'white';?>;">
                    <strong><?php echo $s['shift_name']; ?></strong>
                    <div style="font-size:0.7rem; color:#64748b;"><?php echo $s['time_in'] ? $s['time_in']->format('H:i').'-'.$s['time_out']->format('H:i') : 'No Time'; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <button onclick="closeModals()" class="btn btn-secondary" style="width:100%; margin-top:15px;">Cancel</button>
    </div>
</div>

<!-- History Modal -->
<div id="historyModal" class="modal">
    <div class="modal-content" style="width:600px;">
        <h3>Activity Log</h3>
        <div id="history_list" style="max-height:300px; overflow-y:auto; margin-top:15px; display:flex; flex-direction:column; gap:10px;"></div>
        <button onclick="closeModals()" class="btn btn-secondary" style="width:100%; margin-top:15px;">Close</button>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let currentEditing = { empId: null, date: null };
let cutoffDates = {};
let canEdit = false;
let userRole = "<?php echo $user_role; ?>";

$(document).ready(function() {
    // Initial Load
    loadData();
    
    // Auto-reload when dropdowns change
    $('#dept_select, #cutoff_select').on('change', function() {
        loadData();
    });
    
    // If HR/CEO, check approvals
    <?php if($is_hr || $is_ceo): ?>
        loadPendingApprovals();
    <?php endif; ?>
});

function switchTab(tab) {
    $('.tab').removeClass('active');
    $('.panel').removeClass('active');
    $('#tab_' + tab).addClass('active');
    $('#panel_' + tab).addClass('active');
    
    if(tab === 'approvals') loadPendingApprovals();
}

function loadData(overrideDept = null, overrideCutoff = null) {
    const dept = overrideDept || $('#dept_select').val();
    const cutoff = overrideCutoff || $('#cutoff_select').val();
    
    if(!dept) return; // Wait for selection if empty
    
    // Set controls if loading from approval click
    if(overrideDept) {
        $('#dept_select').val(dept);
        $('#cutoff_select').val(cutoff);
        switchTab('scheduler');
    }

    const year = 2025;
    if(cutoff === '23-09') { cutoffDates.start = `${year-1}-11-23`; cutoffDates.end = `${year-1}-12-09`; }
    if(cutoff === '10-22') { cutoffDates.start = `${year}-12-10`; cutoffDates.end = `${year}-12-22`; }

    $.post('', { action: 'get_employees_by_dept', dept_id: dept }, function(res) {
        const employees = JSON.parse(res).employees;
        $.post('', { 
            action: 'get_schedules', 
            dept_id: dept, 
            cutoff_period: cutoff, 
            cutoff_start: cutoffDates.start, 
            cutoff_end: cutoffDates.end 
        }, function(data) {
            const result = JSON.parse(data);
            canEdit = result.can_edit;
            
            $('#status_display').text(result.status).attr('class', 'status-badge status-' + result.status.replace(' ', '-'));
            
            // Buttons Logic
            $('#workflow_actions').hide();
            $('#btn_submit').hide(); $('#btn_approve_hr').hide(); $('#btn_approve_ceo').hide(); $('#btn_reject').hide();

            if (canEdit) { $('#workflow_actions').css('display','flex'); $('#btn_submit').show(); }
            if (result.can_approve_hr) { $('#workflow_actions').css('display','flex'); $('#btn_approve_hr').show(); $('#btn_reject').show(); }
            if (result.can_approve_ceo) { $('#workflow_actions').css('display','flex'); $('#btn_approve_ceo').show(); $('#btn_reject').show(); }

            renderGrid(employees, result.schedules);
        });
    });
}

function loadPendingApprovals() {
    $.post('', { action: 'get_pending_approvals' }, function(res) {
        const data = JSON.parse(res).data;
        const count = data.length;
        
        if(count > 0) {
            $('#badge_count').text(count).show();
            let html = '';
            data.forEach(item => {
                html += `
                <div class="approval-card">
                    <h4>${item.dept_name}</h4>
                    <p style="font-size:0.85rem; color:#64748b; margin-bottom:15px;">
                        <strong>Period:</strong> ${item.cutoff}<br>
                        <strong>Range:</strong> ${item.range}
                    </p>
                    <button class="btn btn-primary" style="width:100%" onclick="loadData('${item.dept_id}', '${item.cutoff}')">Review Schedule</button>
                </div>`;
            });
            $('#approval_container').html(html);
        } else {
            $('#badge_count').hide();
            $('#approval_container').html('<div style="grid-column:1/-1; text-align:center; padding:40px; color:#94a3b8;">No schedules pending your approval.</div>');
        }
    });
}

function renderGrid(employees, schedules) {
    const start = new Date(cutoffDates.start);
    const end = new Date(cutoffDates.end);
    let htmlHead = '<th>Employee</th>';
    let dates = [];
    
    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
        dates.push(new Date(d));
        htmlHead += `<th>${d.getDate()}<br><span style="font-size:0.7em; font-weight:400;">${d.toLocaleDateString('en-US', {weekday:'short'})}</span></th>`;
    }
    $('#table_header').html(htmlHead);

    let htmlBody = '';
    employees.forEach(emp => {
        htmlBody += `<tr><td style="background:#f8fafc; font-weight:600;">${emp.name}</td>`;
        dates.forEach(d => {
            const dateStr = d.toISOString().split('T')[0];
            const shift = (schedules[emp.id] && schedules[emp.id][dateStr]) ? schedules[emp.id][dateStr] : '-';
            let click = canEdit ? `onclick="openShiftModal(${emp.id}, '${dateStr}')"` : '';
            let cls = canEdit ? 'cell-edit' : '';
            let style = shift === 'OFF' ? 'background:#fff7ed; color:#c2410c;' : (shift !== '-' ? 'background:#ecfdf5; color:#047857; font-weight:bold;' : '');
            htmlBody += `<td class="${cls}" style="${style}" ${click}>${shift}</td>`;
        });
        htmlBody += '</tr>';
    });
    $('#table_body').html(htmlBody);
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
        cutoff_period: $('#cutoff_select').val(),
        cutoff_start: cutoffDates.start,
        cutoff_end: cutoffDates.end
    }, function() {
        $('#shiftModal').removeClass('active');
        loadData();
    });
}

function updateStatus(newStatus) {
    if(!confirm(`Update status to: ${newStatus}?`)) return;
    const comments = prompt("Optional comments:");
    $.post('', {
        action: 'update_status',
        dept_id: $('#dept_select').val(),
        cutoff_period: $('#cutoff_select').val(),
        status: newStatus,
        comments: comments
    }, function() {
        loadData();
    });
}

function viewHistory() {
    const dept = $('#dept_select').val();
    const cutoff = $('#cutoff_select').val();
    if(!dept) return alert("Select a department first");
    
    $.post('', { action: 'get_history', dept_id: dept, cutoff_period: cutoff }, function(res) {
        const logs = JSON.parse(res).logs;
        let html = '';
        if(logs.length === 0) html = '<div style="text-align:center; color:#94a3b8;">No history found.</div>';
        logs.forEach(log => {
            html += `<div style="background:#f8fafc; padding:10px; border-radius:6px; border:1px solid #e2e8f0;">
                <div style="display:flex; justify-content:space-between; font-size:0.8rem; font-weight:bold; margin-bottom:4px;"><span>${log.action}</span><span style="color:#64748b;">${log.time}</span></div>
                <div style="font-size:0.85rem;">By: ${log.actor}</div>
                ${log.comments ? `<div style="font-size:0.8rem; color:#475569; margin-top:4px; font-style:italic;">"${log.comments}"</div>` : ''}
            </div>`;
        });
        $('#history_list').html(html);
        $('#historyModal').addClass('active');
    });
}

function closeModals() { $('.modal').removeClass('active'); }
</script>
</body>
</html>