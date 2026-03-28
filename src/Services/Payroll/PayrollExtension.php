<?php

namespace App\Services\Payroll;

use PDO;
use DateTime;

/**
 * PayrollExtension - A consolidated reference of all payroll calculation formulas.
 * 
 * This file serves as a single point for analyzing the current payroll logic
 * and can be used to test new formulas without affecting the live system.
 */
class PayrollExtension
{
    // --- HOLIDAY TYPES & MULTIPLIERS ---
    public const DAY_TYPE_REGULAR_HOLIDAY = 'REGULAR';
    public const DAY_TYPE_SPECIAL_HOLIDAY = 'SPECIAL';
    public const DAY_TYPE_DOUBLE_HOLIDAY = 'DOUBLE';
    public const DAY_TYPE_ORDINARY = 'REGULAR_DAY';

    // Overtime Multipliers (Philippine Labor Code)
    private const OT_MULTIPLIER_ORDINARY = 1.25;         // +25%
    private const OT_MULTIPLIER_REGULAR_HOLIDAY = 2.60;  // 200% (Holiday) * 1.30 (OT) = 260%
    private const OT_MULTIPLIER_SPECIAL_HOLIDAY = 1.69;  // 130% (Holiday) * 1.30 (OT) = 169%
    private const OT_MULTIPLIER_DOUBLE_HOLIDAY = 3.90;   // 300% (Holiday) * 1.30 (OT) = 390%

    // Night Differential Rate (10pm - 6am)
    private const NIGHT_DIFF_RATE = 0.10; // 10% premium

