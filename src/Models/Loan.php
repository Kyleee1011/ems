<?php
namespace App\Models;

use PDO;
use Exception;

class Loan
{
    protected $emsPdo;

    public function __construct(PDO $emsPdo)
    {
        $this->emsPdo = $emsPdo;
    }

    public function createLoan($data)
    {
        $principal = floatval($data['principal_amount']);
        $months = intval($data['months_to_pay']);
        $rate = floatval($data['interest_rate']);
        $freq = $data['deduction_frequency'];

        if ($principal <= 0 || $months <= 0) throw new Exception("Invalid principal or term.");

        $interest_amount = $principal * ($rate / 100);
        $total_payable = $principal + $interest_amount;
        $monthly_amort = $total_payable / $months;
        $cutoff_deduction = ($freq === 'Semi-monthly') ? ($monthly_amort / 2) : $monthly_amort;

        $sql = "INSERT INTO [EmployeeManagementSystem].[dbo].[EmployeeLoans] 
                (emp_id, loan_category, description, principal_amount, interest_rate, interest_amount, 
                 total_payable, months_to_pay, deduction_frequency, monthly_amortization, 
                 per_cutoff_deduction, remaining_balance, start_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";
        
        $stmt = $this->emsPdo->prepare($sql);
        return $stmt->execute([
            $data['emp_id'], $data['loan_category'], $data['description'], $principal, $rate, $interest_amount, 
            $total_payable, $months, $freq, $monthly_amort, 
            $cutoff_deduction, $total_payable, $data['start_date']
        ]);
    }

    public function updateStatus($loanId, $status)
    {
        $stmt = $this->emsPdo->prepare("UPDATE [EmployeeManagementSystem].[dbo].[EmployeeLoans] SET status = ? WHERE loan_id = ?");
        return $stmt->execute([$status, $loanId]);
    }

    public function getStats()
    {
        $active = $this->emsPdo->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[EmployeeLoans] WHERE status = 'Active'")->fetchColumn();
        $receivable = $this->emsPdo->query("SELECT SUM(remaining_balance) FROM [EmployeeManagementSystem].[dbo].[EmployeeLoans] WHERE status = 'Active'")->fetchColumn();
        $new = $this->emsPdo->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[EmployeeLoans] WHERE MONTH(created_at) = MONTH(GETDATE())")->fetchColumn();
        
        return [
            'active' => $active,
            'receivable' => $receivable ?: 0,
            'new_this_month' => $new
        ];
    }

    public function getLoans($empId = null, $status = null)
    {
        $sql = "SELECT l.*, e.first_name, e.last_name, e.ac_no 
                FROM [EmployeeManagementSystem].[dbo].[EmployeeLoans] l 
                JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON l.emp_id = e.emp_id 
                WHERE 1=1";
        $params = [];

        if (!empty($empId)) {
            $sql .= " AND l.emp_id = ?";
            $params[] = $empId;
        }

        if (!empty($status)) {
            $sql .= " AND l.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY CASE WHEN l.status='Active' THEN 1 ELSE 2 END, l.start_date DESC";
        
        $stmt = $this->emsPdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmployees()
    {
        return $this->emsPdo->query("SELECT emp_id, ac_no, first_name, last_name FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE IsActive = 1 ORDER BY last_name")->fetchAll(PDO::FETCH_ASSOC);
    }
}
