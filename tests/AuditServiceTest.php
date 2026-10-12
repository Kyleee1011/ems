<?php

use PHPUnit\Framework\TestCase;
use App\Services\AuditService;
use App\Services\AuditActions;

class AuditServiceTest extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                actor_user_id INTEGER NULL,
                actor_role VARCHAR(50) NULL,
                action VARCHAR(100) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id VARCHAR(100) NULL,
                before_data TEXT NULL,
                after_data TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(500) NULL,
                request_id VARCHAR(36) NULL
            );

            CREATE TABLE employees (
                emp_id INTEGER PRIMARY KEY AUTOINCREMENT,
                ac_no VARCHAR(50) NOT NULL,
                full_name VARCHAR(200) NOT NULL
            );
        ");

        AuditService::init($this->pdo);
    }

    public function testSanitizesSensitiveFields(): void
    {
        $data = [
            'first_name' => 'John',
            'password' => 'secret123',
            'password_hash' => '$2y$10$abcdef...',
            'bank_account_number' => '1234567890',
            'salary_rate' => 25000.00
        ];

        $sanitized = AuditService::sanitize($data);

        $this->assertEquals('John', $sanitized['first_name']);
        $this->assertEquals('***REDACTED***', $sanitized['password']);
        $this->assertEquals('***REDACTED***', $sanitized['password_hash']);
        $this->assertEquals('***REDACTED***', $sanitized['bank_account_number']);
        $this->assertEquals(25000.00, $sanitized['salary_rate']);
    }

    public function testComputeDiffOnlyIncludesChanges(): void
    {
        $before = ['first_name' => 'John', 'last_name' => 'Doe', 'job_title' => 'Dev'];
        $after  = ['first_name' => 'John', 'last_name' => 'Smith', 'job_title' => 'Dev'];

        $diff = AuditService::computeDiff($before, $after);

        $this->assertArrayHasKey('last_name', $diff['before']);
        $this->assertArrayHasKey('last_name', $diff['after']);
        $this->assertEquals('Doe', $diff['before']['last_name']);
        $this->assertEquals('Smith', $diff['after']['last_name']);
        $this->assertArrayNotHasKey('first_name', $diff['before']);
        $this->assertArrayNotHasKey('job_title', $diff['before']);
    }

    public function testLogPersistsAuditEntry(): void
    {
        AuditService::log(
            AuditActions::EMPLOYEE_CREATED,
            'employee',
            'EMP001',
            null,
            ['ac_no' => 'EMP001', 'full_name' => 'Jane Doe'],
            1,
            'HR'
        );

        $stmt = $this->pdo->query("SELECT * FROM audit_logs WHERE entity_id = 'EMP001'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($row);
        $this->assertEquals(AuditActions::EMPLOYEE_CREATED, $row['action']);
        $this->assertEquals('employee', $row['entity_type']);
        $this->assertEquals('1', $row['actor_user_id']);
        $this->assertEquals('HR', $row['actor_role']);
        $this->assertNotEmpty($row['request_id']);

        $afterData = json_decode($row['after_data'], true);
        $this->assertEquals('EMP001', $afterData['ac_no']);
    }
}
