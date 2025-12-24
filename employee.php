<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config.php';

use App\Models\Employee;
use App\Utils\AppHelpers;
use App\Services\FileUploadService;

// User Context
$current_user_id = $_SESSION['user_id'] ?? 1; 
$current_fullname = $_SESSION['full_name'] ?? 'System Admin';
$user_role = trim($_SESSION['approval_role'] ?? 'HR'); 

// Instantiate Employee Model
$employeeModel = new Employee($pdo);

// ============================================
// 1. GET HANDLERS (Download, Export, JSON)
// ============================================

// --- CSV TEMPLATE DOWNLOAD ---
if (isset($_GET['action']) && $_GET['action'] == 'download_template') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="employee_upload_template.csv"');
    
    $output = fopen('php://output', 'w');
    
    $headers = [
        'AC No (Required)', 'Last Name (Required)', 'First Name (Required)', 'Middle Name', 'DOB (YYYY-MM-DD)', 
        'Gender', 'Address', 'Contact No', 'Email', 'Civil Status', 'Nationality',
        'Emerg. Contact Name', 'Emerg. Contact No', 'Emerg. Relationship',
        'Dept ID (Number)', 'Job Title', 'Job Level', 'Emp Status (Regular/Probationary)', 'Location', 
        'Supervisor ID (Number)', 'Emp Type (Full-time)', 'Work Schedule', 
        'Basic Monthly Salary', 'Payroll Group',
        'TIN', 'SSS', 'PhilHealth', 'PagIBIG', 'Bank Acct No',
        'Date Hired (YYYY-MM-DD)', 'Date Deployed', 'Contract Start', 'Contract End',
        'System Role (Employee/HR/CEO)', 'SIL Credits'
    ];
    
    fputcsv($output, $headers);
    
    $example = [
        'EMP001', 'Doe', 'John', 'A', '1990-01-01', 
        'Male', '123 Main St', '+63 912 345 6789', 'john@email.com', 'Single', 'Filipino',
        'Jane Doe', '09987654321', 'Spouse',
        '1', 'Software Engineer', 'Rank and File', 'Regular', 'Manila',
        '2', 'Full-time', 'Office Based',
        '25000.00', 'Direct Employee',
        '123-456-789', '01-1234567-8', '1234-5678-9012', '1234-5678-9012', '10987654321',
        '2023-01-01', '2023-01-15', '2023-01-01', '2024-01-01',
        'Employee', '5.0'
    ];
    
    fputcsv($output, $example);
    fclose($output);
    exit();
}

// --- EXPORT EMPLOYEES CSV ---
if (isset($_GET['action']) && $_GET['action'] == 'export_employees') {
    $filterDept = isset($_GET['dept']) ? trim($_GET['dept']) : '';
    $filename = "Employee_List_" . ($filterDept ? $filterDept : "All") . "_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, [
        'AC No', 'Last Name', 'First Name', 'Middle Name', 'Department', 'Job Title', 
        'Employment Status', 'Date Hired', 'Contact Number', 'Email', 
        'SSS', 'PhilHealth', 'PagIBIG', 'TIN', 'Basic Salary'
    ]);
    
    $sql = "SELECT e.ac_no, e.last_name, e.first_name, e.middle_name, d.dept_name, e.job_title, 
                   e.employment_status, e.date_hired, e.contact_number, e.email_address,
                   e.sss_number, e.philhealth_number, e.pagibig_number, e.tin_number, e.salary_rate
            FROM [EmployeeManagementSystem].[dbo].[Employees] e
            LEFT JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON e.dept_id = d.dept_id
            WHERE e.IsActive = 1";
    
    $params = [];
    if (!empty($filterDept)) {
        $sql .= " AND d.dept_name = ?";
        $params[] = $filterDept;
    }
    
    $sql .= " ORDER BY e.last_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hired = $row['date_hired'] ? date('Y-m-d', strtotime($row['date_hired'])) : '';
        fputcsv($output, [
            $row['ac_no'], $row['last_name'], $row['first_name'], $row['middle_name'], 
            $row['dept_name'], $row['job_title'], $row['employment_status'], $hired, 
            $row['contact_number'], $row['email_address'],
            $row['sss_number'], $row['philhealth_number'], $row['pagibig_number'], $row['tin_number'], 
            number_format((float)$row['salary_rate'], 2, '.', '')
        ]);
    }
    
    fclose($output);
    exit();
}

