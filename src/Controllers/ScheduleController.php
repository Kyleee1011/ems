<?php
namespace App\Controllers;

use PDO;
use DateTime;
use Exception;
use App\Helpers\UrlHelper;

class ScheduleController
{
    protected $pdo;
    protected $currentUser;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
require_once dirname(dirname(__DIR__)) . '/config_session.php';
        
        if (!isset($_SESSION['user_id'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->jsonResponse(false, 'Unauthorized');
            }
            header("Location: " . baseUrl('login'));
            exit;
        }

        $this->currentUser = [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['full_name'],
            'role' => trim($_SESSION['approval_role'] ?? 'Employee'),
            'dept_id' => $_SESSION['dept_id'] ?? 0
        ];
    }

    public function index()
    {
        // View-specific logic (from bottom of legacy schedule.php)
        $is_admin_view = (strcasecmp($this->currentUser['role'], 'HR') === 0 || strcasecmp($this->currentUser['role'], 'CEO') === 0);
        
        // Fetch User Signature
        $sqlUserSig = "SELECT signature_path FROM employees WHERE emp_id = ?";
        $stmtUserSig = $this->pdo->prepare($sqlUserSig);
        $stmtUserSig->execute([$this->currentUser['id']]);
        $currentUserSigPath = "";
        if($r = $stmtUserSig->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($r['signature_path'])) {
                $currentUserSigPath = $r['signature_path']; // View expects relative or full path, check View logic
            }
        }
        
        // Departments
        $depts = $this->pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name")->fetchAll(PDO::FETCH_ASSOC);

        // Cutoffs
        $searchYear  = $_GET['year']  ?? date('Y');
        $searchMonth = $_GET['month'] ?? date('m');

        // Generate the latest 5 months (10 dates) as requested, but searching handles the rest
        $cutoff_options = \App\Utils\AppHelpers::generateCutoffPeriods($this->pdo, $searchYear, $searchMonth, 5, -4);
        $today = date('Y-m-d');
        $default_cutoff = $cutoff_options[0]['value'] ?? '';
        foreach($cutoff_options as $opt) {
            $dates = explode('|', $opt['value']);
            if($today >= $dates[0] && $today <= $dates[1]) {
                $default_cutoff = $opt['value']; break;
            }
        }

        // Fetch Signatories for Pre-rendering
        // HR
        $stmtHR = $this->pdo->query("SELECT CONCAT(first_name, ' ', last_name) as name, signature_path, d.dept_name FROM employees e LEFT JOIN departments d ON e.dept_id = d.dept_id WHERE approval_role = 'HR' AND IsActive = 1 LIMIT 1");
        $rowHR = $stmtHR->fetch(PDO::FETCH_ASSOC);
        $pageHrName = $rowHR ? $rowHR['name'] : "HR Admin"; // Fallback
        $pageHrDept = $rowHR ? $rowHR['dept_name'] : "Human Resources";

        // CEO
        $stmtCEO = $this->pdo->query("SELECT CONCAT(first_name, ' ', last_name) as name, signature_path, d.dept_name FROM employees e LEFT JOIN departments d ON e.dept_id = d.dept_id WHERE approval_role = 'CEO' AND IsActive = 1 LIMIT 1");
        $rowCEO = $stmtCEO->fetch(PDO::FETCH_ASSOC);
        
        if ($rowCEO) {
            $pageCeoName = $rowCEO['name'];
            $pageCeoDept = $rowCEO['dept_name'] ?? 'President & CEO';
        } else {
             // Fallback to Owner Dept Head
             $ownerSig = $this->getDeptSignatory('Owner');
             $pageCeoName = $ownerSig['name'];
             $pageCeoDept = 'President & CEO';
        }

        // Shift Groups
        $grouped_shifts = $this->getReferencedShifts();

        // Render View
        $data = [
            'current_fullname' => $this->currentUser['name'],
            'user_role' => $this->currentUser['role'],
            'user_dept' => $this->currentUser['dept_id'],
            'is_admin_view' => $is_admin_view,
            'is_hr' => (strcasecmp($this->currentUser['role'], 'HR') === 0),
            'is_ceo' => (strcasecmp($this->currentUser['role'], 'CEO') === 0),
            'currentUserSigPath' => $currentUserSigPath,
            'depts' => $depts,
            'cutoff_options' => $cutoff_options,
            'default_cutoff' => $default_cutoff,
            'grouped_shifts' => $grouped_shifts,
            'pageHrName' => $pageHrName,
            'pageHrDept' => $pageHrDept,
            'pageCeoName' => $pageCeoName,
            'pageCeoDept' => $pageCeoDept
        ];
        
