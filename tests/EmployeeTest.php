<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Employee;
use PDO;
use PDOStatement;

/**
 * Unit Test for Employee Model
 */
class EmployeeTest extends TestCase
{
    private $mockPdo;
    private $employee;

    protected function setUp(): void
    {
        $this->mockPdo = $this->createMock(PDO::class);
        $this->employee = new Employee($this->mockPdo);
    }

    public function testGetAllReturnsArray()
    {
        $expectedData = [
            ['emp_id' => 1, 'first_name' => 'John', 'last_name' => 'Doe'],
            ['emp_id' => 2, 'first_name' => 'Jane', 'last_name' => 'Smith'],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchAll')->willReturn($expectedData);
        $this->mockPdo->method('query')->willReturn($mockStatement);

        $result = $this->employee->getAll();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testFindReturnsEmployeeData()
    {
        $expectedData = ['emp_id' => 1, 'first_name' => 'John', 'last_name' => 'Doe'];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetch')->willReturn($expectedData);
        $this->mockPdo->method('prepare')->willReturn($mockStatement);

        $result = $this->employee->find(1);

        $this->assertIsArray($result);
        $this->assertEquals('John', $result['first_name']);
    }

    public function testFindReturnsNullForNonExistentEmployee()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetch')->willReturn(false);
        $this->mockPdo->method('prepare')->willReturn($mockStatement);

        $result = $this->employee->find(999);

        $this->assertFalse($result);
    }

    public function testGetDepartmentsReturnsArray()
    {
        $expectedData = [
            ['dept_id' => 1, 'dept_name' => 'IT'],
            ['dept_id' => 2, 'dept_name' => 'HR'],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchAll')->willReturn($expectedData);
        $this->mockPdo->method('query')->willReturn($mockStatement);

        $result = $this->employee->getDepartments();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetSupervisorsReturnsArray()
    {
        $expectedData = [
            ['emp_id' => 1, 'first_name' => 'John', 'last_name' => 'Doe'],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchAll')->willReturn($expectedData);
        $this->mockPdo->method('query')->willReturn($mockStatement);

        $result = $this->employee->getSupervisors();

        $this->assertIsArray($result);
    }

    public function testSoftDeleteEmployeeExecutesCorrectly()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())->method('execute')->with([1]);
        $this->mockPdo->method('prepare')->willReturn($mockStatement);

        $this->employee->softDeleteEmployee(1);
        
        $this->assertTrue(true);
    }

    public function testRestoreEmployeeExecutesCorrectly()
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())->method('execute')->with([1]);
        $this->mockPdo->method('prepare')->willReturn($mockStatement);

        $this->employee->restoreEmployee(1);
        
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        $this->mockPdo = null;
        $this->employee = null;
    }
}