// --- JSON HANDLER (AJAX) ---
if (isset($_GET['action']) && $_GET['action'] == 'get_employee_json' && isset($_GET['emp_id'])) {
    header('Content-Type: application/json');
    try {
        $emp_id = $_GET['emp_id'];
        $employeeData = $employeeModel->getEmployeeFullDetails($emp_id);
        if ($employeeData) {
            echo json_encode(['success' => true] + $employeeData);
        } else {
            echo json_encode(['success' => false, 'message' => 'Employee not found.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================
// 2. POST HANDLERS (CRUD)
// ============================================
$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // --- BULK UPLOAD ---
    if ($_POST['action'] == 'bulk_upload') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            $fileName = $_FILES['csv_file']['tmp_name'];
            try {
                $pdo->beginTransaction();
                $handle = fopen($fileName, "r");
                fgetcsv($handle); // Skip Header
                $rowCount = 0;
                
                $sql = "INSERT INTO [EmployeeManagementSystem].[dbo].[Employees] (
                    ac_no, last_name, first_name, middle_name, date_of_birth, gender, 
                    address, contact_number, email_address, civil_status, nationality,
                    emergency_contact_name, emergency_contact_number, emergency_contact_relationship,
                    dept_id, job_title, job_level, employment_status, location_assignment, supervisor_id,
                    employment_type, work_schedule, salary_rate, daily_rate, hourly_rate, payroll_group,
                    tin_number, sss_number, philhealth_number, pagibig_number, bank_account_number,
                    date_hired, date_deployed, contract_start_date, contract_end_date, employee_status,
                    approval_role, sil_credits, IsActive
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, 1)";
                
                $stmt = $pdo->prepare($sql);
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $val = function($index) use ($data) { return (isset($data[$index]) && trim($data[$index]) !== '') ? trim($data[$index]) : null; };
                    if(!$val(0) || !$val(1) || !$val(2)) { continue; } // Skip if required fields missing
                    
                    $basic = AppHelpers::cleanNum($val(22));
                    $rates = AppHelpers::calculateRatesFromMonthly($basic);
                    $sil = AppHelpers::cleanNum($val(34)) ?? 0;

                    $params = [
                        $val(0), $val(1), $val(2), $val(3), AppHelpers::cleanDate($val(4)), $val(5),
                        $val(6), $val(7), $val(8), $val(9), $val(10),
                        $val(11), $val(12), $val(13),
                        AppHelpers::cleanNum($val(14)), $val(15), $val(16), $val(17), $val(18), AppHelpers::cleanNum($val(19)),
                        $val(20), $val(21), 
                        $basic, $rates['daily'], $rates['hourly'], 
                        $val(23), $val(24), $val(25), $val(26), $val(27), $val(28),
                        AppHelpers::cleanDate($val(29)), AppHelpers::cleanDate($val(30)), AppHelpers::cleanDate($val(31)), AppHelpers::cleanDate($val(32)),
                        $val(33) ?: 'Employee', 
                        $sil 
                    ];
                    $stmt->execute($params);
                    $rowCount++;
                }
                fclose($handle);
                $pdo->commit();
                $message = "Successfully uploaded $rowCount employees!"; 
                $messageType = "success";
            } catch (Exception $e) { 
                $pdo->rollBack(); 
                $message = "Upload Failed: " . $e->getMessage(); 
                $messageType = "error"; 
            }
        } else { 
            $message = "Please select a valid CSV file."; 
            $messageType = "error"; 
        }
    }

    // --- ADD EMPLOYEE ---
    elseif ($_POST['action'] == 'add_employee') {
        try {
            $ac_no = $_POST['ac_no'] ?? 'TEMP';
            
            $signaturePath = null;
            if (isset($_FILES['signature_file'])) { 
                $signaturePath = FileUploadService::handleFileUpload($_FILES['signature_file'], $ac_no, 'signatures'); 
            }

            $profilePicPath = null;
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
                $profilePicPath = FileUploadService::handleFileUpload($_FILES['profile_photo'], $ac_no, 'profile_pictures', true);
            }

            $employeeData = [
                'ac_no' => $ac_no,
                'last_name' => $_POST['last_name'], 'first_name' => $_POST['first_name'], 'middle_name' => $_POST['middle_name'],
                'date_of_birth' => AppHelpers::cleanDate($_POST['date_of_birth']), 'gender' => $_POST['gender'], 'address' => $_POST['address'],
                'contact_number' => $_POST['contact_number'], 'email_address' => $_POST['email_address'], 'civil_status' => $_POST['civil_status'], 'nationality' => $_POST['nationality'],
                'emergency_contact_name' => $_POST['emergency_contact_name'], 'emergency_contact_number' => $_POST['emergency_contact_number'], 'emergency_contact_relationship' => $_POST['emergency_contact_relationship'],
                'dept_id' => AppHelpers::cleanNum($_POST['dept_id']),
                'job_title' => $_POST['job_title'],
                'job_level' => $_POST['job_level'] ?? 'Rank and File',
                'employment_status' => $_POST['employment_status'],
                'location_assignment' => $_POST['location_assignment'], 'supervisor_id' => AppHelpers::cleanNum($_POST['supervisor_id']), 'employment_type' => $_POST['employment_type'], 'work_schedule' => $_POST['work_schedule'],
                'salary_rate' => AppHelpers::cleanNum($_POST['salary_rate'] ?? 0), 'daily_rate' => AppHelpers::cleanNum($_POST['daily_rate'] ?? 0), 'hourly_rate' => AppHelpers::cleanNum($_POST['hourly_rate'] ?? 0),
                'payroll_group' => $_POST['payroll_group'], 'tin_number' => $_POST['tin_number'], 'sss_number' => $_POST['sss_number'], 'philhealth_number' => $_POST['philhealth_number'], 'pagibig_number' => $_POST['pagibig_number'], 'bank_account_number' => $_POST['bank_account_number'],
                'date_hired' => AppHelpers::cleanDate($_POST['date_hired']), 'date_deployed' => AppHelpers::cleanDate($_POST['date_deployed']), 'contract_start_date' => AppHelpers::cleanDate($_POST['contract_start_date']), 'contract_end_date' => AppHelpers::cleanDate($_POST['contract_end_date']),
                'employee_status' => $_POST['employee_status'] ?? 'Active', 'approval_role' => ($_POST['approval_role'] ?? 'Employee'),
                'sil_credits' => AppHelpers::cleanNum($_POST['sil_credits'] ?? 0),
            ];
            
            $employeeModel->addEmployee($employeeData, $signaturePath, $profilePicPath, $current_user_id);
            $message = "Employee onboarded successfully!"; $messageType = "success";
        } catch(PDOException $e) { 
            $message = "Error: " . $e->getMessage(); $messageType = "error"; 
        }
    }
    
    // --- UPDATE EMPLOYEE ---
    elseif ($_POST['action'] == 'update_employee') {
        try {
            $emp_id = $_POST['emp_id'] ?? null;
            $ac_no = $_POST['ac_no'] ?? $emp_id;

            $signaturePath = null;
            if (isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] === UPLOAD_ERR_OK) {
                $signaturePath = FileUploadService::handleFileUpload($_FILES['signature_file'], $ac_no, 'signatures');
            }

            $profilePicPath = null;
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $profilePicPath = FileUploadService::handleFileUpload($_FILES['profile_photo'], $ac_no, 'profile_pictures', true);
            }

            $employeeData = [
                'last_name' => $_POST['last_name'] ?? null, 'first_name' => $_POST['first_name'] ?? null, 'middle_name' => $_POST['middle_name'] ?? null,
                'date_of_birth' => AppHelpers::cleanDate($_POST['date_of_birth'] ?? null), 'gender' => $_POST['gender'] ?? null, 'address' => $_POST['address'] ?? null,
                'contact_number' => $_POST['contact_number'] ?? null, 'email_address' => $_POST['email_address'] ?? null, 'civil_status' => $_POST['civil_status'] ?? null,
                'nationality' => $_POST['nationality'] ?? null, 'emergency_contact_name' => $_POST['emergency_contact_name'] ?? null, 'emergency_contact_number' => $_POST['emergency_contact_number'] ?? null,
                'emergency_contact_relationship' => $_POST['emergency_contact_relationship'] ?? null, 'dept_id' => AppHelpers::cleanNum($_POST['dept_id'] ?? null),
                'job_title' => $_POST['job_title'] ?? null,
                'job_level' => $_POST['job_level'] ?? 'Rank and File',
                'employment_status' => $_POST['employment_status'] ?? null, 'location_assignment' => $_POST['location_assignment'] ?? null, 'supervisor_id' => AppHelpers::cleanNum($_POST['supervisor_id'] ?? null),
                'employment_type' => $_POST['employment_type'] ?? null, 'work_schedule' => $_POST['work_schedule'] ?? null, 'payroll_group' => $_POST['payroll_group'] ?? null,
                'salary_rate' => AppHelpers::cleanNum($_POST['salary_rate'] ?? 0), 'daily_rate' => AppHelpers::cleanNum($_POST['daily_rate'] ?? 0), 'hourly_rate' => AppHelpers::cleanNum($_POST['hourly_rate'] ?? 0),
                'tin_number' => $_POST['tin_number'] ?? null, 'sss_number' => $_POST['sss_number'] ?? null, 'philhealth_number' => $_POST['philhealth_number'] ?? null,
                'pagibig_number' => $_POST['pagibig_number'] ?? null, 'bank_account_number' => $_POST['bank_account_number'] ?? null,
                'date_hired' => AppHelpers::cleanDate($_POST['date_hired'] ?? null), 'date_deployed' => AppHelpers::cleanDate($_POST['date_deployed'] ?? null),
                'date_regularized' => AppHelpers::cleanDate($_POST['date_regularized'] ?? null), 'contract_start_date' => AppHelpers::cleanDate($_POST['contract_start_date'] ?? null),
                'contract_end_date' => AppHelpers::cleanDate($_POST['contract_end_date'] ?? null), 'approval_role' => ($_POST['approval_role'] ?? 'Employee'),
                'sil_credits' => AppHelpers::cleanNum($_POST['sil_credits'] ?? 0),
            ];
            
            $employeeModel->updateEmployee($emp_id, $employeeData, $signaturePath, $profilePicPath, $current_user_id);
            $message = "Employee updated successfully!"; $messageType = "success";
        } catch(PDOException $e) { 
            $message = "Error: " . $e->getMessage(); $messageType = "error"; 
        }
    }

    // --- UPLOAD DOCUMENT ---
    elseif ($_POST['action'] == 'upload_document') {
        $emp_id = $_POST['emp_id'];
        $doc_name = $_POST['doc_name'];
        $ac_no = $_POST['ac_no']; 
        
        if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            $filePath = FileUploadService::handleFileUpload($_FILES['doc_file'], $ac_no . '_' . preg_replace('/[^a-zA-Z0-9]/', '', $doc_name), 'documents');
            if ($filePath) {
                try {
                    $employeeModel->uploadDocument($emp_id, $doc_name, $filePath);
                    $message = "Document uploaded successfully!"; $messageType = "success";
                } catch(PDOException $e) { 
                    $message = "DB Error: " . $e->getMessage(); $messageType = "error"; 
                }
            } else { 
                $message = "File upload failed."; $messageType = "error"; 
            }
        } else { 
            $message = "No file selected."; $messageType = "error"; 
        }
    }

    // --- DELETE DOCUMENT ---
    elseif ($_POST['action'] == 'delete_document') {
        try {
            $employeeModel->deleteDocument($_POST['doc_id']);
            $message = "Document deleted."; $messageType = "success";
        } catch(PDOException $e) { 
            $message = "Error: " . $e->getMessage(); $messageType = "error"; 
        }
    }

    // --- SOFT DELETE ---
    elseif ($_POST['action'] == 'soft_delete') {
        try {
            $employeeModel->softDeleteEmployee($_POST['emp_id']);
            $message = "Employee moved to Recycle Bin!"; $messageType = "success";
        } catch(PDOException $e) { 
            $message = "Error: " . $e->getMessage(); $messageType = "error"; 
        }
    }
    // --- RESTORE ---
    elseif ($_POST['action'] == 'restore') {
        try {
            $employeeModel->restoreEmployee($_POST['emp_id']);
            $message = "Employee restored successfully!"; $messageType = "success";
        } catch(PDOException $e) { 
            $message = "Error: " . $e->getMessage(); $messageType = "error"; 
        }
    }
    // --- HARD DELETE ---
    elseif ($_POST['action'] == 'hard_delete') {
        try {
            $employeeModel->hardDeleteEmployee($_POST['emp_id']);
            $message = "Employee permanently deleted!"; $messageType = "success";
        } catch(PDOException $e) { 
            $message = "Error: " . $e->getMessage(); $messageType = "error"; 
        }
    }
}

