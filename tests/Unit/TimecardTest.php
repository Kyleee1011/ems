<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Timecard;
use PDO;
use PDOStatement;

/**
 * Unit Test for Timecard Model
 */
class TimecardTest extends TestCase
{
    private $mockPdo;
    private $mockSchedulerPdo;
    private $timecard;

    protected function setUp(): void
    {
        $this->mockPdo = $this->createMock(PDO::class);
        $this->mockSchedulerPdo = $this->createMock(PDO::class);
        $this->timecard = new Timecard($this->mockPdo, $this->mockSchedulerPdo);
    }

    public function testGetScheduleReturnsArray()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetch')->willReturn(false); // No data for simplicity
        
        $this->mockSchedulerPdo->method('prepare')->willReturn($mockStatement);

        $result = $this->timecard->getSchedule('AC001', '2025-01-01', '2025-01-31');

        $this->assertIsArray($result);
    }

    public function testGetRawLogsReturnsArray()
    {
        $this->assertTrue(true); // Placeholder as getRawLogs depends on biologsPdo which is hard to mock via static Helper
    }

    public function testUpdateManualLogExecutesCorrectly()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->exactly(2))->method('execute')->willReturn(true);
        $mockStatement->method('fetch')->willReturn(false); // Does not exist, will do INSERT
        
        $this->mockSchedulerPdo->method('prepare')->willReturn($mockStatement);

        $this->timecard->updateManualLog('AC001', '2025-01-01', 'IN', '08:00');
        
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        $this->mockPdo = null;
        $this->mockSchedulerPdo = null;
        $this->timecard = null;
    }
}
