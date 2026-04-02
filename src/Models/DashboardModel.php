<?php
namespace App\Models;

use PDO;

class DashboardModel
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Methods for fetching stats cards data
    public function getTotalEmployees() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_status = 'Active' AND salary_rate > 0");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getNewHires() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_status = 'Active' AND salary_rate > 0 AND MONTH(date_hired) = MONTH(CURRENT_DATE()) AND YEAR(date_hired) = YEAR(CURRENT_DATE())");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getAvgSalary() {
        $stmt = $this->pdo->prepare("SELECT AVG(salary_rate) FROM employees WHERE employee_status = 'Active' AND salary_rate > 0 AND (salary_type = 'Monthly' OR salary_type IS NULL OR salary_type = '')");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getMaleEmployeeCount() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_status = 'Active' AND salary_rate > 0 AND gender = 'Male'");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getFemaleEmployeeCount() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_status = 'Active' AND salary_rate > 0 AND gender = 'Female'");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getUnassignedGenderCount() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_status = 'Active' AND salary_rate > 0 AND (gender IS NULL OR gender = '')");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getPendingLeavesCount() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM leave_applications WHERE status = 'Submitted' OR status = 'Pending'");
        $stmt->execute();
        return $stmt->fetchColumn();
    }


    public function getDepartmentStats() {
        $stmt = $this->pdo->prepare("SELECT d.dept_name, COUNT(e.emp_id) as count FROM employees e JOIN departments d ON e.dept_id = d.dept_id WHERE e.employee_status = 'Active' AND e.salary_rate > 0 GROUP BY d.dept_name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStatusStats() {
        $stmt = $this->pdo->prepare("SELECT employment_status, COUNT(*) as count FROM employees WHERE employee_status = 'Active' AND salary_rate > 0 GROUP BY employment_status");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingLeaveList() {
        $stmt = $this->pdo->prepare("SELECT l.*, e.first_name, e.last_name FROM leave_applications l JOIN employees e ON l.emp_id = e.emp_id WHERE l.status IN ('Submitted', 'Pending') ORDER BY l.created_at ASC LIMIT 5");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDepartments() {
        $stmt = $this->pdo->prepare("SELECT * FROM departments ORDER BY dept_name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveEmployeesByDept($dept_id) {
        $stmt = $this->pdo->prepare("SELECT emp_id, first_name, last_name FROM employees WHERE dept_id = ? AND employee_status = 'Active' ORDER BY last_name");
        $stmt->execute([$dept_id]);
        $emps = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $emps[] = ['id' => $row['emp_id'], 'name' => $row['first_name'] . ' ' . $row['last_name']];
        }
        return $emps;
    }

    public function getEmployeeProfile($userId) {
        $stmt = $this->pdo->prepare("SELECT e.*, d.dept_name 
                                     FROM employees e 
                                     LEFT JOIN departments d ON e.dept_id = d.dept_id 
                                     WHERE e.emp_id = ?");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Fallback for missing job/department if strictly needed by view, 
        // though LEFT JOIN handles it by returning nulls.
        // We'll return the raw array or a default structure.
        return $profile ?: [];
    }

    public function getManpowerStats() {
        // Fetch all active employees with a salary rate > 0
        $stmtEmps = $this->pdo->prepare("SELECT salary_rate, salary_type FROM employees WHERE employee_status = 'Active' AND salary_rate > 0");
        $stmtEmps->execute();
        $emps = $stmtEmps->fetchAll(PDO::FETCH_ASSOC);
        
        $totalSalary = 0;
        $totalERSss = 0;
        $totalERPhilHealth = 0;
        $totalERPagIbig = 0;
        
        // Fetch contribution tables for lookup
        $stmtSss = $this->pdo->prepare("SELECT min_salary, max_salary, er_share FROM payroll_sss_table ORDER BY min_salary ASC");
        $stmtSss->execute();
        $sssTable = $stmtSss->fetchAll(PDO::FETCH_ASSOC);
        
        // PhilHealth: 5% total (2.5% EE, 2.5% ER)
        $stmtPh = $this->pdo->prepare("SELECT rate, min_salary, max_salary FROM payroll_philhealth_table LIMIT 1");
        $stmtPh->execute();
        $phConfig = $stmtPh->fetch(PDO::FETCH_ASSOC);
        
        // Pag-IBIG: Fixed amount (usually 200 EE, 200 ER)
        $stmtPi = $this->pdo->prepare("SELECT fixed_amt FROM payroll_pagibig_table LIMIT 1");
        $stmtPi->execute();
        $piFixed = (float)$stmtPi->fetchColumn() ?: 200.00;
        
        foreach ($emps as $e) {
            $salary = (float)$e['salary_rate'];
            // Since we're calculating MANPOWER EXPENSE PER MONTH, we use the salary_rate directly
            $totalSalary += $salary;
            
            // SSS ER (Using the er_share from the table)
            $erSss = 0;
            foreach ($sssTable as $bracket) {
                if ($salary >= (float)$bracket['min_salary'] && $salary <= (float)$bracket['max_salary']) {
                    $erSss = (float)($bracket['er_share'] ?? 0);
                    break;
                }
            }
            $totalERSss += $erSss; 
            
            // PhilHealth ER (50% of total rate)
            if ($phConfig) {
                $compSalary = max((float)$phConfig['min_salary'], min($salary, (float)$phConfig['max_salary']));
                $totalPH = $compSalary * (float)$phConfig['rate'];
                $totalERPhilHealth += ($totalPH / 2);
            }
            
            // Pag-IBIG ER (Match the fixed amount)
            $totalERPagIbig += $piFixed;
        }
        
        return [
            'total_salary' => $totalSalary,
            'total_er_sss' => $totalERSss,
            'total_er_philhealth' => $totalERPhilHealth,
            'total_er_pagibig' => $totalERPagIbig,
            'total_manpower_expense' => $totalSalary + $totalERSss + $totalERPhilHealth + $totalERPagIbig
        ];
    }
}
