<?php

use PHPUnit\Framework\TestCase;
use App\Services\Payroll\SssCalculator;

class SssCalculatorTest extends TestCase
{
    protected $pdo;
    protected $calculator;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create table
        $this->pdo->exec("
            CREATE TABLE payroll_sss_table (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                min_salary DECIMAL(18,2),
                max_salary DECIMAL(18,2),
                ee_share DECIMAL(18,2),
                mpf DECIMAL(18,2)
            )
        ");

        // Seed with sample data (e.g., 2025 SSS Table)
        $this->pdo->exec("
            INSERT INTO payroll_sss_table (min_salary, max_salary, ee_share, mpf) VALUES
            (0, 4250.00, 180.00, 0),
            (4250.01, 4750.00, 202.50, 0),
            (20250.01, 20750.00, 900.00, 22.50),
            (29750.01, 9999999.99, 1350.00, 450.00)
        ");

        $this->calculator = new SssCalculator($this->pdo);
    }

    public function testCalculateLowSalary()
    {
        $result = $this->calculator->calculate(4000);
        $this->assertEquals(180.00, $result['ee_share']);
        $this->assertEquals(0, $result['mpf']);
    }

    public function testCalculateMidSalary()
    {
        $result = $this->calculator->calculate(20500);
        $this->assertEquals(900.00, $result['ee_share']);
        $this->assertEquals(22.50, $result['mpf']);
    }

    public function testCalculateHighSalary()
    {
        $result = $this->calculator->calculate(35000);
        $this->assertEquals(1350.00, $result['ee_share']);
        $this->assertEquals(450.00, $result['mpf']);
    }
}
