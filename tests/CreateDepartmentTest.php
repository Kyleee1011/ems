<?php

use PHPUnit\Framework\TestCase;
use App\Models\Employee;

class CreateDepartmentTest extends TestCase
{
    protected PDO $pdo;
    protected Employee $employeeModel;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE departments (
                dept_id INTEGER PRIMARY KEY AUTOINCREMENT,
                dept_name VARCHAR(100) NOT NULL,
                dept_code VARCHAR(20),
                color VARCHAR(20) DEFAULT '#0F766E'
            );

            CREATE TABLE scheduler_departments (
                dept_id INTEGER PRIMARY KEY,
                dept_name VARCHAR(100) NOT NULL
            );

            INSERT INTO departments (dept_id, dept_name, dept_code, color)
            VALUES (1, 'Human Resources', 'HR', '#C2410C');

            INSERT INTO scheduler_departments (dept_id, dept_name)
            VALUES (1, 'Human Resources');
        ");

        $this->employeeModel = new Employee($this->pdo);
    }

    public function testCreateDepartmentSuccess(): void
    {
        $newDeptId = $this->employeeModel->createDepartment('Information Technology', 'IT', '#1D4ED8');
        $this->assertGreaterThan(1, $newDeptId);

        // Verify in departments table
        $stmt = $this->pdo->prepare("SELECT * FROM departments WHERE dept_id = ?");
        $stmt->execute([$newDeptId]);
        $dept = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($dept);
        $this->assertEquals('Information Technology', $dept['dept_name']);
        $this->assertEquals('IT', $dept['dept_code']);
        $this->assertEquals('#1D4ED8', $dept['color']);

        // Verify synced in scheduler_departments
        $stmtSched = $this->pdo->prepare("SELECT * FROM scheduler_departments WHERE dept_id = ?");
        $stmtSched->execute([$newDeptId]);
        $schedDept = $stmtSched->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($schedDept);
        $this->assertEquals('Information Technology', $schedDept['dept_name']);
    }

    public function testCreateDepartmentAutoGeneratesShortCode(): void
    {
        $newDeptId = $this->employeeModel->createDepartment('Food and Beverage', '', '#BE123C');

        $stmt = $this->pdo->prepare("SELECT dept_code FROM departments WHERE dept_id = ?");
        $stmt->execute([$newDeptId]);
        $code = $stmt->fetchColumn();

        $this->assertEquals('FAB', $code);
    }

    public function testCreateDepartmentFailsOnDuplicateName(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("A department named 'human resources' already exists.");

        // Case-insensitive test
        $this->employeeModel->createDepartment('human resources');
    }

    public function testCreateDepartmentFailsOnEmptyName(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Department name is required.");

        $this->employeeModel->createDepartment('   ');
    }
}