// Fetch Departments & Supervisors (Using model)
$depts = $employeeModel->getDepartments();
$supervisors = $employeeModel->getSupervisors();

// --- SEARCH & PAGINATION ---
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterDept = isset($_GET['dept']) ? $_GET['dept'] : '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; $offset = ($page - 1) * $limit;

$whereClauses = ["emp_id IN (SELECT emp_id FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE employee_status = 'Active' OR employee_status IS NULL)"];
$params = [];
if ($searchTerm) {
    $whereClauses[] = "(full_name LIKE :search1 OR ac_no LIKE :search2 OR job_title LIKE :search3)";
    $params[':search1'] = "%$searchTerm%"; $params[':search2'] = "%$searchTerm%"; $params[':search3'] = "%$searchTerm%";
}
if ($filterDept) { $whereClauses[] = "dept_name = :dept"; $params[':dept'] = $filterDept; }
$whereSQL = " WHERE " . implode(" AND ", $whereClauses);

// Fetch total records for pagination
$sqlCount = "SELECT COUNT(*) as total FROM [EmployeeManagementSystem].[dbo].[vw_EmployeeList]" . $whereSQL;
$stmtCount = $pdo->prepare($sqlCount);
foreach ($params as $key => $val) { $stmtCount->bindValue($key, $val); }
$stmtCount->execute();
$totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $limit);

