<?php

namespace App\Services\Payroll;

use PDO;

/**
 * Calculates the employee's withholding tax.
 */
class TaxCalculator
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Calculates the withholding tax for a given taxable income.
     *
     * This uses a bracket system stored in the database, calculating:
     * Tax = Base Tax + ( (Taxable Income - Bracket Minimum) * Excess Rate )
     *
     * @param float $taxableIncome The employee's taxable income for the period.
     * @return float The calculated withholding tax.
     */
    public function calculate(float $taxableIncome): float
    {
        // 1. Find the matching tax bracket from the database
        $stmt = $this->pdo->prepare(
            "SELECT base_tax, excess_rate, min_salary FROM payroll_tax_table WHERE ? BETWEEN min_salary AND max_salary LIMIT 1"
        );
        $stmt->execute([$taxableIncome]);
        $bracket = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bracket) {
            // 2. Calculate the amount of income in excess of the bracket's minimum
            $excess = $taxableIncome - (float)$bracket['min_salary'];
            
            // Ensure excess is not negative
            if ($excess < 0) {
                $excess = 0;
            }

            // 3. Final Calculation: Base Tax + (Excess * Rate)
            $tax = (float)$bracket['base_tax'] + ($excess * (float)$bracket['excess_rate']);
            
            return $tax;
        }

        // Default to 0 if no bracket is found (e.g., for incomes below the first bracket)
        return 0.00;
    }

    /**
     * Calculates withholding tax using the semi-monthly tax table.
     * Uses direct semi-monthly brackets from BIR TRAIN law instead of dividing monthly tax by 2.
     *
     * @param float $semiMonthlyTaxableIncome The taxable income for the semi-monthly period.
     * @return float The semi-monthly withholding tax.
     */
    public function calculateSemiMonthly(float $semiMonthlyTaxableIncome): float
    {
        $this->ensureSemiMonthlyTable();

        $stmt = $this->pdo->prepare(
            "SELECT base_tax, excess_rate, min_salary 
             FROM payroll_tax_table_semimonthly 
             WHERE ? BETWEEN min_salary AND max_salary 
             LIMIT 1"
        );
        $stmt->execute([$semiMonthlyTaxableIncome]);
        $bracket = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bracket) {
            $excess = max(0, $semiMonthlyTaxableIncome - (float)$bracket['min_salary']);
            return (float)$bracket['base_tax'] + ($excess * (float)$bracket['excess_rate']);
        }

        return 0.00;
    }

    /**
     * Ensure the semi-monthly tax table exists and is seeded with BIR TRAIN law brackets.
     */
    private function ensureSemiMonthlyTable(): void
    {
        // Create table if not exists
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS payroll_tax_table_semimonthly (
                id INT AUTO_INCREMENT PRIMARY KEY,
                min_salary DECIMAL(18,2),
                max_salary DECIMAL(18,2),
                base_tax DECIMAL(18,2),
                excess_rate DECIMAL(5,4)
            )
        ");

        // Seed only if empty
        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM payroll_tax_table_semimonthly")->fetchColumn();
        if ($count === 0) {
            $this->pdo->exec("
                INSERT INTO payroll_tax_table_semimonthly (min_salary, max_salary, base_tax, excess_rate) VALUES
                (0.00, 10416.66, 0.00, 0.0000),
                (10416.67, 16666.66, 0.00, 0.1500),
                (16666.67, 33333.32, 937.50, 0.2000),
                (33333.33, 83333.32, 4270.83, 0.2500),
                (83333.33, 333333.32, 16770.83, 0.3000),
                (333333.33, 9999999.99, 91770.83, 0.3500)
            ");
        }
    }
}
