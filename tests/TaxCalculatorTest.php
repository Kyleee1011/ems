<?php

use PHPUnit\Framework\TestCase;
use App\Services\Payroll\TaxCalculator;

class TaxCalculatorTest extends TestCase
{
    protected $pdo;
    protected $calculator;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create table
        $this->pdo->exec("
            CREATE TABLE payroll_tax_table (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                min_salary DECIMAL(18,2),
                max_salary DECIMAL(18,2),
                base_tax DECIMAL(18,2),
                excess_rate DECIMAL(5,4)
            )
        ");

        // Seed with sample data (2023-2027 TRAIN law brackets - Monthly)
        $this->pdo->exec("
            INSERT INTO payroll_tax_table (min_salary, max_salary, base_tax, excess_rate) VALUES
            (0, 20833, 0, 0),
            (20833.01, 33333, 0, 0.15),
            (33333.01, 66667, 1875, 0.20),
            (66667.01, 166667, 8541.67, 0.25),
            (166667.01, 666667, 33541.67, 0.30),
            (666667.01, 99999999, 183541.67, 0.35)
        ");

        $this->calculator = new TaxCalculator($this->pdo);
    }

    public function testCalculateLowIncome()
    {
        // 20,000 is below first bracket (20,833)
        $result = $this->calculator->calculate(20000);
        $this->assertEquals(0, $result);
    }

    public function testCalculateSecondBracket()
    {
        // 30,000
        // (30000 - 20833.01) * 0.15 = 1375.0485
        // Wait, bracket min in my insert is 20833.01
        // (30000 - 20833.01) * 0.15 = 1375.0485
        $result = $this->calculator->calculate(30000);
        $expected = (30000 - 20833.01) * 0.15;
        $this->assertEquals($expected, $result);
    }

    public function testCalculateThirdBracket()
    {
        // 50,000
        // 1875 + (50000 - 33333.01) * 0.20 = 1875 + 3333.398 = 5208.398
        $result = $this->calculator->calculate(50000);
        $expected = 1875 + (50000 - 33333.01) * 0.20;
        $this->assertEquals($expected, $result);
    }
}
