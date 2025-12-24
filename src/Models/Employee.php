<?php
namespace App\Models;

use PDO;

class Employee
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll()
    {
        $stmt = $this->pdo->query("SELECT * FROM [EmployeeManagementSystem].[dbo].[vw_EmployeeList] WHERE employee_status = 'Active' ORDER BY full_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($id)
    {
        $sql = "SELECT * FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE emp_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getDepartments()
    {
        $stmt = $this->pdo->query("SELECT * FROM [EmployeeManagementSystem].[dbo].[Departments] ORDER BY dept_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSupervisors()
    {
        $sql = "SELECT emp_id, first_name, last_name FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE approval_role IN ('DeptHead','Manager','CEO') AND IsActive = 1 ORDER BY first_name ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add other methods for creating, updating, deleting employees, handling bulk upload, file uploads, etc.
    // This will involve moving a lot of logic from the original employee.php
    
    // Example: Add Employee method
    public function addEmployee($data, $signaturePath = null, $profilePicPath = null, $current_user_id = null)
    {
        $sql = "INSERT INTO [EmployeeManagementSystem].[dbo].[Employees] (
            ac_no, last_name, first_name, middle_name, date_of_birth, gender,
            address, contact_number, email_address, civil_status, nationality,
            emergency_contact_name, emergency_contact_number, emergency_contact_relationship,
            dept_id, job_title, job_level, employment_status, location_assignment, supervisor_id,
            employment_type, work_schedule, salary_rate, daily_rate, hourly_rate, payroll_group,
            tin_number, sss_number, philhealth_number, pagibig_number, bank_account_number,
            date_hired, date_deployed, contract_start_date, contract_end_date, employee_status,
            approval_role, sil_credits, IsActive, signature_path, profile_picture
        ) VALUES (
            :ac_no, :last_name, :first_name, :middle_name, :date_of_birth, :gender,
            :address, :contact_number, :email_address, :civil_status, :nationality,
            :emergency_contact_name, :emergency_contact_number, :emergency_contact_relationship,
            :dept_id, :job_title, :job_level, :employment_status, :location_assignment, :supervisor_id,
            :employment_type, :work_schedule, :salary_rate, :daily_rate, :hourly_rate, :payroll_group,
            :tin_number, :sss_number, :philhealth_number, :pagibig_number, :bank_account_number,
            :date_hired, :date_deployed, :contract_start_date, :contract_end_date, :employee_status,
            :approval_role, :sil_credits, 1, :signature_path, :profile_picture
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
        
        $stmt->execute();
        
        $lastId = $this->pdo->lastInsertId();
        if ($data['salary_rate'] > 0 && $current_user_id) {
             $stmtHist = $this->pdo->prepare("INSERT INTO [EmployeeManagementSystem].[dbo].[SalaryHistory] (emp_id, old_salary, new_salary, changed_by) VALUES (?, 0, ?, ?)");
             $stmtHist->execute([$lastId, $data['salary_rate'], $current_user_id]);
        }
        return $lastId;
    }

    // Example: Update Employee method
    public function updateEmployee($emp_id, $data, $signaturePath = null, $profilePicPath = null, $current_user_id = null)
    {
        // First, get current salary for history
        $stmtCheck = $this->pdo->prepare("SELECT salary_rate FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE emp_id = ?");
        $stmtCheck->execute([$emp_id]);
        $currentData = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        $oldSalary = $currentData ? (float)$currentData['salary_rate'] : 0.0;
        $newSalary = (float)$data['salary_rate'];

        if (abs($oldSalary - $newSalary) > 0.01 && $current_user_id) {
            $stmtHist = $this->pdo->prepare("INSERT INTO [EmployeeManagementSystem].[dbo].[SalaryHistory] (emp_id, old_salary, new_salary, changed_by) VALUES (:eid, :old, :new, :uid)");
            $stmtHist->execute([':eid' => $emp_id, ':old' => $oldSalary, ':new' => $newSalary, ':uid' => $current_user_id]);
        }

        $signatureSQL = "";
        if($signaturePath) { $signatureSQL .= ", signature_path = :signature_path"; }
        $profilePicSQL = "";
        if($profilePicPath) { $profilePicSQL .= ", profile_picture = :profile_picture"; }

        $sql = "UPDATE [EmployeeManagementSystem].[dbo].[Employees] SET
            last_name = :last_name, first_name = :first_name, middle_name = :middle_name,
            date_of_birth = :date_of_birth, gender = :gender, address = :address,
            contact_number = :contact_number, email_address = :email_address, civil_status = :civil_status,
            nationality = :nationality, emergency_contact_name = :emergency_contact_name,
            emergency_contact_number = :emergency_contact_number, emergency_contact_relationship = :emergency_contact_relationship,
            dept_id = :dept_id, job_title = :job_title, job_level = :job_level, employment_status = :employment_status,
            location_assignment = :location_assignment, supervisor_id = :supervisor_id,
            employment_type = :employment_type, work_schedule = :work_schedule,
            salary_rate = :salary_rate, daily_rate = :daily_rate, hourly_rate = :hourly_rate,
            payroll_group = :payroll_group,
            tin_number = :tin_number, sss_number = :sss_number, philhealth_number = :philhealth_number,
            pagibig_number = :pagibig_number, bank_account_number = :bank_account_number,
            date_hired = :date_hired, date_deployed = :date_deployed, date_regularized = :date_regularized,
            contract_start_date = :contract_start_date, contract_end_date = :contract_end_date,
            approval_role = :approval_role, sil_credits = :sil_credits, updated_at = GETDATE()
            $signatureSQL $profilePicSQL
        WHERE emp_id = :emp_id";
        
        $stmt = $this->pdo->prepare($sql);

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
        $stmt = $this->pdo->prepare("SELECT * FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE emp_id = :id");
        $stmt->execute([':id' => $emp_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {
            return null;
        }

        // 2. Get Salary History
        $stmtHist = $this->pdo->prepare("SELECT * FROM [EmployeeManagementSystem].[dbo].[SalaryHistory] WHERE emp_id = :id ORDER BY change_date DESC");
        $stmtHist->execute([':id' => $emp_id]);
        $history = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

        // 3. Get Loans (With calculation for Paid Amount)
        $stmtLoans = $this->pdo->prepare("SELECT *, (total_payable - remaining_balance) as paid_amount FROM [EmployeeManagementSystem].[dbo].[EmployeeLoans] WHERE emp_id = :id ORDER BY status, start_date DESC");
        $stmtLoans->execute([':id' => $emp_id]);
        $loans = $stmtLoans->fetchAll(PDO::FETCH_ASSOC);
        
        // 4. Get Loan Payments (Transaction History)
        $payments = [];
        try {
            $stmtPay = $this->pdo->prepare("
                SELECT lp.payment_date, lp.amount_paid, el.loan_category
                FROM [EmployeeManagementSystem].[dbo].[LoanPayments] lp
                JOIN [EmployeeManagementSystem].[dbo].[EmployeeLoans] el ON lp.loan_id = el.loan_id
                WHERE el.emp_id = :id
                ORDER BY lp.payment_date DESC
            ");
            $stmtPay->execute([':id' => $emp_id]);
            $payments = $stmtPay->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Log error, but continue without payments
        }

        // 5. Get Documents
        $stmtDocs = $this->pdo->prepare("SELECT * FROM [EmployeeManagementSystem].[dbo].[EmployeeDocuments] WHERE emp_id = :id ORDER BY uploaded_at DESC");
        $stmtDocs->execute([':id' => $emp_id]);
        $docs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

        return [
            'employee' => $employee,
            'salary_history' => $history,
            'loans' => $loans,
            'loan_payments' => $payments,
            'documents' => $docs
        ];
    }

    public function uploadDocument($emp_id, $doc_name, $filePath)
    {
        $stmt = $this->pdo->prepare("INSERT INTO [EmployeeManagementSystem].[dbo].[EmployeeDocuments] (emp_id, doc_name, file_path) VALUES (?, ?, ?)");
        return $stmt->execute([$emp_id, $doc_name, $filePath]);
    }

    public function deleteDocument($doc_id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM [EmployeeManagementSystem].[dbo].[EmployeeDocuments] WHERE doc_id = ?");
        return $stmt->execute([$doc_id]);
    }

    public function softDeleteEmployee($emp_id)
    {
        $stmt = $this->pdo->prepare("UPDATE [EmployeeManagementSystem].[dbo].[Employees] SET IsActive = 0, updated_at = GETDATE() WHERE emp_id = ?");
        return $stmt->execute([$emp_id]);
    }

    public function restoreEmployee($emp_id)
    {
        $stmt = $this->pdo->prepare("UPDATE [EmployeeManagementSystem].[dbo].[Employees] SET IsActive = 1, updated_at = GETDATE() WHERE emp_id = ?");
        return $stmt->execute([$emp_id]);
    }

    public function hardDeleteEmployee($emp_id)
    {
        // First delete related records to avoid foreign key constraints
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM [EmployeeManagementSystem].[dbo].[SalaryHistory] WHERE emp_id = ?")->execute([$emp_id]);
            $this->pdo->prepare("DELETE FROM [EmployeeManagementSystem].[dbo].[EmployeeLoans] WHERE emp_id = ?")->execute([$emp_id]);
            $this->pdo->prepare("DELETE FROM [EmployeeManagementSystem].[dbo].[EmployeeDocuments] WHERE emp_id = ?")->execute([$emp_id]);
            $this->pdo->prepare("DELETE FROM [EmployeeManagementSystem].[dbo].[LeaveApplications] WHERE emp_id = ?")->execute([$emp_id]);
            $this->pdo->prepare("DELETE FROM [EmployeeManagementSystem].[dbo].[OvertimeApplications] WHERE emp_id = ?")->execute([$emp_id]);
            $this->pdo->prepare("DELETE FROM [EmployeeManagementSystem].[dbo].[ScheduleChangeRequests] WHERE emp_id = ?")->execute([$emp_id]);
            $this->pdo->prepare("DELETE FROM [EmployeeManagementSystem].[dbo].[EmployeeSchedules] WHERE employee_id = ?")->execute([$emp_id]);
            // Assuming other related tables might exist, add more DELETE statements here

            $stmt = $this->pdo->prepare("DELETE FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE emp_id = ?");
            $result = $stmt->execute([$emp_id]);
            $this->pdo->commit();
            return $result;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e; // Re-throw the exception to be handled by the caller
        }
    }
}
