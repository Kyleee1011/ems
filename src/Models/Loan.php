<?php
namespace App\Models;

use PDO;
use Exception;

class Loan
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
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

        // Default to Pending HR
        $hr_status = 'Pending';
        $status = 'Pending HR'; 

        $sql = "INSERT INTO employee_loans 
                (emp_id, loan_category, description, principal_amount, interest_rate, interest_amount, 
                 total_payable, months_to_pay, deduction_frequency, monthly_amortization, 
                 per_cutoff_deduction, remaining_balance, start_date, status, hr_approval_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['emp_id'], $data['loan_category'], $data['description'], $principal, $rate, $interest_amount, 
            $total_payable, $months, $freq, $monthly_amort, 
            $cutoff_deduction, $total_payable, $data['start_date'], $status, $hr_status
        ]);
    }

    public function updateHRStatus($loanId, $status, $userId, $reason = null)
    {
        $updateStatus = ($status === 'Approved') ? 'Pending CEO' : 'Rejected';
        $hrStatus = $status; // Approved or Rejected
        
        $sql = "UPDATE employee_loans SET 
                hr_approval_status = ?, 
                hr_approved_by = ?, 
                hr_approved_at = NOW(), 
                status = ?,
                rejection_reason = ? 
                WHERE loan_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$hrStatus, $userId, $updateStatus, $reason, $loanId]);
    }

    public function updateCEOStatus($loanId, $status, $userId, $reason = null)
    {
        // If Approved, it becomes Active. If Rejected, Rejected.
        $updateStatus = ($status === 'Approved') ? 'Active' : 'Rejected';
        $ceoStatus = $status;
        
        $sql = "UPDATE employee_loans SET 
                ceo_approval_status = ?, 
                ceo_approved_by = ?, 
                ceo_approved_at = NOW(), 
                status = ?, 
                rejection_reason = IF(?, ?, rejection_reason)
                WHERE loan_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$ceoStatus, $userId, $updateStatus, $reason, $reason, $loanId]);
    }

    public function updateLoanDetails($loanId, $principal, $rate, $months, $freq)
    {
        // Recalculate
        $interest_amount = $principal * ($rate / 100);
        $total_payable = $principal + $interest_amount;
        $monthly_amort = $total_payable / $months;
        $cutoff_deduction = ($freq === 'Semi-monthly') ? ($monthly_amort / 2) : $monthly_amort;
        
        $sql = "UPDATE employee_loans SET 
                principal_amount = ?, 
                interest_rate = ?, 
                months_to_pay = ?, 
                deduction_frequency = ?,
                interest_amount = ?, 
                total_payable = ?, 
                monthly_amortization = ?, 
                per_cutoff_deduction = ?, 
                remaining_balance = ? 
                WHERE loan_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $principal, $rate, $months, $freq, 
            $interest_amount, $total_payable, $monthly_amort, $cutoff_deduction, $total_payable, 
            $loanId
        ]);
    }

    public function updateStatus($loanId, $status)
    {
        $stmt = $this->pdo->prepare("UPDATE employee_loans SET status = ? WHERE loan_id = ?");
        return $stmt->execute([$status, $loanId]);
    }

    public function getStats()
    {
        $active = $this->pdo->query("SELECT COUNT(*) FROM employee_loans WHERE status = 'Active'")->fetchColumn();
        $pending = $this->pdo->query("SELECT COUNT(*) FROM employee_loans WHERE status IN ('Pending HR', 'Pending CEO')")->fetchColumn();
        $receivable = $this->pdo->query("SELECT SUM(remaining_balance) FROM employee_loans WHERE status = 'Active'")->fetchColumn();
        $new = $this->pdo->query("SELECT COUNT(*) FROM employee_loans WHERE MONTH(created_at) = MONTH(NOW())")->fetchColumn();
        
        return [
            'active' => $active,
            'pending' => $pending,
            'receivable' => $receivable ?: 0,
            'new_this_month' => $new
        ];
    }

    public function getLoans($empId = null, $status = null)
    {
        $sql = "SELECT l.*, e.first_name, e.last_name, e.ac_no 
                FROM employee_loans l 
                JOIN employees e ON l.emp_id = e.emp_id 
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

        // Prioritize pending actions
        $sql .= " ORDER BY FIELD(l.status, 'Pending HR', 'Pending CEO', 'Active', 'Hold', 'Paid', 'Rejected'), l.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPendingLoans($role)
    {
        $targetStatus = ($role === 'HR') ? 'Pending HR' : 'Pending CEO';
        $sql = "SELECT l.*, e.first_name, e.last_name, e.ac_no 
                FROM employee_loans l 
                JOIN employees e ON l.emp_id = e.emp_id 
                WHERE l.status = ? ORDER BY l.created_at ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$targetStatus]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmployees()
    {
        return $this->pdo->query("SELECT emp_id, ac_no, first_name, last_name FROM employees WHERE IsActive = 1 ORDER BY last_name")->fetchAll(PDO::FETCH_ASSOC);
    }
}
