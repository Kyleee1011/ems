<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Loan;
use PDO;
use PDOStatement;

/**
 * Unit Test for Loan Model
 */
class LoanTest extends TestCase
{
    private $mockPdo;
    private $loan;

    protected function setUp(): void
    {
        $this->mockPdo = $this->createMock(PDO::class);
        $this->loan = new Loan($this->mockPdo);
    }

    public function testGetLoansReturnsArray()
    {
        $expectedData = [
            ['loan_id' => 1, 'emp_id' => 1, 'principal_amount' => 10000, 'status' => 'Active'],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetchAll')->willReturn($expectedData);
        $this->mockPdo->method('prepare')->willReturn($mockStatement);

        $result = $this->loan->getLoans(1);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testGetStatsReturnsData()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchColumn')->willReturn(42);
        
        $this->mockPdo->method('query')->willReturn($mockStatement);

        $result = $this->loan->getStats();

        $this->assertIsArray($result);
        $this->assertEquals(42, $result['active']);
    }

    public function testUpdateStatusExecutesCorrectly()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())->method('execute')->with(['Paid', 1]);
        $this->mockPdo->method('prepare')->willReturn($mockStatement);

        $this->loan->updateStatus(1, 'Paid');
        
        $this->assertTrue(true);
    }

    public function testCreateLoanExecutesCorrectly()
    {
        $data = [
            'emp_id' => 1,
            'loan_category' => 'Salary',
            'description' => 'Test',
            'principal_amount' => 12000,
            'months_to_pay' => 12,
            'interest_rate' => 0,
            'deduction_frequency' => 'Monthly',
            'start_date' => '2025-01-01'
        ];

        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())->method('execute');
        $this->mockPdo->method('prepare')->willReturn($mockStatement);

        $this->loan->createLoan($data);
        
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        $this->mockPdo = null;
        $this->loan = null;
    }
}
