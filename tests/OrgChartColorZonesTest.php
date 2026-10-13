<?php

use PHPUnit\Framework\TestCase;
use App\Controllers\OrgChartController;
use App\Services\AuditService;
use App\Services\AuditActions;

class OrgChartColorZonesTest extends TestCase
{
    protected PDO $pdo;
    protected OrgChartController $controller;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Setup tables
        $this->pdo->exec("
            CREATE TABLE departments (
                dept_id INTEGER PRIMARY KEY AUTOINCREMENT,
                dept_name VARCHAR(100),
                dept_code VARCHAR(20),
                color VARCHAR(20) DEFAULT '#0F766E'
            );

            CREATE TABLE employees (
                emp_id INTEGER PRIMARY KEY AUTOINCREMENT,
                ac_no VARCHAR(50),
                first_name VARCHAR(100),
                last_name VARCHAR(100),
                full_name VARCHAR(200),
                job_title VARCHAR(100),
                job_level VARCHAR(50),
                dept_id INTEGER,
                approval_role VARCHAR(50),
                supervisor_id INTEGER NULL,
                profile_picture VARCHAR(255) NULL,
                IsActive INTEGER DEFAULT 1,
                employee_status VARCHAR(50) DEFAULT 'Active'
            );

            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                actor_user_id INTEGER NULL,
                actor_role VARCHAR(50) NULL,
                action VARCHAR(100) NOT NULL,
                entity_type VARCHAR(100) NOT NULL,
                entity_id VARCHAR(100) NULL,
                before_data TEXT NULL,
                after_data TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                request_id VARCHAR(64) NULL
            );

            CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                type VARCHAR(50),
                title VARCHAR(255),
                message TEXT,
                link VARCHAR(255),
                is_read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            INSERT INTO departments (dept_id, dept_name, color) VALUES
            (1, 'Human Resources', '#C2410C'),
            (2, 'Kitchen', '#B45309'),
            (3, 'MIS', '#1D4ED8');

            -- CEO
            INSERT INTO employees (emp_id, ac_no, first_name, last_name, full_name, dept_id, approval_role, supervisor_id)
            VALUES (1, 'CEO01', 'Aimee Mary', 'Buenaventura', 'Buenaventura, Aimee Mary', 10, 'CEO', NULL);

            -- HR Lead
            INSERT INTO employees (emp_id, ac_no, first_name, last_name, full_name, dept_id, approval_role, supervisor_id)
            VALUES (2, 'HR01', 'Kenneth', 'Gonzales', 'Gonzales, Kenneth', 1, 'HR', 1);

            -- Kitchen Head
            INSERT INTO employees (emp_id, ac_no, first_name, last_name, full_name, dept_id, approval_role, supervisor_id)
            VALUES (3, 'KIT01', 'Reynan', 'Mandap', 'Mandap, Reynan', 2, 'DeptHead', 1);

            -- Kitchen Staff
            INSERT INTO employees (emp_id, ac_no, first_name, last_name, full_name, dept_id, approval_role, supervisor_id)
            VALUES (4, 'KIT02', 'Nelson', 'Abril', 'Abril, Nelson', 2, 'Employee', 3);

            -- MIS Staff
            INSERT INTO employees (emp_id, ac_no, first_name, last_name, full_name, dept_id, approval_role, supervisor_id)
            VALUES (5, 'MIS01', 'Kyle Justine', 'Dimla', 'Dimla, Kyle Justine', 3, 'Employee', 1);
        ");

        AuditService::init($this->pdo);
        $this->controller = new OrgChartController($this->pdo);
    }

    public function testApprovedPaletteHasAtLeastTenDistinctColors(): void
    {
        $palette = OrgChartController::APPROVED_PALETTE;
        $this->assertGreaterThanOrEqual(10, count($palette), 'Approved palette must contain at least 10 colors');

        // Check required specific defaults from specification
        $this->assertArrayHasKey('#C2410C', $palette, 'HR Orange must exist in palette');
        $this->assertArrayHasKey('#1D4ED8', $palette, 'Engineering/MIS Blue must exist in palette');
        $this->assertArrayHasKey('#0F766E', $palette, 'Operations Teal must exist in palette');
        $this->assertArrayHasKey('#6D28D9', $palette, 'Finance Violet must exist in palette');

        // Check each has tint, text, name
        foreach ($palette as $hex => $info) {
            $this->assertArrayHasKey('tint', $info);
            $this->assertArrayHasKey('text', $info);
            $this->assertArrayHasKey('name', $info);
        }
    }

    public function testPaletteResolutionFallback(): void
    {
        $resolved = OrgChartController::getPaletteInfo('#C2410C');
        $this->assertEquals('#C2410C', $resolved['color']);
        $this->assertEquals('#FFEDD5', $resolved['tint']);

        // Invalid color falls back to default Teal
        $fallback = OrgChartController::getPaletteInfo('#ZZZZZZ');
        $this->assertEquals('#0F766E', $fallback['color']);
    }

    public function testFetchChartDataIncludesDepartmentColorsAndNorm(): void
    {
        $data = $this->controller->fetchChartData();

        $this->assertArrayHasKey('departments', $data);
        $this->assertArrayHasKey('employees', $data);
        $this->assertCount(3, $data['departments']);
        $this->assertCount(5, $data['employees']);

        $hrDept = null;
        foreach ($data['departments'] as $d) {
            if ($d['dept_name'] === 'Human Resources') $hrDept = $d;
        }
        $this->assertNotNull($hrDept);
        $this->assertEquals('#C2410C', $hrDept['color']);
        $this->assertEquals('#FFEDD5', $hrDept['tint']);
    }

    public function testColorUpdateReflectedInDatabaseAndAuditLog(): void
    {
        $oldColor = '#B45309';
        $newColor = '#BE123C';

        $upd = $this->pdo->prepare("UPDATE departments SET color = ? WHERE dept_id = 2");
        $upd->execute([$newColor]);

        AuditService::log(
            AuditActions::DEPARTMENT_COLOR_UPDATED,
            'department',
            '2',
            ['color' => $oldColor, 'dept_name' => 'Kitchen'],
            ['color' => $newColor, 'dept_name' => 'Kitchen']
        );

        $colorInDb = $this->pdo->query("SELECT color FROM departments WHERE dept_id = 2")->fetchColumn();
        $this->assertEquals('#BE123C', $colorInDb);

        // Check audit log
        $audit = $this->pdo->query("SELECT * FROM audit_logs WHERE entity_type = 'department' AND entity_id = '2'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($audit);
        $this->assertEquals(AuditActions::DEPARTMENT_COLOR_UPDATED, $audit['action']);
        $this->assertStringContainsString('#BE123C', $audit['after_data']);
    }

    public function testDepartmentSubtreeIsolation(): void
    {
        $data = $this->controller->fetchChartData();
        $kitchenStaff = array_values(array_filter($data['employees'], fn($e) => $e['dept_id'] === 2));

        $this->assertCount(2, $kitchenStaff, 'Kitchen department must have exactly 2 members');
        $names = array_column($kitchenStaff, 'full_name');
        $this->assertContains('Mandap, Reynan', $names);
        $this->assertContains('Abril, Nelson', $names);
    }
}
