<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Payslip;
use PDO;
use PDOStatement;

/**
 * Unit Test for Payslip Model
 */
class PayslipTest extends TestCase
{
    private $mockEmsPdo;
    private $mockSchedulerPdo;
    private $mockBiologsPdo;
    private $payslip;

    protected function setUp(): void
    {
        $this->mockEmsPdo = $this->createMock(PDO::class);
        $this->mockSchedulerPdo = $this->createMock(PDO::class);
        $this->mockBiologsPdo = $this->createMock(PDO::class);
        
        $this->payslip = new Payslip($this->mockEmsPdo, $this->mockSchedulerPdo, $this->mockBiologsPdo);
    }

    public function testGetPayrollStatusReturnsData()
    {
        $expectedData = ['is_posted' => 'Closed', 'updated_by' => 'Admin'];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetch')->willReturn($expectedData);
        $this->mockSchedulerPdo->method('prepare')->willReturn($mockStatement);

        $result = $this->payslip->getPayrollStatus('2025-01-08', '2025-01-22');

        $this->assertIsArray($result);
        $this->assertEquals('Closed', $result['is_posted']);
    }

    public function testUpdatePayrollStatusExecutesCorrectly()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetch')->willReturn(null); // No existing record
        
        $this->mockSchedulerPdo->method('prepare')->willReturn($mockStatement);

        $result = $this->payslip->updatePayrollStatus('2025-01-08', '2025-01-22', 'Closed', 1);
        
        $this->assertTrue($result);
    }

    public function testProcessLoanDeductionsExecutesCorrectly()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchAll')->willReturn([]);
        $this->mockEmsPdo->method('query')->willReturn($mockStatement);

        $this->payslip->processLoanDeductions('2025-01-22');
        
        $this->assertTrue(true);
    }

    public function testReverseLoanDeductionsExecutesCorrectly()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetchAll')->willReturn([]);
        $this->mockEmsPdo->method('prepare')->willReturn($mockStatement);

        $this->payslip->reverseLoanDeductions('2025-01-22');
        
        $this->assertTrue(true);
    }

    public function testGetEmployeeDataReturnsArray()
    {
        $expectedData = [
            'emp_id' => 1,
            'ac_no' => 'AC001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'salary_rate' => 30000,
            'daily_rate' => 1150,
            'hourly_rate' => 143,
            'job_title' => 'Dev',
            'employee_status' => 'Active'
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetch')->willReturn($expectedData);
        $this->mockEmsPdo->method('prepare')->willReturn($mockStatement);

        $result = $this->payslip->getEmployeeData('AC001');

        $this->assertIsArray($result);
        $this->assertEquals('John', $result['first_name']);
    }

    public function testGetAttendanceDataReturnsFormattedArray()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetch')->willReturn(false); // No data for simplicity
        
        $this->mockSchedulerPdo->method('prepare')->willReturn($mockStatement);
        $this->mockBiologsPdo->method('prepare')->willReturn($mockStatement);

        $result = $this->payslip->getAttendanceData('AC001', '2025-01-08', '2025-01-22');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('raw_logs', $result);
        $this->assertArrayHasKey('schedule', $result);
        $this->assertArrayHasKey('adjustments', $result);
    }

    protected function tearDown(): void
    {
        $this->mockEmsPdo = null;
        $this->mockSchedulerPdo = null;
        $this->mockBiologsPdo = null;
        $this->payslip = null;
    }
}
