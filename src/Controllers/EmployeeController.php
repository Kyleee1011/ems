<?php
namespace App\Controllers;

use App\Models\Employee;
use App\Utils\AppHelpers;
use App\Services\FileUploadService;
use PDO;
use Exception;

class EmployeeController
{
    protected $employeeModel;
    protected $currentUser;
    protected $userRole;

    public function __construct()
    {
        global $pdo;
        $this->employeeModel = new Employee($pdo);
require_once dirname(dirname(__DIR__)) . '/config_session.php';
        if (!isset($_SESSION['user_id'])) { header("Location: " . baseUrl('login')); exit; }
        
        $this->currentUser = $_SESSION['user_id'];
        $this->userRole = trim($_SESSION['approval_role'] ?? 'HR');
    }

    public function index()
    {
        // --- 1. HANDLE SPECIAL GET ACTIONS (Downloads) ---
        if (isset($_GET['action'])) {
            $this->handleGetActions($_GET['action']);
        }

        // --- 2. HANDLE POST ACTIONS ---
        $message = ""; 
        $messageType = "";
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
            try {
                $this->handlePostActions($_POST['action'], $message, $messageType);
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = "error";
            }
        }

        // --- 3. DATA FETCHING ---
        $depts = [];
        $supervisors = [];
        $activeEmployees = [];
        $totalRecords = 0;
        $totalPages = 1;

        try {
            $depts = $this->employeeModel->getDepartments();
            $supervisors = $this->employeeModel->getSupervisors();

            $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
            $filterDept = isset($_GET['dept']) ? $_GET['dept'] : '';
            $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = 8;
            
            $employeeData = $this->employeeModel->getPaginatedEmployees($searchTerm, $filterDept, $page, $limit);
            $activeEmployees = $employeeData['employees'];
            $totalRecords = $employeeData['totalRecords'];
            $totalPages = $employeeData['totalPages'];
        } catch (Exception $e) {
            $message = "Error loading data: " . $e->getMessage();
            $messageType = "error";
        }

        // Inactive Employees Data
        try {
            $inactiveEmployeesData = $this->employeeModel->getPaginatedEmployees('', '', 1, 999, false);
            $inactiveEmployees = $inactiveEmployeesData['employees'];
        } catch (Exception $e) {
            $inactiveEmployees = [];
        }

        // Notification Logic
        $totalPending = 0;
        try {
            $pLeaves = $this->employeeModel->getPendingLeaveCount();
            $pOT = $this->employeeModel->getPendingOvertimeCount();
            $pSched = $this->employeeModel->getPendingScheduleCount($this->userRole);
            $totalPending = $pLeaves + $pOT + $pSched;
        } catch (Exception $e) { $totalPending = 0; }

        // Determine Active Tab
        $activeTab = $_GET['tab'] ?? 'directory'; 
        if (isset($_GET['dept']) || isset($_GET['search'])) $activeTab = 'directory';

        $current_fullname = $_SESSION['full_name'] ?? 'User';
        $user_role = $this->userRole;

