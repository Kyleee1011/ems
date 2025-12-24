<?php
namespace App\Models;

use PDO;

class DashboardModel
{
    protected $emsPdo;
    protected $schedulerPdo;

    public function __construct(PDO $emsPdo, PDO $schedulerPdo)
    {
        $this->emsPdo = $emsPdo;
        $this->schedulerPdo = $schedulerPdo;
    }

    // Methods for fetching stats cards data
    public function getTotalEmployees() {
        return $this->emsPdo->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE employee_status = 'Active'")->fetchColumn();
    }

    public function getNewHires() {
        return $this->emsPdo->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE employee_status = 'Active' AND MONTH(date_hired) = MONTH(GETDATE())")->fetchColumn();
    }

    public function getAvgSalary() {
        return $this->emsPdo->query("SELECT AVG(salary_rate) FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE salary_type = 'Monthly' AND salary_rate > 0")->fetchColumn();
    }

    public function getMaleEmployeeCount() {
        return $this->emsPdo->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE employee_status = 'Active' AND gender = 'Male'")->fetchColumn();
    }

    public function getFemaleEmployeeCount() {
        return $this->emsPdo->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE employee_status = 'Active' AND gender = 'Female'")->fetchColumn();
    }

    public function getPendingLeavesCount() {
        return $this->emsPdo->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[LeaveApplications] WHERE status = 'Submitted' OR status = 'Pending'")->fetchColumn();
    }


    public function getDepartmentStats() {
        return $this->emsPdo->query("SELECT d.dept_name, COUNT(e.emp_id) as count FROM [EmployeeManagementSystem].[dbo].[Employees] e JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON e.dept_id = d.dept_id WHERE e.employee_status = 'Active' GROUP BY d.dept_name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStatusStats() {
        return $this->emsPdo->query("SELECT employment_status, COUNT(*) as count FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE employee_status = 'Active' GROUP BY employment_status")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingLeaveList() {
        return $this->emsPdo->query("SELECT TOP 5 l.*, e.first_name, e.last_name FROM [EmployeeManagementSystem].[dbo].[LeaveApplications] l JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON l.emp_id = e.emp_id WHERE l.status IN ('Submitted', 'Pending') ORDER BY l.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDepartments() {
        return $this->emsPdo->query("SELECT * FROM [EmployeeManagementSystem].[dbo].[Departments] ORDER BY dept_name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveEmployeesByDept($dept_id) {
        $stmt = $this->emsPdo->prepare("SELECT emp_id, first_name, last_name FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE dept_id = ? AND employee_status = 'Active' ORDER BY last_name");
        $stmt->execute([$dept_id]);
        $emps = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $emps[] = ['id' => $row['emp_id'], 'name' => $row['first_name'] . ' ' . $row['last_name']];
        }
        return $emps;
    }

    // You would add more methods here as you extract logic from the Controller
}