        extract($data);
        require BASE_PATH . '/src/Views/schedule_view.php';
    }

    public function handleApi()
    {
        $action = $_REQUEST['action'] ?? '';
        $is_hr = (strcasecmp($this->currentUser['role'], 'HR') === 0);
        $is_ceo = (strcasecmp($this->currentUser['role'], 'CEO') === 0);
        $is_head = (strcasecmp($this->currentUser['role'], 'DeptHead') === 0);

        try {
            switch ($action) {
                case 'get_pending_approvals':
                    $target_status = '';
                    if ($is_hr) $target_status = 'Pending HR';
                    if ($is_ceo) $target_status = 'Pending CEO';
    
                    if ($target_status === '') {
                        $this->jsonResponse(true, '', ['approvals' => []]);
                    }
    
                    $sql = "SELECT b.batch_id, b.dept_id, d.dept_name, b.cutoff_label, b.cutoff_start, b.cutoff_end, b.updated_at, 
                                   (SELECT COUNT(*) FROM schedules s 
                                    JOIN employees e ON s.employee_id = e.emp_id 
                                    WHERE e.dept_id = b.dept_id 
                                    AND s.cutoff_start_date = b.cutoff_start) as emp_count
                            FROM schedule_batches b
                            JOIN departments d ON b.dept_id = d.dept_id
                            WHERE b.status = ?
                            ORDER BY b.updated_at DESC";
                    
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$target_status]);
                    
                    $approvals = [];
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                         $start = ($row['cutoff_start']) ? new DateTime($row['cutoff_start']) : null;
                         $end   = ($row['cutoff_end']) ? new DateTime($row['cutoff_end']) : null;
                         $range = ($start && $end) ? $start->format('Y-m-d') . '|' . $end->format('Y-m-d') : '';
                         $lastUpdated = $row['updated_at'] ? (new DateTime($row['updated_at']))->format('M d, Y h:i A') : '-';
    
                        $approvals[] = [
                            'dept_id' => $row['dept_id'],
                            'dept_name' => $row['dept_name'],
                            'cutoff_label' => $row['cutoff_label'],
                            'range_value' => $range,
                            'last_updated' => $lastUpdated,
                            'count' => $row['emp_count']
                        ];
                    }
                    $this->jsonResponse(true, '', ['approvals' => $approvals]);
                    break;

                case 'get_cutoff_options':
                    $query = $_POST['query'] ?? '';
                    $y = date('Y');
                    $m = date('m');
                    
                    if (!empty($query)) {
                        $time = strtotime($query);
                        if ($time) {
                            $y = date('Y', $time);
                            $m = date('m', $time);
                        }
                    }
                    
                    // Generate for that specific month
                    $options = \App\Utils\AppHelpers::generateCutoffPeriods($this->pdo, $y, $m, 1);
                    $this->jsonResponse(true, '', ['options' => $options]);
                    break;

                case 'get_employees_by_dept':
                    $dept_id = $_POST['dept_id'];
                    $sql = "SELECT emp_id, first_name, last_name, ac_no FROM employees WHERE dept_id = ? AND employee_status = 'Active' AND IsActive = 1 ORDER BY last_name";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$dept_id]);
                    $emps = [];
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $emps[] = [
                            'id' => $row['emp_id'], 
                            'ac_no' => $row['ac_no'], 
                            'name' => $row['first_name'] . ' ' . $row['last_name']
                        ];
                    }
                    $this->jsonResponse(true, '', ['employees' => $emps]);
                    break;

                case 'get_schedules':
                    $dept_id = $_POST['dept_id'];
                    $range = $_POST['range']; 
                    list($dS, $dE) = explode('|', $range);
                    
                    $stmtBatch = $this->pdo->prepare("SELECT b.batch_id, b.status, 
                            CONCAT(e.first_name, ' ', e.last_name) as creator_name, 
                            e.signature_path as creator_sig_path,
                            d.dept_name, b.created_by
                     FROM schedule_batches b
                     LEFT JOIN employees e ON b.created_by = e.emp_id
                     LEFT JOIN departments d ON b.dept_id = d.dept_id
                     WHERE b.dept_id = ? AND b.cutoff_start = ?");
                    $stmtBatch->execute([$dept_id, $dS]);
                    $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);
            
                    $status = $batch ? $batch['status'] : 'Not Started';
                    
                    if ($batch) {
                        $dept_name = $batch['dept_name'];
                    } else {
                        $stmtD = $this->pdo->prepare("SELECT dept_name FROM departments WHERE dept_id = ?");
                        $stmtD->execute([$dept_id]);
                        $dept_name = $stmtD->fetchColumn();
                    }
                    
                    $prepared_by = $batch ? $batch['creator_name'] : $this->currentUser['name'];
                    $prepared_sig_path = ($batch && !empty($batch['creator_sig_path'])) ? $batch['creator_sig_path'] : null;
            
                    $stmtHR = $this->pdo->query("SELECT CONCAT(first_name, ' ', last_name) as name, signature_path FROM employees WHERE approval_role = 'HR' AND IsActive = 1 LIMIT 1");
                    $rowHR = $stmtHR->fetch(PDO::FETCH_ASSOC);
                    $checked_by = $rowHR ? $rowHR['name'] : "HR Admin";
                    $checked_sig_path = $rowHR['signature_path'] ?? null;
            
                    $ownerSignatory = $this->getDeptSignatory("Owner");
                    $approved_by = $ownerSignatory['name'];
                    $approved_sig_path = $ownerSignatory['signature_path'];
            
                    $hasFinalized = false;
                    if ($status === 'Approved') {
                        $stmtCheckF = $this->pdo->prepare("SELECT COUNT(*) FROM finalized_schedule fs JOIN employees e ON fs.ac_no = e.ac_no WHERE e.dept_id = ? AND fs.schedule_date BETWEEN ? AND ?");
                        $stmtCheckF->execute([$dept_id, $dS, $dE]);
                        if ($stmtCheckF->fetchColumn() > 0) $hasFinalized = true;
                    }
            
                    $schedules = [];
                    if ($status === 'Approved' && $hasFinalized) {
                        $sqlSched = "SELECT e.emp_id as employee_id, fs.schedule_date, fs.shift_code, fs.time_in, fs.time_out 
                                     FROM finalized_schedule fs
                                     JOIN employees e ON fs.ac_no = e.ac_no
                                     WHERE e.dept_id = ? AND fs.schedule_date BETWEEN ? AND ?";
                    } else {
                        $sqlSched = "SELECT s.employee_id, s.schedule_date, st.shift_code, st.time_in, st.time_out 
                                     FROM schedules s
                                     JOIN employees e ON s.employee_id = e.emp_id
                                     LEFT JOIN shift_types st ON s.shift_type_id = st.id
                                     WHERE e.dept_id = ? AND s.schedule_date BETWEEN ? AND ?";
                    }
                    $stmtSched = $this->pdo->prepare($sqlSched);
                    $stmtSched->execute([$dept_id, $dS, $dE]);
            
                    // Fetch Holidays for this range to highlight in grid
                    $stmtH = $this->pdo->prepare("SELECT holiday_date, holiday_name, holiday_type FROM scheduler_holidays WHERE holiday_date BETWEEN ? AND ?");
                    $stmtH->execute([$dS, $dE]);
                    $holidays = [];
                    while($h = $stmtH->fetch(PDO::FETCH_ASSOC)) {
                        $holidays[$h['holiday_date']] = ['name' => $h['holiday_name'], 'type' => $h['holiday_type']];
                    }

                    while ($row = $stmtSched->fetch(PDO::FETCH_ASSOC)) {
                        $dateKey = $row['schedule_date'];
                        $tIn = $row['time_in']; $tOut = $row['time_out'];
                        $code = $row['shift_code'] ?? '-';
                        
                        $displayTime = '-';
                        if ($tIn && $tOut) {
                            $displayTime = (new DateTime($tIn))->format('h:iA') . '-' . (new DateTime($tOut))->format('h:iA');
                        }
                        if(in_array($code, ['OFF','FLEX','HOLIDAY OFF','LWOP','LWP'])) $displayTime = $code; 
                        
                        $schedules[$row['employee_id']][$dateKey] = ['code' => $code, 'time' => $displayTime];
                    }
            
                    $this->jsonResponse(true, '', [
                        'status' => $status,
                        'dept_name' => $dept_name,
                        'signatories' => [
                            'prepared' => $prepared_by, 'prepared_sig' => $prepared_sig_path,
                            'checked' => $checked_by, 'checked_sig' => $checked_sig_path,
                            'approved' => $approved_by, 'approved_sig' => $approved_sig_path
                        ],
                        'can_edit' => ($is_head && in_array($status, ['Not Started', 'Draft', 'Rejected'])),
                        'can_reset' => ($status == 'Approved' && ($is_ceo || $is_hr)), 
                        'is_hr' => $is_hr,
                        'can_approve_hr' => ($is_hr && $status == 'Pending HR'),
                        'can_approve_ceo' => ($is_ceo && $status == 'Pending CEO'),
                        'schedules' => $schedules,
                        'holidays' => $holidays
                    ]);
                    break;

                case 'save_schedule':
                    $emp_id = $_POST['employee_id'];
                    $date = $_POST['schedule_date'];
                    $code = $_POST['shift_code'];
                    $dept_id = $_POST['dept_id'];
                    $range = $_POST['range'];
                    list($start, $end) = explode('|', $range);
            
                    $stmtCheck = $this->pdo->prepare("SELECT batch_id FROM schedule_batches WHERE dept_id = ? AND cutoff_start = ?");
                    $stmtCheck->execute([$dept_id, $start]);
                    $batch_id = $stmtCheck->fetchColumn();
            
                    if (!$batch_id) {
                        // Create Draft Batch
                        $label = (new DateTime($start))->format('M d') . ' - ' . (new DateTime($end))->format('M d') . ', ' . (new DateTime($start))->format('Y');
                        $stmtIns = $this->pdo->prepare("INSERT INTO schedule_batches (dept_id, cutoff_label, cutoff_start, cutoff_end, status, created_by) VALUES (?, ?, ?, ?, 'Draft', ?)");
                        $stmtIns->execute([$dept_id, $label, $start, $end, $this->currentUser['id']]);
                        $batch_id = $this->pdo->lastInsertId();
                        $this->logHistory($batch_id, 'Created', $this->currentUser['name'], $this->currentUser['role'], 'Started draft schedule');
                    }
            
                    // Update Schedule
                    $this->pdo->prepare("DELETE FROM schedules WHERE employee_id = ? AND schedule_date = ?")->execute([$emp_id, $date]);
            
                    $stmtShift = $this->pdo->prepare("SELECT id FROM shift_types WHERE shift_code = ?");
                    $stmtShift->execute([$code]);
                    $shiftTypeId = $stmtShift->fetchColumn();
                    
                    if (!$shiftTypeId) {
                        // Auto-create special shift type if not exists
                        $this->pdo->prepare("INSERT INTO shift_types (shift_code, shift_name, is_special) VALUES (?, ?, 1)")->execute([$code, $code]);
                        $shiftTypeId = $this->pdo->lastInsertId();
                    }

                    if ($shiftTypeId) {
                        $this->pdo->prepare("INSERT INTO schedules (employee_id, schedule_date, shift_type_id, cutoff_period, cutoff_start_date, cutoff_end_date) VALUES (?, ?, ?, ?, ?, ?)")
                                  ->execute([$emp_id, $date, $shiftTypeId, $range, $start, $end]);
                    }
                    $this->jsonResponse(true);
                    break;

                case 'update_status':
                    $dept_id = $_POST['dept_id'];
                    $range = $_POST['range'];
                    list($start, $end) = explode('|', $range);
                    $new_status = $_POST['status'];
                    $comments = $_POST['comments'] ?? '';
            
                    $stmtBatch = $this->pdo->prepare("SELECT batch_id FROM schedule_batches WHERE dept_id = ? AND cutoff_start = ?");
                    $stmtBatch->execute([$dept_id, $start]);
                    $batch_id = $stmtBatch->fetchColumn();
                    
                    if ($batch_id) {
                        $this->pdo->prepare("UPDATE schedule_batches SET status = ?, updated_at = NOW() WHERE batch_id = ?")->execute([$new_status, $batch_id]);
            
                        if ($new_status == 'Pending HR') {
                            $this->pdo->prepare("UPDATE schedule_batches SET created_by = ? WHERE batch_id = ?")->execute([$this->currentUser['id'], $batch_id]);
                        }
            
                        if ($new_status == 'Approved') {
                            // Finalize Logic
                             $this->pdo->prepare("DELETE fs FROM finalized_schedule fs JOIN employees e ON fs.ac_no = e.ac_no WHERE e.dept_id = ? AND fs.schedule_date BETWEEN ? AND ?")->execute([$dept_id, $start, $end]);
                             
                             $sqlMigrate = "INSERT INTO finalized_schedule (ac_no, schedule_date, shift_code, time_in, time_out, name, department)
                                SELECT e.ac_no, s.schedule_date, st.shift_code,
                                    CASE WHEN st.time_in IS NULL THEN NULL ELSE ADDTIME(CAST(s.schedule_date AS DATETIME), st.time_in) END,
                                    CASE WHEN st.time_out IS NULL THEN NULL WHEN st.time_out < st.time_in THEN ADDTIME(CAST(s.schedule_date + INTERVAL 1 DAY AS DATETIME), st.time_out) ELSE ADDTIME(CAST(s.schedule_date AS DATETIME), st.time_out) END,
                                    CONCAT(e.first_name, ' ', e.last_name), d.dept_name
                                FROM schedules s
                                INNER JOIN employees e ON s.employee_id = e.emp_id
                                INNER JOIN departments d ON e.dept_id = d.dept_id
                                LEFT JOIN shift_types st ON s.shift_type_id = st.id
                                WHERE s.schedule_date BETWEEN ? AND ? AND e.dept_id = ?";
                             $this->pdo->prepare($sqlMigrate)->execute([$start, $end, $dept_id]);
                        }
            
                        if ($new_status == 'Draft') {
                            $this->pdo->prepare("DELETE fs FROM finalized_schedule fs JOIN employees e ON fs.ac_no = e.ac_no WHERE e.dept_id = ? AND fs.schedule_date BETWEEN ? AND ?")->execute([$dept_id, $start, $end]);
                        }
            
                        $actionLabel = ($new_status == 'Pending HR') ? 'Submitted' : $new_status;
                        $this->logHistory($batch_id, $actionLabel, $this->currentUser['name'], $this->currentUser['role'], $comments);
                    }
                    $this->jsonResponse(true);
                    break;
            }

        } catch (Exception $e) {
            $this->jsonResponse(false, $e->getMessage());
        }
    }

    // --- Helpers ---

    private function jsonResponse($success, $message = '', $data = []) {
        if (ob_get_length()) ob_clean(); 
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
        exit;
    }



    private function getReferencedShifts() {
        $grouped = [
            'AM Shifts (05:00 - 09:59)' => [],
            'Mid Shifts (10:00 - 13:59)' => [],
            'PM Shifts (14:00 - 17:59)' => [],
            'Graveyard Shifts (18:00+)' => [],
            'Leaves / Special' => []
        ];
        
        $stmt = $this->pdo->query("SELECT shift_code, shift_name, time_in, time_out, is_special FROM shift_types");
        while($s = $stmt->fetch(PDO::FETCH_ASSOC)) { 
            if ($s['time_in']) $s['time_in'] = new DateTime($s['time_in']);
            if ($s['time_out']) $s['time_out'] = new DateTime($s['time_out']);
        
            if ($s['is_special'] == 1) {
                $grouped['Leaves / Special'][] = $s;
            } else if ($s['time_in'] instanceof DateTime) {
                $H = (int)$s['time_in']->format('G');
                if ($H >= 5 && $H < 10) $grouped['AM Shifts (05:00 - 09:59)'][] = $s;
                else if ($H >= 10 && $H < 14) $grouped['Mid Shifts (10:00 - 13:59)'][] = $s;
                else if ($H >= 14 && $H < 18) $grouped['PM Shifts (14:00 - 17:59)'][] = $s;
                else $grouped['Graveyard Shifts (18:00+)'][] = $s;
            }
        }
        $specials = ['OFF', 'FLEX', 'HOLIDAY OFF', 'LWOP', 'LWP'];
        foreach($specials as $c) $grouped['Leaves / Special'][] = ['shift_code'=>$c, 'shift_name'=>$c, 'time_in'=>null, 'time_out'=>null];

        return $grouped;
    }

    private function logHistory($batch_id, $action, $name, $role, $comments) {
        $this->pdo->prepare("INSERT INTO schedule_history (batch_id, action, actor_name, actor_role, comments) VALUES (?, ?, ?, ?, ?)")
                  ->execute([$batch_id, $action, $name, $role, $comments]);
    }

    private function getDeptSignatory($deptName) {
        $stmt = $this->pdo->prepare("SELECT CONCAT(e.first_name, ' ', e.last_name) as name, e.signature_path FROM employees e JOIN departments d ON e.dept_id = d.dept_id WHERE d.dept_name = ? AND e.approval_role = 'DeptHead' AND e.IsActive = 1 LIMIT 1");
        $stmt->execute([$deptName]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) return $row;

        // Fallback
        $stmt2 = $this->pdo->prepare("SELECT CONCAT(e.first_name, ' ', e.last_name) as name, e.signature_path FROM employees e JOIN departments d ON e.dept_id = d.dept_id WHERE d.dept_name = ? AND e.IsActive = 1 LIMIT 1");
        $stmt2->execute([$deptName]);
        if ($row2 = $stmt2->fetch(PDO::FETCH_ASSOC)) return $row2;

        return ['name' => $deptName . ' Head', 'signature_path' => null];
    }
}


