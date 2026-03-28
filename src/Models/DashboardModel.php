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
        return $this->pdo->query("SELECT COUNT(*) FROM employees WHERE employee_status = 'Active'")->fetchColumn();
    }

    public function getNewHires() {
        return $this->pdo->query("SELECT COUNT(*) FROM employees WHERE employee_status = 'Active' AND MONTH(date_hired) = MONTH(CURRENT_DATE()) AND YEAR(date_hired) = YEAR(CURRENT_DATE())")->fetchColumn();
    }

    public function getAvgSalary() {
        return $this->pdo->query("SELECT AVG(salary_rate) FROM employees WHERE salary_type = 'Monthly' AND salary_rate > 0")->fetchColumn();
    }

    public function getMaleEmployeeCount() {
        return $this->pdo->query("SELECT COUNT(*) FROM employees WHERE employee_status = 'Active' AND gender = 'Male'")->fetchColumn();
    }

    public function getFemaleEmployeeCount() {
        return $this->pdo->query("SELECT COUNT(*) FROM employees WHERE employee_status = 'Active' AND gender = 'Female'")->fetchColumn();
    }

    public function getUnassignedGenderCount() {
        return $this->pdo->query("SELECT COUNT(*) FROM employees WHERE employee_status = 'Active' AND (gender IS NULL OR gender = '')")->fetchColumn();
    }

    public function getPendingLeavesCount() {
        return $this->pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status = 'Submitted' OR status = 'Pending'")->fetchColumn();
    }


    public function getDepartmentStats() {
        return $this->pdo->query("SELECT d.dept_name, COUNT(e.emp_id) as count FROM employees e JOIN departments d ON e.dept_id = d.dept_id WHERE e.employee_status = 'Active' GROUP BY d.dept_name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStatusStats() {
        return $this->pdo->query("SELECT employment_status, COUNT(*) as count FROM employees WHERE employee_status = 'Active' GROUP BY employment_status")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingLeaveList() {
        return $this->pdo->query("SELECT l.*, e.first_name, e.last_name FROM leave_applications l JOIN employees e ON l.emp_id = e.emp_id WHERE l.status IN ('Submitted', 'Pending') ORDER BY l.created_at ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDepartments() {
        return $this->pdo->query("SELECT * FROM departments ORDER BY dept_name")->fetchAll(PDO::FETCH_ASSOC);
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
}
