<?php

use PHPUnit\Framework\TestCase;
use App\Controllers\OrgChartController;

class OrgChartHierarchyTest extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE employees (
                emp_id INTEGER PRIMARY KEY AUTOINCREMENT,
                ac_no VARCHAR(50),
                full_name VARCHAR(200),
                dept_id INTEGER,
                job_title VARCHAR(100),
                approval_role VARCHAR(50),
                supervisor_id INTEGER NULL,
                IsActive INTEGER DEFAULT 1,
                employee_status VARCHAR(50) DEFAULT 'Active'
            );

            CREATE TABLE departments (
                dept_id INTEGER PRIMARY KEY AUTOINCREMENT,
                dept_name VARCHAR(100)
            );

            INSERT INTO departments (dept_id, dept_name) VALUES
            (1, 'Executive/Owner'),
            (2, 'Kitchen'),
            (3, 'Housekeeping');

            -- 1. CEO (Owner)
            INSERT INTO employees (emp_id, ac_no, full_name, dept_id, approval_role, supervisor_id, IsActive, employee_status)
            VALUES (1, 'CEO001', 'CEO Leader', 1, 'CEO', 999, 1, 'Active');

            -- 2. Department Heads (Kitchen has 2 heads, Housekeeping has 1 head)
            INSERT INTO employees (emp_id, ac_no, full_name, dept_id, approval_role, supervisor_id, IsActive, employee_status)
            VALUES 
            (2, 'KIT001', 'Kitchen Head 1', 2, 'DeptHead', NULL, 1, 'Active'),
            (3, 'KIT002', 'Kitchen Head 2', 2, 'DeptHead', 2, 1, 'Active'),
            (4, 'HSK001', 'Housekeeping Head', 3, 'DeptHead', NULL, 1, 'Active');

            -- 3. Staff (Some with NULL, some with incorrect/inactive supervisors)
            INSERT INTO employees (emp_id, ac_no, full_name, dept_id, approval_role, supervisor_id, IsActive, employee_status)
            VALUES 
            (5, 'KIT003', 'Kitchen Staff 1', 2, 'Employee', NULL, 1, 'Active'),
            (6, 'KIT004', 'Kitchen Staff 2', 2, 'Employee', 3, 1, 'Active'),
            (7, 'HSK002', 'Housekeeping Staff 1', 3, 'Employee', 999, 1, 'Active');

            -- 4. Inactive Employee
            INSERT INTO employees (emp_id, ac_no, full_name, dept_id, approval_role, supervisor_id, IsActive, employee_status)
            VALUES (8, 'INACT01', 'Inactive Staff', 3, 'Employee', 4, 0, 'Inactive');
        ");
    }

    public function testAutoSyncHierarchyStructuresAllEmployees(): void
    {
        OrgChartController::autoSyncHierarchy($this->pdo);

        // 1. CEO has supervisor_id = NULL
        $ceoSup = $this->pdo->query("SELECT supervisor_id FROM employees WHERE emp_id = 1")->fetchColumn();
        $this->assertNull($ceoSup, 'CEO must have supervisor_id = NULL');

        // 2. Both Kitchen Department Heads report to CEO (emp_id 1)
        $kitHead1Sup = $this->pdo->query("SELECT supervisor_id FROM employees WHERE emp_id = 2")->fetchColumn();
        $kitHead2Sup = $this->pdo->query("SELECT supervisor_id FROM employees WHERE emp_id = 3")->fetchColumn();
        $this->assertEquals(1, $kitHead1Sup, 'Kitchen Head 1 must report to CEO');
        $this->assertEquals(1, $kitHead2Sup, 'Kitchen Head 2 must report to CEO even with multiple heads in dept');

        // 3. Housekeeping Head reports to CEO
        $hskHeadSup = $this->pdo->query("SELECT supervisor_id FROM employees WHERE emp_id = 4")->fetchColumn();
        $this->assertEquals(1, $hskHeadSup, 'Housekeeping Head must report to CEO');

        // 4. Kitchen Staff 1 (was NULL) is auto-assigned to one of the active kitchen heads (emp_id 2 or 3)
        $kitStaff1Sup = $this->pdo->query("SELECT supervisor_id FROM employees WHERE emp_id = 5")->fetchColumn();
        $this->assertContains((int)$kitStaff1Sup, [2, 3], 'Kitchen Staff 1 must report to an active Kitchen Head');

        // 5. Kitchen Staff 2 was already assigned to Head 2 (emp_id 3) - assignment preserved
        $kitStaff2Sup = $this->pdo->query("SELECT supervisor_id FROM employees WHERE emp_id = 6")->fetchColumn();
        $this->assertEquals(3, $kitStaff2Sup, 'Kitchen Staff 2 assignment to Head 2 must be preserved');

        // 6. Housekeeping Staff 1 had invalid supervisor (999) - repaired to Housekeeping Head (emp_id 4)
        $hskStaff1Sup = $this->pdo->query("SELECT supervisor_id FROM employees WHERE emp_id = 7")->fetchColumn();
        $this->assertEquals(4, $hskStaff1Sup, 'Housekeeping Staff 1 must be repaired to Housekeeping Head');
    }
}
