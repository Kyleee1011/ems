<?php

namespace App\Services\Payroll;

use PDO;

/**
 * Calculates the employee's Pag-IBIG (HDMF) contribution.
 */
class PagIbigCalculator
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Calculates the Pag-IBIG deduction.
     *
     * The legacy code retrieves a fixed amount from the database, falling
     * back to a hardcoded 200.00 if not found. This implementation
     * retains that behavior.
     *
     * Pag-IBIG rules are typically percentage-based with a cap, so this
     * is a simplified model.
     *
     * @param float $salary The employee's monthly salary (often not used in simplified models).
     * @return float The employee's share of the Pag-IBIG contribution.
     */
    public function calculate(float $salary): float
    {
        $stmt = $this->pdo->prepare("SELECT fixed_amt FROM payroll_pagibig_table LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return the configured fixed amount, or fallback to 200.00
        return $row ? (float)$row['fixed_amt'] : 200.00;
    }
}