// Fetch active employees
$sqlActive = "SELECT * FROM [EmployeeManagementSystem].[dbo].[vw_EmployeeList]" . $whereSQL . " ORDER BY full_name ASC OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
$stmtActive = $pdo->prepare($sqlActive);
foreach ($params as $key => $val) { $stmtActive->bindValue($key, $val); }
$stmtActive->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtActive->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtActive->execute();
$activeEmployees = $stmtActive->fetchAll(PDO::FETCH_ASSOC);

// Fetch inactive employees (for recycle bin)
$sqlInactive = "SELECT * FROM [EmployeeManagementSystem].[dbo].[vw_EmployeeList] WHERE emp_id IN (SELECT emp_id FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE employee_status = 'Inactive') ORDER BY full_name";
$stmtInactive = $pdo->prepare($sqlInactive);
$stmtInactive->execute();
$recycleBinEmployees = $stmtInactive->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include 'partials/_header.php'; ?>

    <main class="flex-1 max-w-7xl mx-auto px-6 py-8 w-full">
        
        <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Employee Management</h1>
                <p class="text-gray-500 text-sm">Manage records, onboarding, and assignments.</p>
            </div>
            
            <div class="bg-gray-100/50 p-1 rounded-lg flex gap-1">
                <button onclick="switchTab('directory')" id="btn-directory" class="subtab-btn active"><i class="fa-solid fa-address-book mr-2"></i> Directory</button>
                <button onclick="switchTab('onboarding')" id="btn-onboarding" class="subtab-btn"><i class="fa-solid fa-user-plus mr-2"></i> Onboard New</button>
                <button onclick="switchTab('bulk_upload')" id="btn-bulk_upload" class="subtab-btn"><i class="fa-solid fa-file-csv mr-2"></i> Bulk Upload</button>
                <button onclick="switchTab('recycle')" id="btn-recycle" class="subtab-btn hover:text-red-600"><i class="fa-solid fa-trash-can mr-2"></i> Recycle Bin</button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 px-4 py-3 rounded-lg shadow-sm border-l-4 <?php echo $messageType == 'success' ? 'bg-white border-primary text-green-700' : 'bg-white border-red-500 text-red-700'; ?> flex items-center gap-3 animate-fade-in relative">
                <i class="fa-solid <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <p class="font-medium text-sm"><?php echo htmlspecialchars($message); ?></p>
                <button onclick="this.parentElement.remove()" class="absolute right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-times"></i></button>
            </div>
        <?php endif; ?>

        <?php 
        // Note: Ensure these files do NOT contain the menu buttons above, or they will appear twice.
        include 'views/employee_list.php';
        include 'views/employee_onboarding.php';
        include 'views/employee_bulk_upload.php';
        include 'views/employee_recycle_bin.php';
        ?>

    </main>

    <?php include 'views/employee_modals.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab') || 'directory'; // Default to 'directory'
            switchTab(activeTab);
        });

        function exportData() {
            const dept = document.getElementById('deptFilter').value;
            window.location.href = `employee.php?action=export_employees&dept=${encodeURIComponent(dept)}`;
        }

        function switchTab(tabName) {
            // Hide all main tab contents
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            
            // Show selected tab
            const targetTab = document.getElementById('tab-' + tabName);
            if (targetTab) {
                targetTab.classList.remove('hidden');
            }
            
            // Update button states
            document.querySelectorAll('.subtab-btn').forEach(el => el.classList.remove('active'));
            const btn = document.getElementById('btn-' + tabName);
            if(btn) btn.classList.add('active');
        }

        function switchViewTab(tabName) {
            document.querySelectorAll('.view-tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById('view-tab-' + tabName).classList.remove('hidden');
            
            // Reset Styles
            const tabs = ['profile', 'salary', 'loans', 'documents'];
            tabs.forEach(t => {
                const btn = document.getElementById('btn-view-' + t);
                if(btn) btn.className = "text-sm font-medium text-gray-500 hover:text-gray-900 pb-2 px-1 border-b-2 border-transparent hover:border-gray-300 whitespace-nowrap transition-all";
            });
            // Set Active
            document.getElementById('btn-view-' + tabName).className = "text-sm font-bold text-gray-900 border-b-2 border-primary pb-2 px-1 whitespace-nowrap transition-all";
        }

        function closeModal() { document.getElementById('editModal').classList.add('hidden'); document.getElementById('modalBackdrop').classList.add('hidden'); }
        function closeViewModal() { document.getElementById('viewModal').classList.add('hidden'); document.getElementById('modalBackdrop').classList.add('hidden'); }

        function formatContact(input) {
            let val = input.value.replace(/\D/g, ''); 
            if(val.length === 0) { input.value = ''; return; }
            if (!input.value.startsWith('+63')) { if(val.startsWith('0')) val = val.substring(1); if(val.startsWith('63')) val = val.substring(2); } 
            else { if(val.startsWith('63')) val = val.substring(2); }
            let formatted = '+63';
            if (val.length > 0) formatted += ' ' + val.substring(0, 3);
            if (val.length > 3) formatted += ' ' + val.substring(3, 6);
            if (val.length > 6) formatted += ' ' + val.substring(6, 10);
            input.value = formatted;
        }

        function getEmployeeData(empId, callback) {
            fetch('employee.php?action=get_employee_json&emp_id=' + empId)
                .then(response => response.json())
                .then(data => { if (data.success) callback(data); else alert('Error: ' + data.message); })
                .catch(error => alert('Error fetching data.'));
        }

        function editEmployee(empId) {
            getEmployeeData(empId, (data) => {
                const emp = data.employee;
                const setVal = (id, val) => { const el = document.getElementById(id); if(el) el.value = (val === null || val === undefined) ? '' : val; };
                
                // Populate Identity
                setVal('edit_emp_id', emp.emp_id); setVal('edit_ac_no', emp.ac_no); setVal('edit_first_name', emp.first_name); setVal('edit_last_name', emp.last_name); setVal('edit_middle_name', emp.middle_name);
                setVal('edit_date_of_birth', emp.date_of_birth); setVal('edit_gender', emp.gender); setVal('edit_civil_status', emp.civil_status);
                setVal('edit_nationality', emp.nationality); setVal('edit_address', emp.address); setVal('edit_contact_number', emp.contact_number);
                setVal('edit_email_address', emp.email_address);
                setVal('edit_emergency_contact_name', emp.emergency_contact_name); setVal('edit_emergency_contact_number', emp.emergency_contact_number);
                setVal('edit_emergency_contact_relationship', emp.emergency_contact_relationship);
                setVal('edit_dept_id', emp.dept_id); 
                setVal('edit_job_title', emp.job_title); 
                setVal('edit_job_level', emp.job_level); 
                setVal('edit_approval_role', emp.approval_role || 'Employee');
                setVal('edit_supervisor_id', emp.supervisor_id); setVal('edit_employment_status', emp.employment_status);
                setVal('edit_employment_type', emp.employment_type); setVal('edit_location_assignment', emp.location_assignment); setVal('edit_work_schedule', emp.work_schedule);
                setVal('edit_date_hired', emp.date_hired); setVal('edit_date_deployed', emp.date_deployed); setVal('edit_date_regularized', emp.date_regularized);
                setVal('edit_contract_start_date', emp.contract_start_date); setVal('edit_contract_end_date', emp.contract_end_date);
                setVal('edit_salary_rate', emp.salary_rate); setVal('edit_daily', emp.daily_rate); setVal('edit_hourly', emp.hourly.rate);
                setVal('edit_payroll_group', emp.payroll_group); setVal('edit_tin_number', emp.tin_number); setVal('edit_sss_number', emp.sss_number); setVal('edit_philhealth_number', emp.philhealth_number); setVal('edit_pagibig_number', emp.pagibig_number);
                
                setVal('edit_sil_credits', emp.sil_credits);

                document.getElementById('editModal').classList.remove('hidden');
                document.getElementById('modalBackdrop').classList.remove('hidden');
            });
        }

        function viewEmployee(empId) {
            getEmployeeData(empId, (data) => {
                const emp = data.employee;
                const history = data.salary_history;
                const loans = data.loans;
                const payments = data.loan_payments; 
                const docs = data.documents;
                const val = (v) => v ? v : '<span class="text-gray-400 italic">N/A</span>';
                
                // Logic for avatar display
                let avatarHtml = '';
                if (emp.profile_picture && emp.profile_picture.trim() !== '') {
                    avatarHtml = `<img src="${emp.profile_picture}?t=${new Date().getTime()}" class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-sm">`;
                } else {
                     avatarHtml = `
                        <div class="w-20 h-20 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center font-bold text-3xl border-4 border-white shadow-sm">
                            ${(emp.first_name?.[0] || '')}${(emp.last_name?.[0] || '')}
                        </div>`;
                }

                // 1. Set Hidden inputs for Doc Upload
                document.getElementById('doc_emp_id').value = emp.emp_id;
                document.getElementById('doc_ac_no').value = emp.ac_no;

                // 2. Profile Tab
                let html = `
                    <div class="flex items-center gap-6 mb-8">
                        ${avatarHtml}
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">${val(emp.full_name)}</h2>
                            <p class="text-primary-600 font-semibold">${val(emp.job_title)} <span class="text-gray-400 font-normal">| ${val(emp.job_level)}</span></p>
                            <div class="flex gap-2 mt-2">
                                <span class="text-xs bg-gray-200 px-2 py-1 rounded text-gray-600 font-mono">${val(emp.ac_no)}</span>
                                <span class="text-xs bg-primary-100 text-primary-700 px-2 py-1 rounded font-bold uppercase">${emp.approval_role || 'Employee'}</span>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-y-6 gap-x-12">
                        <div><h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Department</h4><p class="font-medium">${val(emp.dept_name)}</p></div>
                        <div><h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Supervisor</h4><p class="font-medium">${val(emp.supervisor_name)}</p></div>
                        <div><h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Email</h4><p class="font-medium">${val(emp.email_address)}</p></div>
                        <div><h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Mobile</h4><p class="font-medium font-mono">${val(emp.contact_number)}</p></div>
                        <div><h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Date Hired</h4><p class="font-medium">${val(emp.date_hired)}</p></div>
                        <div><h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Current Salary</h4><p class="font-bold text-green-700">P ${val(emp.salary_rate)}</p></div>
                        <div class="col-span-2 bg-yellow-50 p-2 rounded border border-yellow-100">
                            <h4 class="text-xs font-bold text-yellow-700 uppercase tracking-wider mb-1">Service Incentive Leave</h4>
                            <p class="font-bold text-yellow-900">${val(emp.sil_credits)} Credits Available</p>
                        </div>
                    </div>
                `;
                document.getElementById('viewEmployeeContent').innerHTML = html;

                // 3. Salary History Tab
                const salaryBody = document.getElementById('salaryHistoryBody');
                salaryBody.innerHTML = '';
                if (history && history.length > 0) {
                    history.forEach(rec => {
                        const diff = parseFloat(rec.new_salary) - parseFloat(rec.old_salary);
                        const diffClass = diff >= 0 ? 'text-green-600' : 'text-red-600';
                        const dateObj = new Date(rec.change_date.date || rec.change_date);
                        salaryBody.innerHTML += `
                            <tr class="hover:bg-gray-50 border-b border-gray-100">
                                <td class="px-4 py-3 text-gray-600">${dateObj.toLocaleDateString()}</td>
                                <td class="px-4 py-3 font-mono text-gray-500">P ${parseFloat(rec.old_salary).toFixed(2)}</td>
                                <td class="px-4 py-3 font-mono font-bold text-gray-800">P ${parseFloat(rec.new_salary).toFixed(2)}</td>
                                <td class="px-4 py-3 font-mono font-bold ${diffClass}">${diff >= 0 ? '+' : ''}${diff.toFixed(2)}</td>
                            </tr>`;
                    });
                } else { salaryBody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-400 italic">No salary history.</td></tr>'; }

                // 4. Loans Tab
                const loansBody = document.getElementById('loansHistoryBody');
                loansBody.innerHTML = '';
                if (loans && loans.length > 0) {
                    loans.forEach(l => {
                        const total = parseFloat(l.total_payable);
                        const paid = parseFloat(l.paid_amount);
                        const percent = total > 0 ? Math.round((paid / total) * 100) : 0;
                        const statusClass = l.status === 'Active' ? 'bg-green-100 text-green-700' : (l.status === 'Paid' ? 'bg-gray-100 text-gray-500' : 'bg-yellow-100 text-yellow-700');
                        
                        loansBody.innerHTML += `
                            <tr class="hover:bg-gray-50 border-b border-gray-100">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-800">${l.loan_category}</div>
                                    <div class="text-xs text-gray-400">${l.description || '-'}</div>
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-gray-600">P ${total.toFixed(2)}</td>
                                <td class="px-4 py-3 text-right font-mono text-green-600">P ${paid.toFixed(2)}</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-gray-800">P ${parseFloat(l.remaining_balance).toFixed(2)}</td>
                                <td class="px-4 py-3 text-center"><span class="px-2 py-1 rounded text-[10px] font-bold uppercase ${statusClass}">${l.status}</span></td>
                                <td class="px-4 py-3 align-middle">
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        <div class="bg-primary-500 h-1.5 rounded-full" style="width: ${percent}%"></div>
                                    </div>
                                    <span class="text-[10px] text-gray-400">${percent}%</span>
                                </td>
                            </tr>`;
                    });
                } else { loansBody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 italic">No loan records found.</td></tr>'; }

                // 4b. Payment History
                const paymentsBody = document.getElementById('paymentHistoryBody');
                paymentsBody.innerHTML = '';
                if (payments && payments.length > 0) {
                    payments.forEach(p => {
                        const dateObj = new Date(p.payment_date.date || p.payment_date);
                        paymentsBody.innerHTML += `
                            <tr class="hover:bg-gray-50 border-b border-gray-100">
                                <td class="px-4 py-3 text-gray-600">${dateObj.toLocaleDateString()}</td>
                                <td class="px-4 py-3 font-medium text-gray-700">${p.loan_category}</td>
                                <td class="px-4 py-3 text-right font-mono text-green-600 font-bold">P ${parseFloat(p.amount_paid).toFixed(2)}</td>
                            </tr>`;
                    });
                } else { 
                    paymentsBody.innerHTML = '<tr><td colspan="3" class="px-4 py-8 text-center text-gray-400 italic">No payment history available.</td></tr>'; 
                }

                // 5. Documents Tab
                const docsList = document.getElementById('documentsList');
                docsList.innerHTML = '';
                if(docs && docs.length > 0) {
                    docs.forEach(d => {
                        docsList.innerHTML += `
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg bg-gray-50">
                                <div class="flex items-center gap-3">
                                    <div class="text-red-500 text-xl"><i class="fa-solid fa-file-pdf"></i></div>
                                    <div><div class="text-sm font-bold text-gray-700">${d.doc_name}</div><div class="text-xs text-gray-400">${new Date(d.uploaded_at.date || d.uploaded_at).toLocaleDateString()}</div></div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="${d.file_path}" target="_blank" class="text-blue-600 hover:text-blue-800 text-xs font-bold"><i class="fa-solid fa-eye"></i></a>
                                    <form method="POST" onsubmit="return confirm('Delete this document?')">
                                        <input type="hidden" name="action" value="delete_document">
                                        <input type="hidden" name="doc_id" value="${d.doc_id}">
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        `;
                    });
                } else { docsList.innerHTML = '<p class="col-span-2 text-center text-gray-400 italic py-4">No documents uploaded.</p>'; }

                switchViewTab('profile');
                document.getElementById('viewModal').classList.remove('hidden');
                document.getElementById('modalBackdrop').classList.remove('hidden');
            });
        }

        function calculateRates(element, prefix) {
            let monthlyInput = document.getElementById(prefix === 'add' ? 'add_salary' : 'edit_salary_rate');
            let dailyInput = document.getElementById(prefix === 'add' ? 'add_daily' : 'edit_daily');
            let hourlyInput = document.getElementById(prefix === 'add' ? 'add_hourly' : 'edit_hourly');
            let val = parseFloat(element.value);
            if (isNaN(val) || val === 0) return; 
            const workdaysInMonth = 313 / 12;
            if (element === monthlyInput) {
                let daily = val / workdaysInMonth; let hourly = daily / 8;
                dailyInput.value = daily.toFixed(2); hourlyInput.value = hourly.toFixed(2);
            } 
        }

        function softDeleteEmployee(id, name) { if(confirm('Move ' + name + ' to Recycle Bin?')) createPost(id, 'soft_delete'); }
        function restoreEmployee(id) { if(confirm('Restore employee?')) createPost(id, 'restore'); }
        function hardDeleteEmployee(id, name) { if(confirm('PERMANENTLY DELETE ' + name + '? This cannot be undone.')) createPost(id, 'hard_delete'); }
        
        function createPost(id, action) {
            const f = document.createElement('form'); f.method = 'POST'; f.action = '';
            f.innerHTML = `<input type="hidden" name="action" value="${action}"><input type="hidden" name="emp_id" value="${id}">`;
            document.body.appendChild(f); f.submit();
        }
    </script>
</body>
</html>