        // --- 4. RENDER VIEW ---
        require BASE_PATH . '/src/Views/employee_view.php';
    }

    private function handleGetActions($action)
    {
        if ($action == 'download_template') {
            ob_end_clean(); 
            header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="employee_upload_template.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['AC No (Required)', 'Last Name (Required)', 'First Name (Required)', 'Middle Name', 'DOB (YYYY-MM-DD)', 'Gender', 'Address', 'Contact No', 'Email', 'Civil Status', 'Nationality', 'Emerg Name', 'Emerg No', 'Emerg Rel', 'Dept ID (Number)', 'Job Title', 'Job Level', 'Emp Status (Regular/Probationary)', 'Location', 'Supervisor ID (Number)', 'Emp Type (Full-time)', 'Work Schedule', 'Basic Monthly Salary', 'Payroll Group', 'TIN', 'SSS', 'PhilHealth', 'PagIBIG', 'Bank Acct No', 'Date Hired (YYYY-MM-DD)', 'Date Deployed', 'Contract Start', 'Contract End', 'System Role (Employee/HR/CEO)', 'SIL Credits']);
            fputcsv($out, ['EMP001', 'Doe', 'John', 'A', '1990-01-01', 'Male', '123 Main St', '09123456789', 'email@test.com', 'Single', 'Filipino', 'Jane', '09123456789', 'Wife', '1', 'Dev', 'Rank', 'Active', 'Office', '2', 'Full', 'Office', '25000', 'Direct', '123', '123', '123', '123', '123', '2023-01-01', '2023-01-01', '2023-01-01', '2024-01-01', 'Employee', '5']);
            fclose($out); exit();
        }
        if ($action == 'export_employees') {
            ob_end_clean();
            header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="Employee_List_'.date('Ymd').'.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['AC No', 'Name', 'Department', 'Job Title', 'Status', 'Hired', 'Basic Salary']);
            $filterDept = $_GET['dept'] ?? '';
            $employeesToExport = $this->employeeModel->exportEmployees($filterDept);
            foreach($employeesToExport as $r) {
                fputcsv($out, [$r['ac_no'], $r['last_name'].', '.$r['first_name'], $r['dept_name'], $r['job_title'], $r['employment_status'], $r['date_hired'], $r['salary_rate']]);
            }
            fclose($out); exit();
        }
        if ($action == 'get_employee_json' && isset($_GET['emp_id'])) {
            ob_end_clean(); header('Content-Type: application/json');
            try { 
                $details = $this->employeeModel->getEmployeeFullDetails($_GET['emp_id']);
                if (!$details) throw new Exception("Employee not found.");
                echo json_encode(array_merge(['success' => true], $details)); 
            } 
            catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
            exit();
        }
        if ($action == 'search_suggestions' && isset($_GET['q'])) {
            ob_end_clean(); header('Content-Type: application/json');
            try { echo json_encode($this->employeeModel->getSearchSuggestions($_GET['q'])); } 
            catch (Exception $e) { echo json_encode([]); }
            exit();
        }
    }

    private function handlePostActions($action, &$message, &$messageType)
    {
        if ($action == 'bulk_upload' && isset($_FILES['csv_file'])) {
            if ($_FILES['csv_file']['error'] == 0) {
                $rowCount = $this->employeeModel->bulkUploadEmployees($_FILES['csv_file']['tmp_name']);
                $message = "Uploaded $rowCount employees!";
                $messageType = "success";
            } else {
                $message = "Invalid file.";
                $messageType = "error";
            }
        } elseif ($action == 'add_employee' || $action == 'update_employee') {
            $ac_no = $_POST['ac_no'] ?? 'TEMP'; 
            $emp_id = $_POST['emp_id'] ?? null;
            $s = (isset($_FILES['signature_file']) && $_FILES['signature_file']['error']==0) ? FileUploadService::handleFileUpload($_FILES['signature_file'], $ac_no, 'signatures') : null;
            $p = (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error']==0) ? FileUploadService::handleFileUpload($_FILES['profile_photo'], $ac_no, 'profile_pictures', true) : null;
            
            // Safe extraction of POST data with defaults
            $getPost = function($key, $default = null) {
                return (isset($_POST[$key]) && $_POST[$key] !== '') ? $_POST[$key] : $default;
            };

            $d = [
                'ac_no' => $ac_no,
                'last_name' => $getPost('last_name'),
                'first_name' => $getPost('first_name'),
                'middle_name' => $getPost('middle_name'),
                'date_of_birth' => AppHelpers::cleanDate($getPost('date_of_birth')),
                'gender' => $getPost('gender'),
                'address' => $getPost('address'),
                'contact_number' => $getPost('contact_number'),
                'email_address' => $getPost('email_address'),
                'civil_status' => $getPost('civil_status'),
                'nationality' => $getPost('nationality'),
                'emergency_contact_name' => $getPost('emergency_contact_name'),
                'emergency_contact_number' => $getPost('emergency_contact_number'),
                'emergency_contact_relationship' => $getPost('emergency_contact_relationship'),
                'dept_id' => AppHelpers::cleanNum($getPost('dept_id')),
                'job_title' => $getPost('job_title'),
                'job_level' => $getPost('job_level', 'Rank and File'),
                'employment_status' => $getPost('employment_status'),
                'location_assignment' => $getPost('location_assignment'),
                'supervisor_id' => AppHelpers::cleanNum($getPost('supervisor_id')),
                'employment_type' => $getPost('employment_type'),
                'work_schedule' => $getPost('work_schedule'),
                'salary_rate' => AppHelpers::cleanNum($getPost('salary_rate')),
                'daily_rate' => AppHelpers::cleanNum($getPost('daily_rate')),
                'hourly_rate' => AppHelpers::cleanNum($getPost('hourly_rate')),
                'payroll_group' => $getPost('payroll_group'),
                'tin_number' => $getPost('tin_number'),
                'sss_number' => $getPost('sss_number'),
                'philhealth_number' => $getPost('philhealth_number'),
                'pagibig_number' => $getPost('pagibig_number'),
                'bank_account_number' => $getPost('bank_account_number'),
                'date_hired' => AppHelpers::cleanDate($getPost('date_hired')),
                'date_deployed' => AppHelpers::cleanDate($getPost('date_deployed')),
                'contract_start_date' => AppHelpers::cleanDate($getPost('contract_start_date')),
                'contract_end_date' => AppHelpers::cleanDate($getPost('contract_end_date')),
                'employee_status' => 'Active',
                'approval_role' => $getPost('approval_role'),
                'sil_credits' => AppHelpers::cleanNum($getPost('sil_credits')),
                'date_regularized' => AppHelpers::cleanDate($getPost('date_regularized')),
                'work_days_mode' => $getPost('work_days_mode', '6')
            ];

            if($action=='add_employee') $this->employeeModel->addEmployee($d, $s, $p, $this->currentUser);
            else $this->employeeModel->updateEmployee($emp_id, $d, $s, $p, $this->currentUser);
            $message = "Saved successfully!"; $messageType = "success";
        } elseif ($action == 'upload_document') {
            if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
                $fp = FileUploadService::handleFileUpload($_FILES['doc_file'], $_POST['ac_no'].'_'.$_POST['doc_name'], 'documents');
                if ($fp) { $this->employeeModel->uploadDocument($_POST['emp_id'], $_POST['doc_name'], $fp); $message = "Uploaded!"; $messageType = "success"; } else { $message = "Upload failed."; $messageType = "error"; }
            } else { $message = "No file."; $messageType = "error"; }
        } elseif (in_array($action, ['soft_delete', 'restore', 'hard_delete', 'delete_document'])) {
            if($action=='delete_document') $this->employeeModel->deleteDocument($_POST['doc_id']);
            if($action=='soft_delete') $this->employeeModel->softDeleteEmployee($_POST['emp_id']);
            if($action=='restore') $this->employeeModel->restoreEmployee($_POST['emp_id']);
            if($action=='hard_delete') $this->employeeModel->hardDeleteEmployee($_POST['emp_id']);
            $message = "Action completed."; $messageType = "success";
        }
    }
}