    /**
     * 1. HOLIDAY CALCULATION
     * Returns the holiday type for a specific date from the 'holidays' table.
     */
    public function getDayType(string $dateStr, PDO $pdo): string
    {
        $stmt = $pdo->prepare("SELECT holiday_type FROM scheduler_holidays WHERE holiday_date = ?");
        $stmt->execute([$dateStr]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($row && !empty($row['holiday_type'])) ? $row['holiday_type'] : self::DAY_TYPE_ORDINARY;
    }

    /**
     * 2. OVERTIME CALCULATION
     * Formula: OT Pay = OT Hours * Hourly Rate * Multiplier
     */
    public function calculateOvertime(float $otHours, float $hourlyRate, string $dayType): float
    {
        if ($otHours <= 0 || $hourlyRate <= 0) return 0;

        $multiplier = self::OT_MULTIPLIER_ORDINARY;
        switch ($dayType) {
            case self::DAY_TYPE_REGULAR_HOLIDAY: $multiplier = self::OT_MULTIPLIER_REGULAR_HOLIDAY; break;
            case self::DAY_TYPE_SPECIAL_HOLIDAY: $multiplier = self::OT_MULTIPLIER_SPECIAL_HOLIDAY; break;
            case self::DAY_TYPE_DOUBLE_HOLIDAY:  $multiplier = self::OT_MULTIPLIER_DOUBLE_HOLIDAY; break;
        }

        return $otHours * $hourlyRate * $multiplier;
    }

    /**
     * 3. NIGHT DIFFERENTIAL CALCULATION
     * Formula: Night Diff = Overlap Hours (10PM-6AM) * Hourly Rate * 10%
     */
    public function calculateNightDiff(?DateTime $timeIn, ?DateTime $timeOut, float $hourlyRate): float
    {
        if (!$timeIn || !$timeOut || $hourlyRate <= 0) return 0;

        $workStartTs = $timeIn->getTimestamp();
        $workEndTs = $timeOut->getTimestamp();
        if ($workStartTs >= $workEndTs) return 0;

        $totalNightHours = 0;
        $checkDate = clone $timeIn;

        // Handles shifts crossing midnight (checks windows for previous, current, and next day)
        for ($i = -1; $i <= 1; $i++) {
            $nightWindowStart = (clone $checkDate)->modify("$i day")->setTime(22, 0, 0);
            $nightWindowEnd = (clone $checkDate)->modify(($i + 1) . " day")->setTime(6, 0, 0);

            $overlapSeconds = max(0, min($workEndTs, $nightWindowEnd->getTimestamp()) - max($workStartTs, $nightWindowStart->getTimestamp()));
            if ($overlapSeconds > 0) {
                $totalNightHours += ($overlapSeconds / 3600);
            }
        }

        return $totalNightHours * $hourlyRate * self::NIGHT_DIFF_RATE;
    }

    /**
     * 4. SSS CONTRIBUTION
     * Formula: Look up 'ee_share' from 'payroll_sss_table' based on Salary Bracket.
     */
    public function calculateSss(float $monthlySalary, PDO $pdo): float
    {
        $stmt = $pdo->prepare("SELECT ee_share FROM payroll_sss_table WHERE ? BETWEEN min_salary AND max_salary LIMIT 1");
        $stmt->execute([$monthlySalary]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (float)$row['ee_share'] : 0.00;
    }

    /**
     * 5. PHILHEALTH CONTRIBUTION
     * Formula: Deduction = Monthly Salary * Rate (capped at max_salary)
     */
    public function calculatePhilHealth(float $monthlySalary, PDO $pdo): float
    {
        $stmt = $pdo->prepare("SELECT rate, min_salary, max_salary FROM payroll_philhealth_table LIMIT 1");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config) return 0.00;

        $computationalSalary = min($monthlySalary, (float)$config['max_salary']);
        return $computationalSalary * (float)$config['rate'];
    }

    /**
     * 6. PAG-IBIG (HDMF) CONTRIBUTION
     * Formula: Fixed Amount from 'payroll_pagibig_table' (Default: 200.00)
     */
    public function calculatePagIbig(float $monthlySalary, PDO $pdo): float
    {
        $stmt = $pdo->query("SELECT fixed_amt FROM payroll_pagibig_table LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (float)$row['fixed_amt'] : 200.00;
    }

    /**
     * 7. WITHHOLDING TAX CALCULATION
     * Formula: Tax = Base Tax + ((Taxable Income - Bracket Minimum) * Excess Rate)
     */
    public function calculateWithholdingTax(float $taxableIncome, PDO $pdo): float
    {
        $stmt = $pdo->prepare("SELECT base_tax, excess_rate, min_salary FROM payroll_tax_table WHERE ? BETWEEN min_salary AND max_salary LIMIT 1");
        $stmt->execute([$taxableIncome]);
        $bracket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bracket) return 0.00;

        $excess = max(0, $taxableIncome - (float)$bracket['min_salary']);
        return (float)$bracket['base_tax'] + ($excess * (float)$bracket['excess_rate']);
    }

    /**
     * 8. LATE DEDUCTION
     * Formula: Deduction = Minutes Late * (Hourly Rate / 60)
     */
    public function calculateLate(int $lateMins, float $hourlyRate): float
    {
        if ($lateMins <= 0 || $hourlyRate <= 0) return 0;
        return $lateMins * ($hourlyRate / 60);
    }

    /**
     * 9. UNDERTIME DEDUCTION
     * Formula: Deduction = Minutes Undertime * (Hourly Rate / 60)
     */
    public function calculateUndertime(?DateTime $schedOut, ?DateTime $actualOut, float $hourlyRate): float
    {
        if (!$schedOut || !$actualOut || $hourlyRate <= 0) return 0;

        $scheduledTs = $schedOut->getTimestamp();
        $actualTs = $actualOut->getTimestamp();

        if ($actualTs < $scheduledTs) {
            $undertimeMinutes = (int)floor(($scheduledTs - $actualTs) / 60);
            return $undertimeMinutes * ($hourlyRate / 60);
        }

        return 0;
    }

    /**
     * 10. BASIC PAY & HOLIDAY PAY LOGIC
     * 
     * Worked Day: 
     *   Pay = Daily Rate
     *   Regular Holiday: +100% Regular Pay
     *   Special Holiday: +30% Regular Pay
     *   Double Holiday:  +200% Regular Pay
     * 
     * Absent Day:
     *   Regular Holiday: Pay 100% (if not worked but scheduled day)
     *   Otherwise: No Pay
     */
}
