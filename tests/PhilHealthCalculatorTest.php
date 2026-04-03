<?php

use PHPUnit\Framework\TestCase;
use App\Services\Payroll\PhilHealthCalculator;

class PhilHealthCalculatorTest extends TestCase
{
    protected $pdo;
    protected $calculator;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create table
        $this->pdo->exec("
            CREATE TABLE payroll_philhealth_table (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                min_salary DECIMAL(18,2),
                max_salary DECIMAL(18,2),
                rate DECIMAL(5,4)
            )
        ");

        // Seed with sample data (5% rate, shared 50/50, so effective 2.5% for EE)
        $this->pdo->exec("
            INSERT INTO payroll_philhealth_table (min_salary, max_salary, rate) VALUES
            (10000.00, 100000.00, 0.0500)
        ");

        $this->calculator = new PhilHealthCalculator($this->pdo);
    }

    public function testCalculateFloor()
    {
        // Salary below floor (10,000)
        // 10,000 * 0.05 / 2 = 250
        $result = $this->calculator->calculate(8000);
        $this->assertEquals(250.00, $result);
    }

    public function testCalculateNormal()
    {
        // 35,000 * 0.05 / 2 = 875
        $result = $this->calculator->calculate(35000);
        $this->assertEquals(875.00, $result);
    }

    public function testCalculateCeiling()
    {
        // Salary above ceiling (100,000)
        // 100,000 * 0.05 / 2 = 2500
        $result = $this->calculator->calculate(120000);
        $this->assertEquals(2500.00, $result);
    }
}
