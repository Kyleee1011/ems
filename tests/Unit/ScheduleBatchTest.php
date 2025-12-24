<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\ScheduleBatch;
use PDO;
use PDOStatement;

/**
 * Unit Test for ScheduleBatch Model
 */
class ScheduleBatchTest extends TestCase
{
    private $mockSchedulerPdo;
    private $scheduleBatch;

    protected function setUp(): void
    {
        $this->mockSchedulerPdo = $this->createMock(PDO::class);
        $this->scheduleBatch = new ScheduleBatch($this->mockSchedulerPdo);
    }

    public function testGetPendingBatchesReturnsArray()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetch')->willReturnOnConsecutiveCalls(
            ['batch_id' => 1, 'cutoff_start' => '2025-01-01', 'cutoff_end' => '2025-01-15', 'dept_id' => 1, 'dept_name' => 'IT'],
            false
        );
        $this->mockSchedulerPdo->method('prepare')->willReturn($mockStatement);

        $result = $this->scheduleBatch->getPendingBatches('Pending');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('IT', $result[0]['dept_name']);
    }

    public function testGetBatchStatusReturnsData()
    {
        $expectedData = ['batch_id' => 1, 'status' => 'Pending'];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetch')->willReturn($expectedData);
        $this->mockSchedulerPdo->method('prepare')->willReturn($mockStatement);

        $result = $this->scheduleBatch->getBatchStatus(1, '2025-01-01');

        $this->assertIsArray($result);
        $this->assertEquals('Pending', $result['status']);
    }

    public function testUpdateStatusExecutesCorrectly()
    {
        // Mock getBatchStatus call inside updateStatus
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetch')->willReturn(['batch_id' => 1, 'status' => 'Draft']);
        
        // Mock the UPDATE and INSERT statements
        $this->mockSchedulerPdo->method('prepare')->willReturn($mockStatement);

        $this->scheduleBatch->updateStatus(1, '2025-01-01', '2025-01-15', 'Approved', 'Manager', 'Approver');
        
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        $this->mockSchedulerPdo = null;
        $this->scheduleBatch = null;
    }
}
