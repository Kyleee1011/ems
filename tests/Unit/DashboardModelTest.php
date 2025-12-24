<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\DashboardModel;
use PDO;
use PDOStatement;

/**
 * Unit Test for DashboardModel
 * 
 * This demonstrates proper unit testing with mocked dependencies
 */
class DashboardModelTest extends TestCase
{
    private $mockEmsPdo;
    private $mockSchedulerPdo;
    private $dashboardModel;

    protected function setUp(): void
    {
        // Create mock PDO connections
        $this->mockEmsPdo = $this->createMock(PDO::class);
        $this->mockSchedulerPdo = $this->createMock(PDO::class);
        
        // Create instance with mocked dependencies
        $this->dashboardModel = new DashboardModel($this->mockEmsPdo, $this->mockSchedulerPdo);
    }

    public function testGetTotalEmployeesReturnsInteger()
    {
        // Arrange: Set up mock to return a specific value
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchColumn')->willReturn(42);
        
        $this->mockEmsPdo->method('query')->willReturn($mockStatement);

        // Act: Call the method
        $result = $this->dashboardModel->getTotalEmployees();

        // Assert: Verify the result
        $this->assertIsInt($result);
        $this->assertEquals(42, $result);
    }

    public function testGetNewHiresReturnsInteger()
    {
        // Arrange
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchColumn')->willReturn(5);
        
        $this->mockEmsPdo->method('query')->willReturn($mockStatement);

        // Act
        $result = $this->dashboardModel->getNewHires();

        // Assert
        $this->assertIsInt($result);
        $this->assertEquals(5, $result);
    }

    public function testGetAvgSalaryReturnsNumeric()
    {
        // Arrange
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchColumn')->willReturn(35000.50);
        
        $this->mockEmsPdo->method('query')->willReturn($mockStatement);

        // Act
        $result = $this->dashboardModel->getAvgSalary();

        // Assert
        $this->assertIsNumeric($result);
        $this->assertEquals(35000.50, $result);
    }

    public function testGetDepartmentStatsReturnsArray()
    {
        // Arrange
        $expectedData = [
            ['dept_name' => 'IT', 'count' => 10],
            ['dept_name' => 'HR', 'count' => 5],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchAll')->willReturn($expectedData);
        
        $this->mockEmsPdo->method('query')->willReturn($mockStatement);

        // Act
        $result = $this->dashboardModel->getDepartmentStats();

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('IT', $result[0]['dept_name']);
        $this->assertEquals(10, $result[0]['count']);
    }

    public function testGetActiveEmployeesByDeptReturnsFormattedArray()
    {
        // Arrange
        $deptId = 1;
        $mockData = [
            ['emp_id' => 1, 'first_name' => 'John', 'last_name' => 'Doe'],
            ['emp_id' => 2, 'first_name' => 'Jane', 'last_name' => 'Smith'],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetch')
            ->willReturnOnConsecutiveCalls(
                $mockData[0],
                $mockData[1],
                false // End of results
            );
        
        $this->mockEmsPdo->method('prepare')->willReturn($mockStatement);

        // Act
        $result = $this->dashboardModel->getActiveEmployeesByDept($deptId);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals('John Doe', $result[0]['name']);
        $this->assertEquals(2, $result[1]['id']);
        $this->assertEquals('Jane Smith', $result[1]['name']);
    }

    protected function tearDown(): void
    {
        // Clean up
        $this->mockEmsPdo = null;
        $this->mockSchedulerPdo = null;
        $this->dashboardModel = null;
    }
}
