# Scheduler, Timecard & Payroll System Documentation

This guide separates **instructions** and **code**, making it easier to compare and edit the system.

---

# I. System Workflow Overview
The system works in a 3-step cycle: **Schedule → Track → Pay**.

## 1. Scheduler (schedule.php)
**Purpose:** HR or Department Heads create work schedules.

### Instructions
- Plot shifts for each cutoff period.
- When status becomes **Approved**, the system saves the shifts to the **FinalizedSchedule** table.
- The **Biometric ID (ac_no)** links schedules to biometric logs.

---

## 2. Timecard (timecard.php)
**Purpose:** Compare planned schedule vs actual logs.

### Instructions
- Fetches schedule from **FinalizedSchedule**.
- Fetches logs from **AttendanceData**.
- Computes late, undertime, and overtime.
- HR can add manual adjustments saved in **TimecardAdjustments**.

---

## 3. Payslip (payslip.php)
**Purpose:** Converts attendance into monetary values.

### Instructions
- Reads outputs from Timecard.
- Applies rates, tax formulas, contributions.
- Generates final pay.

---

# II. Editing payroll calculations (payslip.php)

## 1. Salary & Hourly Rate Calculation
**Location:** Lines ~83–87

### Instructions
- Change `$work_days_per_month` for divisor.
- Change hourly divisor from 8 to your preference.

### Code
```php
$monthly_salary = $employee['salary_rate'] > 0 ? $employee['salary_rate'] : 16900;
$work_days_per_month = 26; // change to 22 or other
$daily_rate = $monthly_salary / $work_days_per_month;
$hourly_rate = $daily_rate / 8; // change hours if needed
```

---

## 2. Overtime (OT) Rate Calculation
**Location:** Lines ~240–255

### Instructions
- Change OT multipliers for regular, special, or holiday OT.

### Code
```php
$rateMult = 1.25; // change to modify OT
if ($holidayType == 'REGULAR') $rateMult = 2.6;
if ($holidayType == 'SPECIAL') $rateMult = 1.69;
```

---

## 3. Late & Undertime Deduction
**Location:** Lines ~295–296

### Instructions
- Modify formula if using fixed penalties or custom rules.

### Code
```php
$deduction_lates = $totals['late_mins'] * $minute_rate;
$deduction_ut = $totals['ut_mins'] * $minute_rate;
```

---

## 4. Government Contributions (SSS, PH, Pag-IBIG)
**Location:** Lines ~305–307, ~314–316

### Instructions
- Edit percentages.
- Edit caps.
- Remove `/ 2` to deduct whole amount in one cutoff.

### Code
```php
function calcSSS($sal) { return min(35000, max(5000, $sal)) * 0.05; }
function calcPH($sal) { return min(100000, max(10000, $sal)) * 0.025; }
function calcPagIBIG($sal) { return min(10000, $sal) * 0.02; }

$cut_sss = $monthly_sss / 2; // remove /2 to deduct full amount
```

---

## 5. Tax Calculation
**Location:** Line ~311

### Instructions
- Adjust threshold or percentage based on BIR TRAIN table.

### Code
```php
$monthly_tax = ($taxable_est > 20833)
    ? ($taxable_est - 20833) * 0.20
    : 0;
```

---

## 6. Counting Paid Days Per Cutoff
**Location:** Lines ~220 and ~283–284

### Instructions
- Modify leave codes that should count as paid days.

### Code
```php
if (in_array($shiftCode, ['LWP', 'VL', 'SL', 'HOLIDAY OFF', 'NEW_LEAVE_TYPE'])) {
    $totals['gross_from_attendance'] += $daily_rate;
    $totals['days_attended']++;
}
```

---

# End of Guide
This guide is structured so you clearly see where instructions end and code begins.

