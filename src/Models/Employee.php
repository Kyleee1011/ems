<?php
namespace App\Models;

use PDO;
use App\Utils\AppHelpers;
use App\Models\AttendanceModel;

class Employee
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll()
    {
        $sql = "SELECT e.emp_id, e.ac_no, e.full_name, e.first_name, e.last_name, e.middle_name, e.job_title, e.employment_status, e.employment_type, d.dept_name, s.full_name as supervisor_name, e.contact_number, e.email_address, e.date_hired, e.date_deployed, e.date_regularized, e.employee_status, e.location_assignment, e.salary_rate, e.salary_type, e.created_at, e.updated_at FROM employees e LEFT JOIN departments d ON e.dept_id = d.dept_id LEFT JOIN employees s ON e.supervisor_id = s.emp_id WHERE e.IsActive = 1 ORDER BY e.full_name ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($id)
    {
        $sql = "SELECT * FROM employees WHERE emp_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getDepartments()
    {
        $stmt = $this->pdo->query("SELECT * FROM departments ORDER BY dept_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSupervisors()
    {
        $sql = "SELECT emp_id, first_name, last_name FROM employees WHERE approval_role IN ('DeptHead','Manager','CEO') AND IsActive = 1 ORDER BY first_name ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPaginatedEmployees($searchTerm = '', $filterDept = '', $page = 1, $limit = 10, $active = true)
    {
        $offset = ($page - 1) * $limit;
        $whereClauses = [];
        $params = [];

        if ($active) {
            $whereClauses[] = "e.IsActive = 1";
        } else {
            $whereClauses[] = "e.IsActive = 0";
        }

        if ($searchTerm) {
            $whereClauses[] = "(e.full_name LIKE :s1 OR e.ac_no LIKE :s2 OR e.job_title LIKE :s3)";
            $params[':s1'] = "%$searchTerm%";
            $params[':s2'] = "%$searchTerm%";
            $params[':s3'] = "%$searchTerm%";
        }

        if ($filterDept) {
            $whereClauses[] = "e.dept_id = :dept";
            $params[':dept'] = $filterDept;
        }

        $whereSQL = " WHERE " . implode(" AND ", $whereClauses);

        $baseSelect = "FROM employees e LEFT JOIN departments d ON e.dept_id = d.dept_id LEFT JOIN employees s ON e.supervisor_id = s.emp_id";

        $sqlCount = "SELECT COUNT(*) as total " . $baseSelect . $whereSQL;
        $stmtCount = $this->pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

        $sql = "SELECT e.emp_id, e.ac_no, e.full_name, e.first_name, e.last_name, e.middle_name, e.job_title, e.employment_status, e.employment_type, d.dept_name, s.full_name as supervisor_name, e.contact_number, e.email_address, e.date_hired, e.date_deployed, e.date_regularized, e.employee_status, e.location_assignment, e.salary_rate, e.salary_type, e.created_at, e.updated_at " . $baseSelect . $whereSQL . " ORDER BY e.full_name ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'employees' => $employees,
            'totalRecords' => $totalRecords,
            'totalPages' => ceil($totalRecords / $limit)
        ];
    }

    // Add other methods for creating, updating, deleting employees, handling bulk upload, file uploads, etc.
    // This will involve moving a lot of logic from the original employee.php
    public function bulkUploadEmployees($csvFilePath)
    {
        $this->pdo->beginTransaction();
        try {
            $handle = fopen($csvFilePath, "r");
            fgetcsv($handle); // Skip header row
            $rowCount = 0;
            
            // Pre-fetch roles to avoid query inside loop
            $rolesQuery = $this->pdo->query("SELECT role_name, role_id FROM roles");
            $roles = $rolesQuery ? $rolesQuery->fetchAll(PDO::FETCH_KEY_PAIR) : [];
            $defaultRoleId = $roles['Employee'] ?? reset($roles);
            
            $sql = "INSERT INTO employees (ac_no, last_name, first_name, middle_name, date_of_birth, gender, address, contact_number, email_address, civil_status, nationality, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, dept_id, job_title, job_level, employment_status, location_assignment, supervisor_id, employment_type, work_schedule, salary_rate, daily_rate, hourly_rate, payroll_group, tin_number, sss_number, philhealth_number, pagibig_number, bank_account_number, date_hired, date_deployed, contract_start_date, contract_end_date, employee_status, approval_role, sil_credits, IsActive, password_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = $this->pdo->prepare($sql);
            
            $stmtUser = $this->pdo->prepare("INSERT INTO users (username, password_hash, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");

            while (($d = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (!$d[0] || !$d[1])
                    continue;
                $basic = AppHelpers::cleanNum($d[22]);
                $r = AppHelpers::calculateRatesFromMonthly($basic);
                $sil = AppHelpers::cleanNum($d[34]) ?? 0;
                $approvalRole = $d[33] ?: 'Employee';
                $acNo = $d[0];
                
                // Generate hash from AC NO
                $passHash = password_hash($acNo, PASSWORD_DEFAULT);

                $params = [$acNo, $d[1], $d[2], $d[3], AppHelpers::cleanDate($d[4]), $d[5], $d[6], $d[7], $d[8], $d[9], $d[10], $d[11], $d[12], $d[13], AppHelpers::cleanNum($d[14]), $d[15], $d[16], $d[17], $d[18], AppHelpers::cleanNum($d[19]), $d[20], $d[21], $basic, $r['daily'], $r['hourly'], $d[23], $d[24], $d[25], $d[26], $d[27], $d[28], AppHelpers::cleanDate($d[29]), AppHelpers::cleanDate($d[30]), AppHelpers::cleanDate($d[31]), AppHelpers::cleanDate($d[32]), 'Active', $approvalRole, $sil, 1, $passHash];
                $stmt->execute($params);
                
                // Create User (Optional sync)
                $username = $acNo; 
                $fullname = $d[2] . ' ' . $d[1];
                $email = $d[8];
                $roleId = $roles[$approvalRole] ?? $defaultRoleId;
                
                $stmtUser->execute([$username, $passHash, $fullname, $email, $approvalRole]);

                $rowCount++;
            }
            fclose($handle);
            $this->pdo->commit();
            return $rowCount;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            fclose($handle);
            throw $e;
        }
    }

    public function exportEmployees($filterDept = '')
    {
        $sql = "SELECT e.ac_no, e.last_name, e.first_name, e.middle_name, d.dept_name, e.job_title, e.employment_status, e.date_hired, e.contact_number, e.email_address, e.sss_number, e.philhealth_number, e.pagibig_number, e.tin_number, e.salary_rate FROM employees e LEFT JOIN departments d ON e.dept_id = d.dept_id WHERE e.IsActive = 1";
        $params = [];
        if ($filterDept) {
            $sql .= " AND (d.dept_id = :dept_id OR d.dept_name = :dept_name)";
            $params[':dept_id'] = $filterDept;
            $params[':dept_name'] = $filterDept;
        }
        $sql .= " ORDER BY e.last_name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Example: Add Employee method
    public function addEmployee($data, $signaturePath = null, $profilePicPath = null, $current_user_id = null)
    {
        try {
            $this->pdo->beginTransaction();

            // Generate default password hash using AC No
            $defaultPasswordHash = password_hash($data['ac_no'], PASSWORD_DEFAULT);

            $sql = "INSERT INTO employees (
                ac_no, last_name, first_name, middle_name, date_of_birth, gender,
                address, contact_number, email_address, civil_status, nationality,
                emergency_contact_name, emergency_contact_number, emergency_contact_relationship,
                dept_id, job_title, job_level, employment_status, location_assignment, supervisor_id,
                employment_type, work_schedule, work_days_mode, salary_rate, daily_rate, hourly_rate, payroll_group,
                tin_number, sss_number, philhealth_number, pagibig_number, bank_account_number,
                date_hired, date_deployed, contract_start_date, contract_end_date, employee_status,
                approval_role, sil_credits, IsActive, signature_path, profile_picture, password_hash
            ) VALUES (
                :ac_no, :last_name, :first_name, :middle_name, :date_of_birth, :gender,
                :address, :contact_number, :email_address, :civil_status, :nationality,
                :emergency_contact_name, :emergency_contact_number, :emergency_contact_relationship,
                :dept_id, :job_title, :job_level, :employment_status, :location_assignment, :supervisor_id,
                :employment_type, :work_schedule, :work_days_mode, :salary_rate, :daily_rate, :hourly_rate, :payroll_group,
                :tin_number, :sss_number, :philhealth_number, :pagibig_number, :bank_account_number,
                :date_hired, :date_deployed, :contract_start_date, :contract_end_date, :employee_status,
                :approval_role, :sil_credits, 1, :signature_path, :profile_picture, :password_hash
            )";
            $stmt = $this->pdo->prepare($sql);
            

            
            
            $stmt->bindParam(':ac_no', $data['ac_no']);
            $stmt->bindParam(':last_name', $data['last_name']);
            $stmt->bindParam(':first_name', $data['first_name']);
            $stmt->bindParam(':middle_name', $data['middle_name']);
            $stmt->bindParam(':date_of_birth', $data['date_of_birth']);
            $stmt->bindParam(':gender', $data['gender']);
            $stmt->bindParam(':address', $data['address']);
            $stmt->bindParam(':contact_number', $data['contact_number']);
            $stmt->bindParam(':email_address', $data['email_address']);
            $stmt->bindParam(':civil_status', $data['civil_status']);
            $stmt->bindParam(':nationality', $data['nationality']);
            $stmt->bindParam(':emergency_contact_name', $data['emergency_contact_name']);
            $stmt->bindParam(':emergency_contact_number', $data['emergency_contact_number']);
            $stmt->bindParam(':emergency_contact_relationship', $data['emergency_contact_relationship']);
            $stmt->bindParam(':dept_id', $data['dept_id']);
            $stmt->bindParam(':job_title', $data['job_title']);
            $stmt->bindParam(':job_level', $data['job_level']);
            $stmt->bindParam(':employment_status', $data['employment_status']);
            $stmt->bindParam(':location_assignment', $data['location_assignment']);
            $stmt->bindParam(':supervisor_id', $data['supervisor_id']);
            $stmt->bindParam(':employment_type', $data['employment_type']);
            $stmt->bindParam(':work_schedule', $data['work_schedule']);
            $work_days_mode = $data['work_days_mode'] ?? '6';
            $stmt->bindParam(':work_days_mode', $work_days_mode);
            $stmt->bindParam(':salary_rate', $data['salary_rate']);
            $stmt->bindParam(':daily_rate', $data['daily_rate']);
            $stmt->bindParam(':hourly_rate', $data['hourly_rate']);
            $stmt->bindParam(':payroll_group', $data['payroll_group']);
            $stmt->bindParam(':tin_number', $data['tin_number']);
            $stmt->bindParam(':sss_number', $data['sss_number']);
            $stmt->bindParam(':philhealth_number', $data['philhealth_number']);
            $stmt->bindParam(':pagibig_number', $data['pagibig_number']);
            $stmt->bindParam(':bank_account_number', $data['bank_account_number']);
            $stmt->bindParam(':date_hired', $data['date_hired']);
            $stmt->bindParam(':date_deployed', $data['date_deployed']);
            $stmt->bindParam(':contract_start_date', $data['contract_start_date']);
            $stmt->bindParam(':contract_end_date', $data['contract_end_date']);
            $stmt->bindParam(':employee_status', $data['employee_status']);
            $stmt->bindParam(':approval_role', $data['approval_role']);
            $stmt->bindParam(':sil_credits', $data['sil_credits']);
            $stmt->bindParam(':signature_path', $signaturePath);
            $stmt->bindParam(':profile_picture', $profilePicPath);
            $stmt->bindParam(':password_hash', $defaultPasswordHash);
            
            $stmt->execute();
            
            $lastId = $this->pdo->lastInsertId();
            
            // Log Salary History
            if ($data['salary_rate'] > 0 && $current_user_id) {
                 $stmtHist = $this->pdo->prepare("INSERT INTO salary_history (emp_id, old_salary, new_salary, changed_by) VALUES (?, 0, ?, ?)");
                 $stmtHist->execute([$lastId, $data['salary_rate'], $current_user_id]);
            }

            // OPTIONAL: Still Create User Account if needed for other systems, mimicking the employee creds
            // But login.php uses employees table primarily
            $fullname = $data['first_name'] . ' ' . $data['last_name'];
            $username = $data['ac_no'];
            
             if ($data['approval_role']) {
                  $stmtUser = $this->pdo->prepare("INSERT INTO users (username, password_hash, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                  $stmtUser->execute([$username, $defaultPasswordHash, $fullname, $data['email_address'], $data['approval_role']]);
             }

            $this->pdo->commit();
            return $lastId;

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Example: Update Employee method
    public function updateEmployee($emp_id, $data, $signaturePath = null, $profilePicPath = null, $current_user_id = null)
    {
        // First, get current salary for history
        $stmtCheck = $this->pdo->prepare("SELECT salary_rate FROM employees WHERE emp_id = ?");
        $stmtCheck->execute([$emp_id]);
        $currentData = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        $oldSalary = $currentData ? (float)$currentData['salary_rate'] : 0.0;
        $newSalary = (float)$data['salary_rate'];

        if (abs($oldSalary - $newSalary) > 0.01 && $current_user_id) {
            $stmtHist = $this->pdo->prepare("INSERT INTO salary_history (emp_id, old_salary, new_salary, changed_by) VALUES (:eid, :old, :new, :uid)");
            $stmtHist->execute([':eid' => $emp_id, ':old' => $oldSalary, ':new' => $newSalary, ':uid' => $current_user_id]);
        }

        $signatureSQL = "";
        if($signaturePath) { $signatureSQL = ", signature_path = :signature_path"; }
        $profilePicSQL = "";
        if($profilePicPath) { $profilePicSQL = ", profile_picture = :profile_picture"; }

        // Logic for soft delete based on status
        $statusParam = $data['employee_status'] ?? 'Active';
        $isActive = ($statusParam === 'Active') ? 1 : 0;

        $sql = "UPDATE employees SET
            last_name = :last_name, first_name = :first_name, middle_name = :middle_name,
            date_of_birth = :date_of_birth, gender = :gender, address = :address,
            contact_number = :contact_number, email_address = :email_address, civil_status = :civil_status,
            nationality = :nationality, emergency_contact_name = :emergency_contact_name,
            emergency_contact_number = :emergency_contact_number, emergency_contact_relationship = :emergency_contact_relationship,
            dept_id = :dept_id, job_title = :job_title, job_level = :job_level, employment_status = :employment_status,
            location_assignment = :location_assignment, supervisor_id = :supervisor_id,
            employment_type = :employment_type, work_schedule = :work_schedule, work_days_mode = :work_days_mode,
            salary_rate = :salary_rate, daily_rate = :daily_rate, hourly_rate = :hourly_rate,
            payroll_group = :payroll_group,
            tin_number = :tin_number, sss_number = :sss_number, philhealth_number = :philhealth_number,
            pagibig_number = :pagibig_number, bank_account_number = :bank_account_number,
            date_hired = :date_hired, date_deployed = :date_deployed, date_regularized = :date_regularized,
            contract_start_date = :contract_start_date, contract_end_date = :contract_end_date,
            approval_role = :approval_role, sil_credits = :sil_credits, 
            employee_status = :employee_status, IsActive = :is_active,
            updated_at = NOW()
            $signatureSQL $profilePicSQL
        WHERE emp_id = :emp_id";
        
        $stmt = $this->pdo->prepare($sql);
        
        $stmt->bindParam(':employee_status', $statusParam);
        $stmt->bindParam(':is_active', $isActive, PDO::PARAM_INT);

        $stmt->bindParam(':emp_id', $emp_id);
        $stmt->bindParam(':last_name', $data['last_name']);
        $stmt->bindParam(':first_name', $data['first_name']);
        $stmt->bindParam(':middle_name', $data['middle_name']);
        $stmt->bindParam(':date_of_birth', $data['date_of_birth']);
        $stmt->bindParam(':gender', $data['gender']);
        $stmt->bindParam(':address', $data['address']);
        $stmt->bindParam(':contact_number', $data['contact_number']);
        $stmt->bindParam(':email_address', $data['email_address']);
        $stmt->bindParam(':civil_status', $data['civil_status']);
        $stmt->bindParam(':nationality', $data['nationality']);
        $stmt->bindParam(':emergency_contact_name', $data['emergency_contact_name']);
        $stmt->bindParam(':emergency_contact_number', $data['emergency_contact_number']);
        $stmt->bindParam(':emergency_contact_relationship', $data['emergency_contact_relationship']);
        $stmt->bindParam(':dept_id', $data['dept_id']);
        $stmt->bindParam(':job_title', $data['job_title']);
        $stmt->bindParam(':job_level', $data['job_level']);
        $stmt->bindParam(':employment_status', $data['employment_status']);
        $stmt->bindParam(':location_assignment', $data['location_assignment']);
        $stmt->bindParam(':supervisor_id', $data['supervisor_id']);
        $stmt->bindParam(':employment_type', $data['employment_type']);
        $stmt->bindParam(':work_schedule', $data['work_schedule']);
        $work_days_mode = $data['work_days_mode'] ?? '6';
        $stmt->bindParam(':work_days_mode', $work_days_mode);
        $stmt->bindParam(':salary_rate', $data['salary_rate']);
        $stmt->bindParam(':daily_rate', $data['daily_rate']);
        $stmt->bindParam(':hourly_rate', $data['hourly_rate']);
        $stmt->bindParam(':payroll_group', $data['payroll_group']);
        $stmt->bindParam(':tin_number', $data['tin_number']);
        $stmt->bindParam(':sss_number', $data['sss_number']);
        $stmt->bindParam(':philhealth_number', $data['philhealth_number']);
        $stmt->bindParam(':pagibig_number', $data['pagibig_number']);
        $stmt->bindParam(':bank_account_number', $data['bank_account_number']);
        $stmt->bindParam(':date_hired', $data['date_hired']);
        $stmt->bindParam(':date_deployed', $data['date_deployed']);
        $stmt->bindParam(':date_regularized', $data['date_regularized']);
        $stmt->bindParam(':contract_start_date', $data['contract_start_date']);
        $stmt->bindParam(':contract_end_date', $data['contract_end_date']);
        $stmt->bindParam(':approval_role', $data['approval_role']);
        $stmt->bindParam(':sil_credits', $data['sil_credits']);
        if($signaturePath) { $stmt->bindParam(':signature_path', $signaturePath); }
        if($profilePicPath) { $stmt->bindParam(':profile_picture', $profilePicPath); }
        
        return $stmt->execute();
    }

    public function getEmployeeFullDetails($emp_id)
    {
        // 1. Get Employee Data
        $stmt = $this->pdo->prepare("SELECT e.*, s.full_name as supervisor_name FROM employees e LEFT JOIN employees s ON e.supervisor_id = s.emp_id WHERE e.emp_id = :id");
        $stmt->execute([':id' => $emp_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {
            return null;
        }

        // 2. Get Salary History
        $stmtHist = $this->pdo->prepare("SELECT * FROM salary_history WHERE emp_id = :id ORDER BY change_date DESC");
        $stmtHist->execute([':id' => $emp_id]);
        $history = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

        // 3. Get Loans (With calculation for Paid Amount)
        $stmtLoans = $this->pdo->prepare("SELECT *, (total_payable - remaining_balance) as paid_amount FROM employee_loans WHERE emp_id = :id ORDER BY status, start_date DESC");
        $stmtLoans->execute([':id' => $emp_id]);
        $loans = $stmtLoans->fetchAll(PDO::FETCH_ASSOC);
        
        // 4. Get Loan Payments (Transaction History)
        $payments = [];
        try {
            $stmtPay = $this->pdo->prepare("
                SELECT lp.payment_date, lp.amount_paid, el.loan_category
                FROM loan_payments lp
                JOIN employee_loans el ON lp.loan_id = el.loan_id
                WHERE el.emp_id = :id
                ORDER BY lp.payment_date DESC
            ");
            $stmtPay->execute([':id' => $emp_id]);
            $payments = $stmtPay->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Log error, but continue without payments
        }

        // 5. Get Documents
        $stmtDocs = $this->pdo->prepare("SELECT * FROM employee_documents WHERE emp_id = :id ORDER BY uploaded_at DESC");
        $stmtDocs->execute([':id' => $emp_id]);
        $docs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

        // 6. Get Attendance Logs (Timecard)
        $attendanceLogs = [];
        try {
            $attModel = new AttendanceModel($this->pdo);
            if (!empty($employee['ac_no'])) {
                $attendanceLogs = $attModel->getEmployeeLogs($employee['ac_no']);
            }
        } catch (\Exception $e) {
            // Failure to load attendance should not break the whole profile view
        }
        
        return [
            'employee' => $employee,
            'salary_history' => $history,
            'loans' => $loans,
            'loan_payments' => $payments,
            'documents' => $docs,
            'attendance_logs' => $attendanceLogs
        ];
    }

    public function uploadDocument($emp_id, $doc_name, $filePath)
    {
        $stmt = $this->pdo->prepare("INSERT INTO employee_documents (emp_id, doc_name, file_path) VALUES (?, ?, ?)");
        return $stmt->execute([$emp_id, $doc_name, $filePath]);
    }

    public function deleteDocument($doc_id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM employee_documents WHERE doc_id = ?");
        return $stmt->execute([$doc_id]);
    }

    public function softDeleteEmployee($emp_id)
    {
        $stmt = $this->pdo->prepare("UPDATE employees SET IsActive = 0, employee_status = 'Inactive', updated_at = NOW() WHERE emp_id = ?");
        return $stmt->execute([$emp_id]);
    }

    public function restoreEmployee($emp_id)
    {
        $stmt = $this->pdo->prepare("UPDATE employees SET IsActive = 1, employee_status = 'Active', updated_at = NOW() WHERE emp_id = ?");
        return $stmt->execute([$emp_id]);
    }

    public function hardDeleteEmployee($emp_id)
    {
        // First delete related records to avoid foreign key constraints
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM salary_history WHERE emp_id = ?")->execute([$emp_id]);
            $this->pdo->prepare("DELETE FROM employee_loans WHERE emp_id = ?")->execute([$emp_id]);
            $this->pdo->prepare("DELETE FROM employee_documents WHERE emp_id = ?")->execute([$emp_id]);
            $this->pdo->prepare("DELETE FROM leave_applications WHERE emp_id = ?")->execute([$emp_id]);
            $this->pdo->prepare("DELETE FROM overtime_applications WHERE emp_id = ?")->execute([$emp_id]);
            $this->pdo->prepare("DELETE FROM schedule_change_requests WHERE emp_id = ?")->execute([$emp_id]);
            // $this->pdo->prepare("DELETE FROM employee_schedules WHERE employee_id = ?")->execute([$emp_id]); // This table doesn't seem to exist in the new schema, commenting out for now
            // Assuming other related tables might exist, add more DELETE statements here

            $stmt = $this->pdo->prepare("DELETE FROM employees WHERE emp_id = ?");
            $result = $stmt->execute([$emp_id]);
            $this->pdo->commit();
            return $result;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e; // Re-throw the exception to be handled by the caller
        }
    }

    public function getPendingLeaveCount()
    {
        return $this->pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status IN ('Submitted', 'Pending')")->fetchColumn();
    }

    public function getPendingOvertimeCount()
    {
        return $this->pdo->query("SELECT COUNT(*) FROM overtime_applications WHERE status IN ('Submitted', 'Pending')")->fetchColumn();
    }

    public function getPendingScheduleCount($user_role)
    {
        $targetStatus = ($user_role === 'HR') ? 'Pending HR' : (($user_role === 'CEO') ? 'Pending CEO' : '');
        if ($targetStatus) {
            $stmtSched = $this->pdo->prepare("SELECT COUNT(*) FROM schedule_batches WHERE status = ?");
            $stmtSched->execute([$targetStatus]);
            return $stmtSched->fetchColumn();
        }
        return 0;
    }

}
