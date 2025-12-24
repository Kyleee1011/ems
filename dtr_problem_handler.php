<?php
session_start();
require_once 'config.php';

use App\Utils\AppHelpers;
use App\Services\FileUploadService;

$conn = $pdo;
$current_role = $_SESSION['approval_role'] ?? 'Employee';
$is_hr = ($current_role === 'HR');

// --- BIOLOGS CONNECTION ---
$biologsServer = "192.168.21.52, 1433";
$biologsDB = "biologs_db";
$biologsUser = "sa";
$biologsPass = "Azzurro2025";

try {
    $bioConn = new PDO("sqlsrv:server=$biologsServer;Database=$biologsDB", $biologsUser, $biologsPass);
    $bioConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Report Problem (Employee)
    if ($_POST['action'] === 'report_problem') {
        try {
            // Status defaults to 'Submitted'
            $stmt = $conn->prepare("INSERT INTO DTRProblems (emp_id, log_date, issue_type, description, status) VALUES (?, ?, ?, ?, 'Submitted')");
            $stmt->execute([$_POST['emp_id'], $_POST['log_date'], $_POST['issue_type'], $_POST['description']]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // 2. Update Time (HR Only)
    if ($_POST['action'] === 'update_time') {
        if (!$is_hr) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }
        
        $ac_no = $_POST['ac_no'];
        $date = $_POST['date'];
        $type = $_POST['type']; 
        $new_time = $_POST['new_time']; 
        
        // Format for SQL Server DateTime (YYYY-MM-DD HH:MI:SS)
        $logTime = $date . ' ' . $new_time . ':00';
        $checkType = ($type === 'IN') ? 'I' : 'O';

        try {
            // FIX: Fetch the Internal_UserID for this AC_No first
            // The table requires this column and it cannot be NULL.
            $uidStmt = $bioConn->prepare("SELECT TOP 1 Internal_UserID FROM AttendanceData WHERE AC_No = ?");
            $uidStmt->execute([$ac_no]);
            $uidRow = $uidStmt->fetch(PDO::FETCH_ASSOC);
            
            // If user exists, use their ID. If new/unknown, default to 0 (or 1) to satisfy NOT NULL constraint.
            $internal_uid = $uidRow ? $uidRow['Internal_UserID'] : 0; 

            // Insert the manual log with the Internal_UserID
            $stmt = $bioConn->prepare("INSERT INTO AttendanceData (AC_No, LogTime, CheckType, EmployeeName, Internal_UserID) VALUES (?, ?, ?, 'Manual Correction', ?)");
            $stmt->execute([$ac_no, $logTime, $checkType, $internal_uid]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // 3. Get My Problems (Employee)
    if ($_POST['action'] === 'get_my_problems') {
        $emp_id = $_POST['emp_id'];
        $stmt = $conn->prepare("SELECT issue_type, description, log_date, status FROM DTRProblems WHERE emp_id = ? ORDER BY created_at DESC");
        $stmt->execute([$emp_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    // 4. Get All Submitted Problems (HR Only)
    if ($_POST['action'] === 'get_all_problems') {
        if (!$is_hr) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }
        // Join with Employees to get names
        $sql = "SELECT p.problem_id, p.issue_type, p.description, p.log_date, p.status, e.first_name, e.last_name 
                FROM DTRProblems p 
                JOIN Employees e ON p.emp_id = e.emp_id 
                WHERE p.status = 'Submitted' OR p.status = 'Pending' 
                ORDER BY p.created_at DESC";
        $data = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted = [];
        foreach($data as $row) {
            $row['employee_name'] = $row['first_name'] . ' ' . $row['last_name'];
            $formatted[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $formatted]);
        exit;
    }

    // 5. Mark as Complete (HR Only)
    if ($_POST['action'] === 'mark_complete') {
        if (!$is_hr) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }
        $id = $_POST['problem_id'];
        
        $stmt = $conn->prepare("UPDATE DTRProblems SET status = 'Completed', resolved_by = ?, resolved_at = GETDATE() WHERE problem_id = ?");
        $stmt->execute([$_SESSION['user_id'], $id]);
        echo json_encode(['success' => true]);
        exit;
    }
}
?>