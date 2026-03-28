<?php

namespace App\Services\Payroll;

use PDO;

/**
 * Calculates the employee's PhilHealth contribution.
 */
class PhilHealthCalculator
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Calculates the PhilHealth deduction based on the employee's salary.
     * 
     * Per PhilHealth Circular No. 2024-001:
     * - Premium Rate: 5% (shared 50/50 between employer and employee)
     * - Salary Floor: ₱10,000 (minimum contribution base)
     * - Salary Ceiling: ₱100,000 (maximum contribution base)
     *
     * @param float $salary The employee's monthly salary.
     * @return float The employee's share of the PhilHealth contribution (50% of total).
     */
    public function calculate(float $salary): float
    {
        $stmt = $this->pdo->prepare("SELECT rate, min_salary, max_salary FROM payroll_philhealth_table LIMIT 1");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            return 0.00;
        }

        $rate = (float)$config['rate'];
        $minSalary = (float)$config['min_salary'];
        $maxSalary = (float)$config['max_salary'];

        // Apply salary floor and ceiling for computation
        $computationalSalary = $salary;
        if ($salary < $minSalary) {
            $computationalSalary = $minSalary;
        } elseif ($salary > $maxSalary) {
            $computationalSalary = $maxSalary;
        }

        // Total Contribution = Capped Salary × Rate (e.g., 5%)
        $totalContribution = $computationalSalary * $rate;

        // Employee Share = 50% of Total Contribution
        $employeeShare = $totalContribution / 2;

        return round($employeeShare, 2);
    }
}
