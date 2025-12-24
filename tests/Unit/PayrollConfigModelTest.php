<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\PayrollConfigModel;
use PDO;
use PDOStatement;

/**
 * Unit Test for PayrollConfigModel
 */
class PayrollConfigModelTest extends TestCase
{
    private $mockSchedulerPdo;
    private $model;

    protected function setUp(): void
    {
        $this->mockSchedulerPdo = $this->createMock(PDO::class);
        $this->model = new PayrollConfigModel($this->mockSchedulerPdo);
    }

    public function testGetSssDataReturnsArray()
    {
        $expectedData = [
            ['id' => 1, 'min_salary' => 0, 'max_salary' => 4250, 'ee_share' => 180],
            ['id' => 2, 'min_salary' => 4250, 'max_salary' => 4750, 'ee_share' => 202.50],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchAll')->willReturn($expectedData);
        $this->mockSchedulerPdo->method('query')->willReturn($mockStatement);

        $result = $this->model->getSssData();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(180, $result[0]['ee_share']);
    }

    public function testUpdateSssTableExecutesCorrectly()
    {
        $sssData = [
            1 => ['min' => 0, 'max' => 4250, 'ee' => 180],
            2 => ['min' => 4250, 'max' => 4750, 'ee' => 202.50],
        ];

        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->exactly(2))->method('execute');
        $this->mockSchedulerPdo->method('prepare')->willReturn($mockStatement);

        $this->model->updateSssTable($sssData);
        
        $this->assertTrue(true); // If no exception, update succeeded
    }

    public function testGetPhilHealthDataReturnsArray()
    {
        $expectedData = [
            ['id' => 1, 'min_salary' => 0, 'max_salary' => 10000, 'rate' => 0.04],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchAll')->willReturn($expectedData);
        $this->mockSchedulerPdo->method('query')->willReturn($mockStatement);

        $result = $this->model->getPhilHealthData();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testGetPagIbigDataReturnsArray()
    {
        $expectedData = [
            ['id' => 1, 'fixed_amt' => 100],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchAll')->willReturn($expectedData);
        $this->mockSchedulerPdo->method('query')->willReturn($mockStatement);

        $result = $this->model->getPagIbigData();

        $this->assertIsArray($result);
    }

    public function testGetTaxDataReturnsArray()
    {
        $expectedData = [
            ['id' => 1, 'min_salary' => 0, 'max_salary' => 20833, 'base_tax' => 0, 'excess_rate' => 0],
            ['id' => 2, 'min_salary' => 20833, 'max_salary' => 33332, 'base_tax' => 0, 'excess_rate' => 0.15],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchAll')->willReturn($expectedData);
        $this->mockSchedulerPdo->method('query')->willReturn($mockStatement);

        $result = $this->model->getTaxData();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetOvertimeRulesReturnsArray()
    {
        $expectedData = [
            ['id' => 1, 'ot_multiplier' => 1.25, 'night_diff_percent' => 0.10],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchAll')->willReturn($expectedData);
        $this->mockSchedulerPdo->method('query')->willReturn($mockStatement);

        $result = $this->model->getOvertimeRules();

        $this->assertIsArray($result);
    }

    public function testGetHolidayRulesReturnsArray()
    {
        $expectedData = [
            ['id' => 1, 'pay_if_unworked' => 1.0, 'pay_if_worked' => 2.0],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchAll')->willReturn($expectedData);
        $this->mockSchedulerPdo->method('query')->willReturn($mockStatement);

        $result = $this->model->getHolidayRules();

        $this->assertIsArray($result);
    }

    public function testGetGeneralSettingsReturnsAssociativeArray()
    {
        $rawData = [
            ['setting_key' => 'company_name', 'setting_value' => 'ACME Corp'],
            ['setting_key' => 'payroll_day', 'setting_value' => '15'],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchAll')->willReturn($rawData);
        $this->mockSchedulerPdo->method('query')->willReturn($mockStatement);

        $result = $this->model->getGeneralSettings();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('company_name', $result);
        $this->assertEquals('ACME Corp', $result['company_name']);
        $this->assertEquals('15', $result['payroll_day']);
    }

    public function testGetAllowanceTypesReturnsArray()
    {
        $expectedData = [
            ['id' => 1, 'name' => 'Transportation', 'amount' => 500, 'is_active' => 1],
        ];
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('fetchAll')->willReturn($expectedData);
        $this->mockSchedulerPdo->method('query')->willReturn($mockStatement);

        $result = $this->model->getAllowanceTypes();

        $this->assertIsArray($result);
    }

    protected function tearDown(): void
    {
        $this->mockSchedulerPdo = null;
        $this->model = null;
    }
}
