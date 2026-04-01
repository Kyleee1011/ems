<?php

namespace App\Services\Payroll;

use PDO;

/**
 * Calculates the employee's SSS contribution.
 */
class SssCalculator
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Calculates the SSS deduction based on the employee's salary.
     *
     * @param float $salary The employee's monthly salary.
     * @return array An associative array containing 'ee_share' and 'mpf'.
     */
    public function calculate(float $salary): array
    {
        // Select the row where salary falls between min and max
        $stmt = $this->pdo->prepare(
            "SELECT ee_share, mpf FROM payroll_sss_table WHERE ? BETWEEN min_salary AND max_salary LIMIT 1"
        );
        $stmt->execute([$salary]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return the deduction or 0.00 if not found
        return $row ? [
            'ee_share' => (float)$row['ee_share'],
            'mpf' => (float)$row['mpf']
        ] : [
            'ee_share' => 0.00,
            'mpf' => 0.00
        ];
    }
}
