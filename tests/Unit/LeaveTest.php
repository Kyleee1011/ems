<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Leave;
use PDO;
use PDOStatement;
use Exception;

/**
 * Unit Test for Leave Model
 */
class LeaveTest extends TestCase
{
    private $mockPdo;
    private $mockSchedulerPdo;
    private $leave;

    protected function setUp(): void
    {
        $this->mockPdo = $this->createMock(PDO::class);
        $this->mockSchedulerPdo = $this->createMock(PDO::class);
        $this->leave = new Leave($this->mockPdo, $this->mockSchedulerPdo);
    }

    public function testGetCreditsReturnsNumeric()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetchColumn')->willReturn(15.0);
        $this->mockPdo->method('prepare')->willReturn($mockStatement);

        $result = $this->leave->getCredits(1);

        $this->assertIsNumeric($result);
        $this->assertEquals(15.0, $result);
    }

    public function testGetPendingLeavesReturnsArray()
    {
        $expectedData = [
            ['id' => 1, 'emp_id' => 1, 'status' => 'Submitted'],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchAll')->willReturn($expectedData);
        $this->mockPdo->method('query')->willReturn($mockStatement);

        $result = $this->leave->getPendingLeaves();

        $this->assertIsArray($result);
    }

    public function testUpdateLeaveStatusExecutesCorrectly()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('rowCount')->willReturn(1); // Crucial to avoid "already processed" exception
        
        $this->mockPdo->method('prepare')->willReturn($mockStatement);
        $this->mockPdo->method('beginTransaction')->willReturn(true);
        $this->mockPdo->method('commit')->willReturn(true);

        $this->leave->updateLeaveStatus(1, 'Rejected', 1);
        
        $this->assertTrue(true);
    }

    public function testApplyLeaveExecutesCorrectly()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->atLeastOnce())->method('execute')->willReturn(true);
        $mockStatement->method('fetchColumn')->willReturn(0); // 0 existing leaves
        
        $this->mockPdo->method('prepare')->willReturn($mockStatement);

        $this->leave->applyLeave(1, 'Sick', '2025-01-01', '2025-01-02', 'Fever');
        
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        $this->mockPdo = null;
        $this->mockSchedulerPdo = null;
        $this->leave = null;
    }
}
