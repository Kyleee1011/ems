<?php

use PHPUnit\Framework\TestCase;
use App\Models\Leave;

class LeaveCreditValidationTest extends TestCase
{
    protected PDO $pdo;
    protected Leave $leaveModel;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE employees (
                emp_id INTEGER PRIMARY KEY AUTOINCREMENT,
                ac_no VARCHAR(50) NOT NULL,
                full_name VARCHAR(200) NOT NULL,
                sil_credits REAL DEFAULT 0.0,
                vl_credits REAL DEFAULT 0.0,
                sl_credits REAL DEFAULT 0.0,
                solo_parent_credits REAL DEFAULT 7.0,
                paternity_credits REAL DEFAULT 7.0,
                maternity_credits REAL DEFAULT 105.0,
                bereavement_immediate_credits REAL DEFAULT 5.0,
                bereavement_extended_credits REAL DEFAULT 3.0
            );

            INSERT INTO employees (emp_id, ac_no, full_name, sil_credits, vl_credits, sl_credits, solo_parent_credits)
            VALUES (1, 'EMP001', 'Juan Dela Cruz', 0.0, 5.0, 10.0, 7.0);
        ");

        $this->leaveModel = new Leave($this->pdo);
    }

    public function testSilValidationFailsWhenZeroCredits(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('SIL cannot be selected without SIL credits');
        $this->leaveModel->validateLeaveCredits(1, 'Service Incentive Leave', 1.0);
    }

    public function testSilValidationPassesWhenSufficientCredits(): void
    {
        $this->leaveModel->updateEmployeeCredits(1, ['sil_credits' => 5.0]);
        $this->leaveModel->validateLeaveCredits(1, 'Service Incentive Leave', 3.0);
        $this->assertTrue(true);
    }

    public function testVlValidationChecksCredits(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient Vacation Leave credits');
        $this->leaveModel->validateLeaveCredits(1, 'Vacation Leave', 6.0);
    }

    public function testBereavementImmediateLimit(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('maximum 5 days');
        $this->leaveModel->validateLeaveCredits(1, 'Bereavement (Immediate Family)', 6.0);
    }

    public function testBereavementExtendedLimit(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('maximum 3 days');
        $this->leaveModel->validateLeaveCredits(1, 'Bereavement (Extended Family)', 4.0);
    }

    public function testPaternityLimit(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('maximum 7 days');
        $this->leaveModel->validateLeaveCredits(1, 'Paternity Leave', 8.0);
    }

    public function testMaternityLimit(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('maximum 105 days');
        $this->leaveModel->validateLeaveCredits(1, 'Maternity Leave', 106.0);
    }

    public function testSoloParentLimit(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Solo Parent Leave request exceeds available credits');
        $this->leaveModel->validateLeaveCredits(1, 'Solo Parent Leave', 8.0);
    }

    public function testBereavementLeaveUnifiedLimit(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('maximum 5 days');
        $this->leaveModel->validateLeaveCredits(1, 'Bereavement Leave', 6.0);
    }

    public function testVlValidationPassesWithinAllocation(): void
    {
        $this->leaveModel->validateLeaveCredits(1, 'Vacation Leave', 4.0);
        $this->assertTrue(true);
    }

    public function testSlValidationChecksCredits(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient Sick Leave credits');
        $this->leaveModel->validateLeaveCredits(1, 'Sick Leave', 15.0);
    }

    public function testOffDoesNotThrow(): void
    {
        $this->leaveModel->validateLeaveCredits(1, 'Off', 1.0);
        $this->assertTrue(true);
    }
}
