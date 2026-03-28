# Philippine Payroll System - Complete Formula Reference

> **Version:** 1.1  
> **Last Updated:** February 2026  
> **Legal Basis:** Philippine Labor Code, BIR, SSS, PhilHealth, Pag-IBIG

This document contains all payroll calculation formulas used in the EMS system. You may manually adjust the values in the database tables referenced below.

---

## Table of Contents

1. [Basic Pay Calculation](#1-basic-pay-calculation)
2. [SSS Contribution](#2-sss-contribution)
3. [PhilHealth Contribution](#3-philhealth-contribution)
4. [Pag-IBIG Contribution](#4-pag-ibig-contribution)
5. [Withholding Tax](#5-withholding-tax)
6. [Holiday Pay](#6-holiday-pay)
7. [Night Differential](#7-night-differential)
8. [Overtime Pay](#8-overtime-pay)
9. [Late Deduction](#9-late-deduction)
10. [Undertime Deduction](#10-undertime-deduction)
11. [Multi-Day Shift Calculation](#11-multi-day-shift-calculation)

---

## 1. Basic Pay Calculation

### Summary

Calculates the employee's base pay from their monthly, daily, or hourly rate. A **1-hour unpaid break** is deducted for shifts exceeding 5 hours.

### Formulas

```
Daily Rate   = Monthly Salary ÷ (313 ÷ 12)  [~26.0833 working days]
Hourly Rate  = Daily Rate ÷ 8 hours
Minute Rate  = Hourly Rate ÷ 60

Paid Hours   = Worked Hours - 1 (if Worked Hours > 5)
Basic Pay    = Paid Hours × Hourly Rate
```

### Example

| Monthly Salary | Daily Rate | Hourly Rate | 9hr Shift (8 paid) |
| -------------- | ---------- | ----------- | ------------------ |
| ₱18,000        | ₱690.10    | ₱86.26      | ₱690.10            |
| ₱25,000        | ₱958.47    | ₱119.81     | ₱958.47            |

### Database Configuration

- Table: `employees`
- Columns: `salary_rate`, `daily_rate`, `hourly_rate`

---

## 2. SSS Contribution

### Summary

Social Security System contribution based on salary brackets. The employee's share is deducted from salary; the employer also contributes.

### Formula

```
SSS Deduction = Lookup employee share (ee_share) from bracket table based on monthly salary
```

### Lookup Logic

```
SELECT ee_share
FROM payroll_sss_table
WHERE monthly_salary BETWEEN min_salary AND max_salary
```

### Database Configuration

- Table: `payroll_sss_table`
- Columns:
  - `min_salary` - Minimum salary for bracket
  - `max_salary` - Maximum salary for bracket
  - `ee_share` - Employee's contribution amount

### Example (2025 Table)

| Min Salary | Max Salary | Employee Share |
| ---------- | ---------- | -------------- |
| 0          | 4,999.99   | ₱225.00        |
| 5,000.00   | 5,999.99   | ₱275.00        |
| 6,000.00   | 6,999.99   | ₱325.00        |
| 20,000.00  | 24,999.99  | ₱1,150.00      |
| 30,000.00  | 34,999.99  | ₱1,600.00      |

> **Note:** SSS updates contribution tables periodically. Update `payroll_sss_table` with the latest official SSS schedule.

### Code Location

### Implementation Code

**File:** `src/Services/Payroll/SssCalculator.php`

```php
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
     * @return float The employee's share of the SSS contribution.
     */
    public function calculate(float $salary): float
    {
        // Select the row where salary falls between min and max
        $stmt = $this->pdo->prepare(
            "SELECT ee_share FROM payroll_sss_table WHERE ? BETWEEN min_salary AND max_salary LIMIT 1"
        );
        $stmt->execute([$salary]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return the deduction or 0.00 if not found
        return $row ? (float)$row['ee_share'] : 0.00;
    }
}
```

---

## 3. PhilHealth Contribution

### Summary

Philippine Health Insurance contribution calculated as a percentage of monthly salary, split 50/50 between employer and employee.

### Formula

```
Total Contribution = MIN(Monthly Salary, Salary Cap) × Rate
Employee Share = Total Contribution × 0.50
```

### Current Parameters (2025)

| Parameter  | Value        | Description               |
| ---------- | ------------ | ------------------------- |
| Rate       | 5.00% (0.05) | Total contribution rate   |
| Employee % | 50%          | Employee portion of total |
| Min Salary | ₱10,000.00   | Floor for computation     |
| Max Salary | ₱100,000.00  | Ceiling for computation   |

### Example

| Monthly Salary | Computation Basis | Total (5%) | Employee Share |
| -------------- | ----------------- | ---------- | -------------- |
| ₱15,000        | ₱15,000           | ₱750.00    | ₱375.00        |
| ₱50,000        | ₱50,000           | ₱2,500.00  | ₱1,250.00      |
| ₱150,000       | ₱100,000 (capped) | ₱5,000.00  | ₱2,500.00      |

### Database Configuration

- Table: `payroll_philhealth_table`
- Columns: `rate`, `min_salary`, `max_salary`

### Code Location

### Implementation Code

**File:** `src/Services/Payroll/PhilHealthCalculator.php`

```php
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
```

---

## 4. Pag-IBIG Contribution

### Summary

Home Development Mutual Fund (HDMF) contribution. Typically a fixed monthly amount for employee share.

### Formula

```
Pag-IBIG Deduction = Fixed Amount (default: ₱200.00)
```

### Official Rate (For Reference)

| Monthly Salary | Employee Rate | Employer Rate |
| -------------- | ------------- | ------------- |
| ≤ ₱1,500       | 1%            | 2%            |
| > ₱1,500       | 2%            | 2%            |
| **Maximum**    | ₱200.00       | ₱200.00       |

> **Note:** The system uses a simplified fixed amount model. Most employees earning above ₱10,000 will have ₱200 as their maximum contribution.

### Database Configuration

- Table: `payroll_pagibig_table`
- Column: `fixed_amt` (default: 200.00)

### Code Location

### Implementation Code

**File:** `src/Services/Payroll/PagIbigCalculator.php`

```php
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
```

---

## 5. Withholding Tax

### Summary

Bureau of Internal Revenue (BIR) withholding tax calculated using progressive tax brackets (TRAIN Law).

### Formula

```
Tax = Base Tax + ((Taxable Income - Bracket Minimum) × Excess Rate)
```

Where:

- `Taxable Income` = Gross Pay - SSS - PhilHealth - Pag-IBIG

### Tax Brackets (TRAIN Law - Monthly)

| Bracket | Min Salary  | Max Salary  | Base Tax    | Excess Rate |
| ------- | ----------- | ----------- | ----------- | ----------- |
| 1       | ₱0          | ₱20,833.32  | ₱0          | 0%          |
| 2       | ₱20,833.33  | ₱33,333.32  | ₱0          | 15%         |
| 3       | ₱33,333.33  | ₱66,666.66  | ₱1,875.00   | 20%         |
| 4       | ₱66,666.67  | ₱166,666.66 | ₱8,541.67   | 25%         |
| 5       | ₱166,666.67 | ₱666,666.66 | ₱33,541.67  | 30%         |
| 6       | ₱666,666.67 | ∞           | ₱183,541.67 | 35%         |

### Example Calculation

```
Taxable Income: ₱40,000
Bracket: 3 (₱33,333.33 - ₱66,666.66)
Tax = ₱1,875.00 + ((₱40,000 - ₱33,333.33) × 0.20)
Tax = ₱1,875.00 + (₱6,666.67 × 0.20)
Tax = ₱1,875.00 + ₱1,333.33
Tax = ₱3,208.33
```

### Database Configuration

- Table: `payroll_tax_table`
- Columns: `min_salary`, `max_salary`, `base_tax`, `excess_rate`

### Code Location

### Implementation Code

**File:** `src/Services/Payroll/TaxCalculator.php`

```php
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
}
```

---

## 6. Holiday Pay

### Summary

Premium pay rates for work performed on holidays as mandated by the Philippine Labor Code.

### Holiday Types & Multipliers

| Day Type                    | Code           | Rate | Description        |
| --------------------------- | -------------- | ---- | ------------------ |
| Regular Day                 | `REGULAR_DAY`  | 100% | Normal working day |
| Rest Day                    | `REST_DAY`     | 130% | +30% premium       |
| Special Non-Working Holiday | `SPECIAL`      | 130% | +30% premium       |
| Special Holiday + Rest Day  | `SPECIAL_REST` | 150% | +50% premium       |
| Regular Holiday             | `REGULAR`      | 200% | +100% premium      |
| Regular Holiday + Rest Day  | `REGULAR_REST` | 260% | +160% premium      |
| Double Holiday              | `DOUBLE`       | 300% | +200% premium      |

### Formula

```
Holiday Pay = Daily Rate × Holiday Multiplier
```

### Example

| Day Type        | Daily Rate | Multiplier | Pay       |
| --------------- | ---------- | ---------- | --------- |
| Regular Day     | ₱909.09    | 1.00       | ₱909.09   |
| Special Holiday | ₱909.09    | 1.30       | ₱1,181.82 |
| Regular Holiday | ₱909.09    | 2.00       | ₱1,818.18 |

### Database Configuration

- Table: `holidays`
- Columns: `holiday_date`, `holiday_type`, `holiday_name`

### Code Location

### Implementation Code

**File:** `src/Services/Payroll/HolidayCalculator.php`

```php
<?php

namespace App\Services\Payroll;

use PDO;

/**
 * Determines the type of day (e.g., holiday, regular day).
 */
class HolidayCalculator
{
    // Constants for Holiday Types to avoid magic strings
    public const DAY_TYPE_REGULAR_HOLIDAY = 'REGULAR';
    public const DAY_TYPE_SPECIAL_HOLIDAY = 'SPECIAL';
    public const DAY_TYPE_DOUBLE_HOLIDAY = 'DOUBLE';
    public const DAY_TYPE_ORDINARY = 'REGULAR_DAY';

    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Checks the database to determine if a given date is a holiday.
     *
     * @param string $dateStr The date to check in 'Y-m-d' format.
     * @return string The type of day (e.g., 'REGULAR', 'SPECIAL', 'REGULAR_DAY').
     */
    public function getDayType(string $dateStr): string
    {
        // Check database for a holiday entry
        $stmt = $this->pdo->prepare(
            "SELECT holiday_type FROM holidays WHERE holiday_date = ?"
        );
        $stmt->execute([$dateStr]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['holiday_type'])) {
            return $row['holiday_type'];
        }

        return self::DAY_TYPE_ORDINARY;
    }
}
```

---

## 7. Night Differential

### Summary

Additional 10% pay for work performed between 10:00 PM and 6:00 AM.

### Formula

```
Night Diff Pay = Night Hours × Hourly Rate × 10%
```

### Night Differential Window

- **Start:** 10:00 PM (22:00)
- **End:** 6:00 AM (06:00) next day
- **Duration:** 8 hours maximum per night

### Calculation Logic

1. Determine overlap between work hours and night window
2. Calculate night hours from overlap
3. Apply 10% premium to base pay

### Example

| Shift      | Night Hours | Hourly Rate | Night Diff Pay |
| ---------- | ----------- | ----------- | -------------- |
| 6PM - 3AM  | 5 hours     | ₱113.64     | ₱56.82         |
| 10PM - 6AM | 8 hours     | ₱113.64     | ₱90.91         |
| 8AM - 5PM  | 0 hours     | ₱113.64     | ₱0.00          |

### Code Location

### Implementation Code

**File:** `src/Services/Payroll/NightDiffCalculator.php`

```php
<?php

namespace App\Services\Payroll;

use DateTime;
use DateTimeZone;

/**
 * Calculates employee night differential pay.
 */
class NightDiffCalculator
{
    private const NIGHT_DIFF_RATE = 0.10;

    /**
     * Calculates the night differential pay for a given time-in and time-out.
     *
     * Night differential is a 10% premium for hours worked between 10:00 PM and 6:00 AM.
     * This method calculates the number of hours an employee worked during this
     * window and computes the premium.
     *
     * @param DateTime|null $timeIn The employee's time-in.
     * @param DateTime|null $timeOut The employee's time-out.
     * @param float $hourlyRate The employee's hourly rate.
     * @return float The total night differential pay.
     */
    public function calculate(?DateTime $timeIn, ?DateTime $timeOut, float $hourlyRate): float
    {
        if (!$timeIn || !$timeOut || $hourlyRate <= 0) {
            return 0;
        }

        $workStartTs = $timeIn->getTimestamp();
        $workEndTs = $timeOut->getTimestamp();

        if ($workStartTs >= $workEndTs) {
            return 0;
        }

        $totalNightHours = 0;

        // The legacy code creates three windows (previous day, current day, next day)
        // to handle shifts that cross midnight. This is a robust way to capture all overlap.
        $checkDate = new DateTime("@{$workStartTs}");
        $checkDate->setTimezone($timeIn->getTimezone());

        for ($i = -1; $i <= 1; $i++) {
            // Define the night differential window for a given day.
            // Window starts at 10 PM of the reference day.
            $nightWindowStart = clone $checkDate;
            $nightWindowStart->modify("$i day")->setTime(22, 0, 0);

            // Window ends at 6 AM of the *next* day.
            $nightWindowEnd = clone $checkDate;
            $nightWindowEnd->modify(($i + 1) . " day")->setTime(6, 0, 0);

            $nightWindowStartTs = $nightWindowStart->getTimestamp();
            $nightWindowEndTs = $nightWindowEnd->getTimestamp();

            // Calculate the overlap in seconds between the work shift and the night window.
            $overlapSeconds = max(0, min($workEndTs, $nightWindowEndTs) - max($workStartTs, $nightWindowStartTs));

            if ($overlapSeconds > 0) {
                $totalNightHours += ($overlapSeconds / 3600);
            }
        }

        if ($totalNightHours > 0) {
            return $totalNightHours * $hourlyRate * self::NIGHT_DIFF_RATE;
        }

        return 0;
    }
}
```

---

## 8. Overtime Pay

### Summary

Premium pay for hours worked beyond 8 regular hours per day.

### Formula

```
OT Pay = OT Hours × Hourly Rate × OT Multiplier
```

### Overtime Multipliers

| Day Type                    | Base Rate | OT Multiplier | Total Rate |
| --------------------------- | --------- | ------------- | ---------- |
| Regular Day                 | 100%      | 1.25          | 125%       |
| Rest Day                    | 130%      | 1.30          | 169%       |
| Special Non-Working Holiday | 130%      | 1.30          | 169%       |
| Regular Holiday             | 200%      | 1.30          | 260%       |
| Double Holiday              | 300%      | 1.30          | 390%       |

### Calculation

```
Regular Day OT:     OT Hours × Hourly Rate × 1.25
Special Holiday OT: OT Hours × Hourly Rate × 1.69
Regular Holiday OT: OT Hours × Hourly Rate × 2.60
Double Holiday OT:  OT Hours × Hourly Rate × 3.90
```

### Example

| Day Type        | OT Hours | Hourly Rate | Multiplier | OT Pay  |
| --------------- | -------- | ----------- | ---------- | ------- |
| Regular Day     | 2        | ₱113.64     | 1.25       | ₱284.10 |
| Regular Holiday | 2        | ₱113.64     | 2.60       | ₱590.93 |

### Code Location

### Implementation Code

**File:** `src/Services/Payroll/OvertimeCalculator.php`

```php
<?php

namespace App\Services\Payroll;

/**
 * Calculates employee overtime pay based on Philippine labor code multipliers.
 */
class OvertimeCalculator
{
    // Constants for Holiday Types to avoid magic strings
    public const DAY_TYPE_REGULAR = 'REGULAR';
    public const DAY_TYPE_SPECIAL = 'SPECIAL';
    public const DAY_TYPE_DOUBLE = 'DOUBLE';
    public const DAY_TYPE_ORDINARY = 'ORDINARY_DAY'; // Or any other default value

    // Multipliers based on Philippine Labor Code
    private const MULTIPLIER_ORDINARY = 1.25;         // +25% for ordinary day OT
    private const MULTIPLIER_REGULAR_HOLIDAY = 2.60;  // 200% (holiday pay) * 1.30 (OT premium) = 260%
    private const MULTIPLIER_SPECIAL_HOLIDAY = 1.69;  // 130% (holiday pay) * 1.30 (OT premium) = 169%
    private const MULTIPLIER_DOUBLE_HOLIDAY = 3.90;   // 300% (holiday pay) * 1.30 (OT premium) = 390%

    /**
     * Calculates the overtime pay.
     *
     * @param float $otHours The number of overtime hours worked.
     * @param float $hourlyRate The employee's regular hourly rate.
     * @param string $dayType The type of day (e.g., 'REGULAR', 'SPECIAL', 'ORDINARY_DAY').
     * @return float The calculated overtime pay.
     */
    public function calculate(float $otHours, float $hourlyRate, string $dayType): float
    {
        if ($otHours <= 0 || $hourlyRate <= 0) {
            return 0;
        }

        $multiplier = self::MULTIPLIER_ORDINARY;

        switch ($dayType) {
            case self::DAY_TYPE_REGULAR:
                $multiplier = self::MULTIPLIER_REGULAR_HOLIDAY;
                break;
            case self::DAY_TYPE_SPECIAL:
                $multiplier = self::MULTIPLIER_SPECIAL_HOLIDAY;
                break;
            case self::DAY_TYPE_DOUBLE:
                $multiplier = self::MULTIPLIER_DOUBLE_HOLIDAY;
                break;
        }

        return $otHours * $hourlyRate * $multiplier;
    }
}
```

---

## 9. Late Deduction

### Summary

Deduction from salary for arriving late to work.

### Formula

```
Late Deduction = Late Minutes × (Hourly Rate ÷ 60)
              = Late Minutes × Minute Rate
```

### Example

| Late Minutes | Hourly Rate | Deduction |
| ------------ | ----------- | --------- |
| 15           | ₱113.64     | ₱28.41    |
| 30           | ₱113.64     | ₱56.82    |
| 60           | ₱113.64     | ₱113.64   |

### Grace Period

- Many companies allow a grace period (e.g., 10-15 minutes)
- Configure this in company policy settings

### Code Location

### Implementation Code

**File:** `src/Services/Payroll/PayrollExtension.php`

```php
    /**
     * 8. LATE DEDUCTION
     * Formula: Deduction = Minutes Late * (Hourly Rate / 60)
     */
    public function calculateLate(int $lateMins, float $hourlyRate): float
    {
        if ($lateMins <= 0 || $hourlyRate <= 0) return 0;
        return $lateMins * ($hourlyRate / 60);
    }
```

---

## 10. Undertime Deduction

### Summary

Deduction for leaving work before the scheduled end time.

### Formula

```
Undertime Minutes = (Scheduled Out - Actual Out) in minutes
Undertime Deduction = Undertime Minutes × (Hourly Rate ÷ 60)
```

### Example

| Scheduled Out | Actual Out | Undertime | Hourly Rate | Deduction |
| ------------- | ---------- | --------- | ----------- | --------- |
| 5:00 PM       | 4:30 PM    | 30 mins   | ₱113.64     | ₱56.82    |
| 5:00 PM       | 3:00 PM    | 120 mins  | ₱113.64     | ₱227.28   |

### Code Location

### Implementation Code

**File:** `src/Services/Payroll/UndertimeCalculator.php`

```php
<?php

namespace App\Services\Payroll;

use DateTime;

/**
 * Calculates deductions for undertime.
 */
class UndertimeCalculator
{
    /**
     * Calculates the deduction amount for working less than the scheduled hours.
     *
     * @param DateTime|null $scheduledOut The employee's scheduled departure time.
     * @param DateTime|null $actualOut The employee's actual departure time.
     * @param float $hourlyRate The employee's hourly rate.
     * @return float The total deduction amount for undertime.
     */
    public function calculateDeduction(?DateTime $scheduledOut, ?DateTime $actualOut, float $hourlyRate): float
    {
        if (!$scheduledOut || !$actualOut || $hourlyRate <= 0) {
            return 0;
        }

        $undertimeMinutes = $this->calculateMinutes($scheduledOut, $actualOut);

        if ($undertimeMinutes > 0) {
            $minuteRate = $hourlyRate / 60;
            return $undertimeMinutes * $minuteRate;
        }

        return 0.00;
    }

    /**
     * Calculates the number of minutes an employee was undertime.
     *
     * @param DateTime $scheduledOut The employee's scheduled departure time.
     * @param DateTime $actualOut The employee's actual departure time.
     * @return int The number of undertime minutes.
     */
    public function calculateMinutes(DateTime $scheduledOut, DateTime $actualOut): int
    {
        $scheduledTs = $scheduledOut->getTimestamp();
        $actualTs = $actualOut->getTimestamp();

        // If logged out earlier than scheduled
        if ($actualTs < $scheduledTs) {
            $seconds = $scheduledTs - $actualTs;
            return (int)floor($seconds / 60);
        }

        return 0;
    }
}
```

---

## 11. Multi-Day Shift Calculation

### Summary

Handles shifts that span multiple days with different holiday types (e.g., 6PM Dec 24 to 3AM Dec 25).

### Problem Statement

When an employee works a shift like **6PM Dec 24 (Special Holiday) to 3AM Dec 25 (Regular Holiday)**:

- Hours 6PM-12AM belong to Dec 24 → Special Holiday rate (130%)
- Hours 12AM-3AM belong to Dec 25 → Regular Holiday rate (200%)
- Night differential applies differently based on each segment

### Algorithm

1. **Split Shift into Daily Segments**
   - Segment 1: 6:00 PM Dec 24 → 11:59:59 PM Dec 24
   - Segment 2: 12:00 AM Dec 25 → 3:00 AM Dec 25

2. **For Each Segment:**
   - Determine holiday type for that date
   - Calculate regular hours and night hours
   - Apply appropriate holiday multiplier
   - Add night differential premium

3. **Apply Break Deduction Proportionally**
   - Distribute break hours across segments based on duration ratio

### Calculation Formula per Segment

```
Segment Pay = (Regular Hours × Hourly Rate × Holiday Multiplier)
            + (Night Hours × Hourly Rate × Holiday Multiplier × 1.10)
```

### Example: 6PM Dec 24 to 3AM Dec 25 (₱20,000/month = ₱113.64/hr)

**Segment 1: Dec 24 (Special Holiday - 130%)**
| Component | Hours | Rate | Multiplier | Amount |
|----------------|-------|---------|------------|-----------|
| Regular | 4.00 | ₱113.64 | 1.30 | ₱590.93 |
| Night (10PM+) | 2.00 | ₱113.64 | 1.43\* | ₱324.91 |
| **Subtotal** | 6.00 | | | ₱915.84 |

\*Night: 1.30 × 1.10 = 1.43

**Segment 2: Dec 25 (Regular Holiday - 200%)**
| Component | Hours | Rate | Multiplier | Amount |
|----------------|-------|---------|------------|-----------|
| Night (12-3AM) | 3.00 | ₱113.64 | 2.20\* | ₱750.02 |
| **Subtotal** | 3.00 | | | ₱750.02 |

\*Night: 2.00 × 1.10 = 2.20

**Total Before Break:** ₱1,665.86  
**Less: 1-hour break (proportional):** ₱185.10  
**Final Pay:** ₱1,480.76

### Code Location

### Implementation Code

**File:** `src/Services/Payroll/ShiftPayrollCalculator.php`

```php
<?php

namespace App\Services\Payroll;

use PDO;
use DateTime;

/**
 * ShiftPayrollCalculator - Handles shifts spanning multiple days with different holiday types
 */
class ShiftPayrollCalculator
{
    // Holiday rate multipliers (Philippine Labor Code)
    private const HOLIDAY_RATES = [
        'REGULAR_DAY' => 1.0,      // Regular working day (100%)
        'REST_DAY'    => 1.3,      // Rest day (130%)
        'SPECIAL'     => 1.3,      // Special non-working holiday (130%)
        'REGULAR'     => 2.0,      // Regular holiday (200%)
        'DOUBLE'      => 3.0,      // Double holiday (300%)
    ];

    // Night differential rate (10PM - 6AM)
    private const NIGHT_DIFF_RATE = 0.10; // 10% premium

    protected PDO $pdo;
    protected HolidayCalculator $holidayCalculator;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->holidayCalculator = new HolidayCalculator($pdo);
    }

    /**
     * Calculate pay for a shift that may span multiple days/holidays
     */
    public function calculateShiftPay(
        float $hourlyRate,
        DateTime $shiftStart,
        DateTime $shiftEnd,
        float $breakHours = 1.0
    ): array {
        // Calculate total shift duration
        $totalShiftSeconds = $shiftEnd->getTimestamp() - $shiftStart->getTimestamp();
        $totalShiftHours = $totalShiftSeconds / 3600;

        if ($totalShiftHours <= 0) {
            return $this->emptyResult($breakHours);
        }

        // Split shift into daily segments
        $segments = $this->splitShiftIntoSegments($shiftStart, $shiftEnd, $hourlyRate);

        // Calculate totals before break deduction
        $totals = $this->calculateTotals($segments);

        // Apply break deduction proportionally across segments
        if ($breakHours > 0 && $totalShiftHours > 0) {
            $segments = $this->applyBreakDeduction($segments, $breakHours, $totalShiftHours);
            $totals = $this->calculateTotals($segments);
        }

        return [
            'segments'            => $segments,
            'total_pay'           => round($totals['pay'], 2),
            'total_hours'         => round($totals['hours'], 2),
            'total_regular_hours' => round($totals['regular_hours'], 2),
            'total_night_hours'   => round($totals['night_hours'], 2),
            'break_hours'         => $breakHours,
            'hourly_rate'         => $hourlyRate,
            'shift_start'         => $shiftStart->format('Y-m-d H:i:s'),
            'shift_end'           => $shiftEnd->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Split a shift into segments at midnight boundaries
     * Each segment belongs to a single date with its own holiday type
     */
    private function splitShiftIntoSegments(
        DateTime $startDateTime,
        DateTime $endDateTime,
        float $hourlyRate
    ): array {
        $segments = [];
        $currentTime = clone $startDateTime;

        while ($currentTime < $endDateTime) {
            // End of current day (11:59:59 PM)
            $endOfDay = clone $currentTime;
            $endOfDay->setTime(23, 59, 59);

            // Segment ends at either end of day or shift end
            $segmentEnd = ($endDateTime < $endOfDay) ? clone $endDateTime : $endOfDay;

            // Get date for this segment
            $segmentDate = $currentTime->format('Y-m-d');

            // Determine holiday type from database
            $holidayType = $this->holidayCalculator->getDayType($segmentDate);

            // Calculate hours for this segment
            $segmentSeconds = $segmentEnd->getTimestamp() - $currentTime->getTimestamp();
            $hours = $segmentSeconds / 3600;

            // Calculate night differential hours (10PM - 6AM)
            $nightHours = $this->calculateNightHours($currentTime, $segmentEnd);
            $regularHours = $hours - $nightHours;

            // Get holiday rate multiplier
            $holidayRate = self::HOLIDAY_RATES[$holidayType] ?? 1.0;

            // Calculate pay for this segment
            $regularPay = $regularHours * $hourlyRate * $holidayRate;
            $nightPay = $nightHours * $hourlyRate * $holidayRate * (1 + self::NIGHT_DIFF_RATE);
            $totalSegmentPay = $regularPay + $nightPay;

            $segments[] = [
                'start'         => $currentTime->format('Y-m-d H:i:s'),
                'end'           => $segmentEnd->format('Y-m-d H:i:s'),
                'date'          => $segmentDate,
                'holiday_type'  => $holidayType,
                'holiday_rate'  => $holidayRate,
                'hours'         => round($hours, 4),
                'regular_hours' => round($regularHours, 4),
                'night_hours'   => round($nightHours, 4),
                'regular_pay'   => round($regularPay, 2),
                'night_pay'     => round($nightPay, 2),
                'pay'           => round($totalSegmentPay, 2),
            ];

            // Move to start of next day
            $currentTime = clone $segmentEnd;
            $currentTime->modify('+1 second');
        }

        return $segments;
    }

    /**
     * Calculate hours that fall within night differential window (10PM - 6AM)
     */
    private function calculateNightHours(DateTime $start, DateTime $end): float
    {
        $nightHours = 0;
        $workStartTs = $start->getTimestamp();
        $workEndTs = $end->getTimestamp();

        // Check two possible night windows that could overlap:
        // 1. Previous day 10PM to current day 6AM
        // 2. Current day 10PM to next day 6AM

        for ($dayOffset = -1; $dayOffset <= 0; $dayOffset++) {
            // Night window start: 10PM of (current + offset) day
            $nightWindowStart = clone $start;
            $nightWindowStart->modify("{$dayOffset} day")->setTime(22, 0, 0);

            // Night window end: 6AM of next day
            $nightWindowEnd = clone $nightWindowStart;
            $nightWindowEnd->modify('+1 day')->setTime(6, 0, 0);

            $nightStartTs = $nightWindowStart->getTimestamp();
            $nightEndTs = $nightWindowEnd->getTimestamp();

            // Calculate overlap
            $overlapStart = max($workStartTs, $nightStartTs);
            $overlapEnd = min($workEndTs, $nightEndTs);

            if ($overlapEnd > $overlapStart) {
                $nightHours += ($overlapEnd - $overlapStart) / 3600;
            }
        }

        return $nightHours;
    }

    /**
     * Apply break deduction proportionally across segments
     */
    private function applyBreakDeduction(array $segments, float $breakHours, float $totalShiftHours): array
    {
        $breakRatio = $breakHours / $totalShiftHours;

        return array_map(function ($segment) use ($breakRatio) {
            $segmentBreakHours = $segment['hours'] * $breakRatio;
            $remainingRatio = ($segment['hours'] - $segmentBreakHours) / $segment['hours'];

            return [
                ...$segment,
                'hours'         => round($segment['hours'] - $segmentBreakHours, 4),
                'regular_hours' => round($segment['regular_hours'] * $remainingRatio, 4),
                'night_hours'   => round($segment['night_hours'] * $remainingRatio, 4),
                'regular_pay'   => round($segment['regular_pay'] * $remainingRatio, 2),
                'night_pay'     => round($segment['night_pay'] * $remainingRatio, 2),
                'pay'           => round($segment['pay'] * $remainingRatio, 2),
            ];
        }, $segments);
    }

    /**
     * Calculate totals from segments
     */
    private function calculateTotals(array $segments): array
    {
        $totals = ['pay' => 0, 'hours' => 0, 'regular_hours' => 0, 'night_hours' => 0];

        foreach ($segments as $segment) {
            $totals['pay'] += $segment['pay'];
            $totals['hours'] += $segment['hours'];
            $totals['regular_hours'] += $segment['regular_hours'];
            $totals['night_hours'] += $segment['night_hours'];
        }

        return $totals;
    }

    /**
     * Return empty result structure
     */
    private function emptyResult(float $breakHours): array
    {
        return [
            'segments'            => [],
            'total_pay'           => 0,
            'total_hours'         => 0,
            'total_regular_hours' => 0,
            'total_night_hours'   => 0,
            'break_hours'         => $breakHours,
        ];
    }
}
```

---

## Quick Reference: Deduction Order

The standard order of payroll deductions:

1. **Gross Pay** (Basic + Overtime + Holiday + Night Diff)
2. Less: SSS Contribution
3. Less: PhilHealth Contribution
4. Less: Pag-IBIG Contribution
5. = **Taxable Income**
6. Less: Withholding Tax
7. Less: Late Deduction
8. Less: Undertime Deduction
9. Less: Other Deductions (Loans, Absences)
10. = **Net Pay**

---

## Database Tables Summary

| Table                      | Purpose                   | Key Columns                                   |
| -------------------------- | ------------------------- | --------------------------------------------- |
| `payroll_sss_table`        | SSS contribution brackets | min_salary, max_salary, ee_share              |
| `payroll_philhealth_table` | PhilHealth rate config    | rate, min_salary, max_salary                  |
| `payroll_pagibig_table`    | Pag-IBIG fixed amount     | fixed_amt                                     |
| `payroll_tax_table`        | BIR tax brackets          | min_salary, max_salary, base_tax, excess_rate |
| `holidays`                 | Holiday calendar          | holiday_date, holiday_type, holiday_name      |

---

## Source Files Index

| Calculator      | Path                                                                                                       | Main Method            | Lines   |
| --------------- | ---------------------------------------------------------------------------------------------------------- | ---------------------- | ------- |
| SSS             | [SssCalculator.php](file:///c:/xampp/htdocs/ems1/src/Services/Payroll/SssCalculator.php)                   | `calculate()`          | L25-36  |
| PhilHealth      | [PhilHealthCalculator.php](file:///c:/xampp/htdocs/ems1/src/Services/Payroll/PhilHealthCalculator.php)     | `calculate()`          | L28-61  |
| Pag-IBIG        | [PagIbigCalculator.php](file:///c:/xampp/htdocs/ems1/src/Services/Payroll/PagIbigCalculator.php)           | `calculate()`          | L32-40  |
| Tax             | [TaxCalculator.php](file:///c:/xampp/htdocs/ems1/src/Services/Payroll/TaxCalculator.php)                   | `calculate()`          | L28-54  |
| Holiday         | [HolidayCalculator.php](file:///c:/xampp/htdocs/ems1/src/Services/Payroll/HolidayCalculator.php)           | `getDayType()`         | L31-45  |
| Night Diff      | [NightDiffCalculator.php](file:///c:/xampp/htdocs/ems1/src/Services/Payroll/NightDiffCalculator.php)       | `calculate()`          | L27-73  |
| Overtime        | [OvertimeCalculator.php](file:///c:/xampp/htdocs/ems1/src/Services/Payroll/OvertimeCalculator.php)         | `calculate()`          | L30-51  |
| Undertime       | [UndertimeCalculator.php](file:///c:/xampp/htdocs/ems1/src/Services/Payroll/UndertimeCalculator.php)       | `calculateDeduction()` | L20-34  |
| Multi-Day Shift | [ShiftPayrollCalculator.php](file:///c:/xampp/htdocs/ems1/src/Services/Payroll/ShiftPayrollCalculator.php) | `calculateShiftPay()`  | L49-87  |
| Extension (All) | [PayrollExtension.php](file:///c:/xampp/htdocs/ems1/src/Services/Payroll/PayrollExtension.php)             | Various                | L31-175 |

---

## Modifying Configuration

### To Update SSS Rates:

```sql
UPDATE payroll_sss_table
SET ee_share = 1200.00
WHERE min_salary = 20000.00;
```

### To Add a Holiday:

```sql
INSERT INTO holidays (holiday_date, holiday_type, holiday_name)
VALUES ('2026-12-25', 'REGULAR', 'Christmas Day');
```

### To Update PhilHealth Rate:

```sql
UPDATE payroll_philhealth_table
SET rate = 0.05, max_salary = 100000.00
WHERE id = 1;
```

---

## Legal References

- **Labor Code of the Philippines** (Presidential Decree No. 442)
- **TRAIN Law** (Republic Act No. 10963) - Tax Reform
- **SSS Circular No. 2024-001** - Contribution Schedule
- **PhilHealth Circular No. 2024-0006** - Premium Contribution
- **Pag-IBIG Circular No. 456** - Contribution Guidelines

---

_This document should be reviewed and updated whenever government agencies release new contribution tables or rates._
