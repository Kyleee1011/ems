<?php
namespace App\Models;

use App\Services\Payroll\HolidayCalculator;
use App\Services\Payroll\NightDiffCalculator;
use App\Services\Payroll\OvertimeCalculator;
use App\Services\Payroll\PagIbigCalculator;
use App\Services\Payroll\PhilHealthCalculator;
use App\Services\Payroll\ShiftPayrollCalculator;
use App\Services\Payroll\SssCalculator;
use App\Services\Payroll\TaxCalculator;
use App\Services\Payroll\UndertimeCalculator;
use PDO;
use DateTime;

class Payslip
{
    protected $pdo;
    private $holidayCalculator;
    private $overtimeCalculator;
    private $nightDiffCalculator;
    private $undertimeCalculator;
    private $sssCalculator;
    private $philHealthCalculator;
    private $pagIbigCalculator;
    private $taxCalculator;
    private $shiftPayrollCalculator;

    public function __construct(
        PDO $pdo,
        HolidayCalculator $holidayCalculator,
        OvertimeCalculator $overtimeCalculator,
        NightDiffCalculator $nightDiffCalculator,
        UndertimeCalculator $undertimeCalculator,
        SssCalculator $sssCalculator,
        PhilHealthCalculator $philHealthCalculator,
        PagIbigCalculator $pagIbigCalculator,
        TaxCalculator $taxCalculator
    ) {
        $this->pdo = $pdo;
        $this->holidayCalculator = $holidayCalculator;
        $this->overtimeCalculator = $overtimeCalculator;
        $this->nightDiffCalculator = $nightDiffCalculator;
        $this->undertimeCalculator = $undertimeCalculator;
        $this->sssCalculator = $sssCalculator;
        $this->philHealthCalculator = $philHealthCalculator;
        $this->pagIbigCalculator = $pagIbigCalculator;
        $this->taxCalculator = $taxCalculator;
        // Initialize ShiftPayrollCalculator for cross-day shift handling
        $this->shiftPayrollCalculator = new ShiftPayrollCalculator($pdo);
    }


    public function getPayrollStatus($startDate, $endDate)
    {
        $stmt = $this->pdo->prepare("SELECT is_posted, updated_by FROM payroll_period_status WHERE start_date = ? AND end_date = ?");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updatePayrollStatus($startDate, $endDate, $status, $updatedBy)
    {
        $checkStmt = $this->pdo->prepare("SELECT id FROM payroll_period_status WHERE start_date = ? AND end_date = ?");
        $checkStmt->execute([$startDate, $endDate]);
        if ($checkStmt->fetch()) {
            return $this->pdo->prepare("UPDATE payroll_period_status SET is_posted = ?, updated_by = ?, updated_at = NOW() WHERE start_date = ? AND end_date = ?")->execute([$status, $updatedBy, $startDate, $endDate]);
        } else {
            return $this->pdo->prepare("INSERT INTO payroll_period_status (start_date, end_date, is_posted, updated_by) VALUES (?, ?, ?, ?)")->execute([$startDate, $endDate, $status, $updatedBy]);
        }
    }

    public function processLoanDeductions($endDate, $cutoffStartDate)
    {
        try {
            $this->pdo->beginTransaction();

            $stmtLoans = $this->pdo->prepare("SELECT loan_id, per_cutoff_deduction, remaining_balance FROM employee_loans WHERE status = 'Active' AND remaining_balance > 0 AND start_date <= ?");
            $stmtLoans->execute([$cutoffStartDate]);
            $activeLoans = $stmtLoans->fetchAll(PDO::FETCH_ASSOC);

            foreach ($activeLoans as $loan) {
                $chk = $this->pdo->prepare("SELECT payment_id FROM loan_payments WHERE loan_id = ? AND payment_date = ?");
                $chk->execute([$loan['loan_id'], $endDate]);
                if($chk->fetch()) continue;

                $amount = min($loan['per_cutoff_deduction'], $loan['remaining_balance']);
                
                if ($amount > 0) {
                    $insPay = $this->pdo->prepare("INSERT INTO loan_payments (loan_id, amount_paid, payment_date, notes) VALUES (?, ?, ?, 'Payroll Deduction')");
                    $insPay->execute([$loan['loan_id'], $amount, $endDate]);

                    $newBal = $loan['remaining_balance'] - $amount;
                    $status = ($newBal <= 0) ? 'Paid' : 'Active';
                    $updLoan = $this->pdo->prepare("UPDATE employee_loans SET remaining_balance = ?, status = ? WHERE loan_id = ?");
                    $updLoan->execute([$newBal, $status, $loan['loan_id']]);
                }
            }

            $this->pdo->commit();
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function reverseLoanDeductions($endDate)
    {
        $getPay = $this->pdo->prepare("SELECT payment_id, loan_id, amount_paid FROM loan_payments WHERE payment_date = ? AND notes = 'Payroll Deduction'");
        $getPay->execute([$endDate]);
        $payments = $getPay->fetchAll(PDO::FETCH_ASSOC);

        foreach ($payments as $p) {
            $updRev = $this->pdo->prepare("UPDATE employee_loans SET remaining_balance = remaining_balance + ?, status = 'Active' WHERE loan_id = ?");
            $updRev->execute([$p['amount_paid'], $p['loan_id']]);

            $delPay = $this->pdo->prepare("DELETE FROM loan_payments WHERE payment_id = ?");
            $delPay->execute([$p['payment_id']]);
        }
    }

    public function getEmployeeData($acNo)
    {
        $stmt = $this->pdo->prepare("SELECT emp_id, ac_no, first_name, last_name, salary_rate, daily_rate, hourly_rate, job_title, employee_status, employment_status, work_days_mode FROM employees WHERE ac_no = ?");
        $stmt->execute([$acNo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public function getActiveLoans(int $empId, string $cutoffStartDate): array
    {
        $loans = [];
        $stmtLoans = $this->pdo->prepare(
            "SELECT loan_category, description, per_cutoff_deduction, remaining_balance 
             FROM employee_loans 
             WHERE emp_id = ? AND status = 'Active' AND remaining_balance > 0 AND start_date <= ?"
        );
        $stmtLoans->execute([$empId, $cutoffStartDate]);
        
        while ($loan = $stmtLoans->fetch(PDO::FETCH_ASSOC)) {
            $deduction = min((float)$loan['per_cutoff_deduction'], (float)$loan['remaining_balance']);
            if ($deduction > 0) {
                $label = $loan['loan_category'] . (!empty($loan['description']) ? " (" . $loan['description'] . ")" : "");
                $loans[] = [
                    'type' => $label, 
                    'category' => $loan['loan_category'],
                    'amount' => $deduction, 
                    'balance' => (float)$loan['remaining_balance'] - $deduction
                ];
            }
        }
        return $loans;
    }

    public function getCalculatedAllowances(int $empId, array $breakdown, ?string $cutoffStartDate = null): array
    {
        $allowances = [];
        $stmtAllow = $this->pdo->prepare("
            SELECT t.name, t.amount, t.deduction_per_absent, t.frequency,
                   COALESCE(ea.start_cutoff_date, t.start_cutoff_date) as effective_start_date
            FROM payroll_employee_allowances ea
            JOIN payroll_allowance_types t ON ea.allowance_id = t.allowance_id
            WHERE ea.emp_id = ? AND t.is_active = 1 AND ea.is_active = 1
        ");
        $stmtAllow->execute([$empId]);

        while ($row = $stmtAllow->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['effective_start_date']) && !empty($cutoffStartDate)) {
                if ($cutoffStartDate < $row['effective_start_date']) {
                    continue;
                }
            }

            $pay = 0; 
            $notes = "";
            if ($row['frequency'] === 'Semi-Monthly') {
                $deduction = $breakdown['days_absent'] * (float)$row['deduction_per_absent'];
                $pay = max(0, (float)$row['amount'] - $deduction);
                if ($deduction > 0) {
                    $notes = "(-" . number_format($deduction, 0) . " for absents)";
                }
            } elseif ($row['frequency'] === 'Daily') {
                $pay = $breakdown['days_worked'] * (float)$row['amount'];
                $notes = "({$breakdown['days_worked']} days)";
            } elseif ($row['frequency'] === 'Monthly') {
                $halfAmount = (float)$row['amount'] / 2;
                $deduction = $breakdown['days_absent'] * (float)$row['deduction_per_absent'];
                $pay = max(0, $halfAmount - $deduction);
                $notes = "(Monthly ÷ 2)";
                if ($deduction > 0) {
                    $notes .= " (-" . number_format($deduction, 0) . " absents)";
                }
            }

            if ($pay > 0) {
                $allowances[] = ['name' => $row['name'], 'amount' => $pay, 'notes' => $notes];
            }
        }
        return $allowances;
    }
    
    public function calculateGovernmentDeductions(float $monthlySalary, float $grossPay, string $cutoffPeriodType = 'sss', float $monthlyAllowances = 0): array
    {
        $govDeductions = ['sss' => 0, 'mpf' => 0, 'philhealth' => 0, 'pagibig' => 0, 'tax' => 0];

        $sssResult = $this->sssCalculator->calculate($monthlySalary);
        $fullSss = $sssResult['ee_share'];
        $fullMpf = $sssResult['mpf'];
        $fullPhilHealth = $this->philHealthCalculator->calculate($monthlySalary);
        $fullPagIbig = $this->pagIbigCalculator->calculate($monthlySalary);

        $thisCutoffDeductions = 0;
        if ($cutoffPeriodType === 'sss') {
            $govDeductions['sss'] = $fullSss;
            $govDeductions['mpf'] = $fullMpf;
            $thisCutoffDeductions = $fullSss + $fullMpf;
        } else {
            $govDeductions['philhealth'] = $fullPhilHealth;
            $govDeductions['pagibig'] = $fullPagIbig;
            $thisCutoffDeductions = $fullPhilHealth + $fullPagIbig;
        }

        $cutoffTaxableIncome = $grossPay - $thisCutoffDeductions;

        if ($monthlyAllowances > 0) {
            $cutoffTaxableIncome -= ($monthlyAllowances / 2);
        }

        if ($cutoffTaxableIncome < 0) {
            $cutoffTaxableIncome = 0;
        }

        $monthlyEquivalentTaxable = $cutoffTaxableIncome * 2;
        $monthlyEquivalentTax = $this->taxCalculator->calculate($monthlyEquivalentTaxable);
        $govDeductions['tax'] = round($monthlyEquivalentTax / 2, 2);

        foreach ($govDeductions as $key => $val) {
            $govDeductions[$key] = round($val, 2);
        }

        return $govDeductions;
    }


    public function calculateBreakdown($employee, $startDate, $endDate, $dtrData)
    {
        $breakdown = [
            'days_worked' => 0, 'days_absent' => 0,
            'hours_ot' => 0, 'mins_late' => 0, 'mins_ut' => 0,
            'pay_basic' => 0, 'pay_holiday' => 0, 'pay_overtime' => 0, 'pay_nightdiff' => 0,
            'deduct_late' => 0, 'deduct_ut' => 0,
            
            // Granular fields for the new template
            'hrs_reg_ot' => 0, 'pay_reg_ot' => 0,
            'hrs_nd' => 0, 'pay_nd' => 0,
            'hrs_rd_sh' => 0, 'pay_rd_sh' => 0, // RD+Sp Hol (1.5)
            'hrs_rd' => 0, 'pay_rd' => 0,       // Rest Day (1.3)
            'hrs_sh' => 0, 'pay_sh' => 0,       // Special Hol (1.3)
            'hrs_rd_sh_ot' => 0, 'pay_rd_sh_ot' => 0,
            'hrs_sh_nd' => 0, 'pay_sh_nd' => 0, // Special Hol ND
            'hrs_rd_sh_nd' => 0, 'pay_rd_sh_nd' => 0, // Rest Day ND
            'hrs_rh' => 0, 'pay_rh' => 0,       // Holiday (2.0)
            'hrs_rh_ot' => 0, 'pay_rh_ot' => 0,
            'hrs_rh_nd' => 0, 'pay_rh_nd' => 0,
            
            'deduct_absent' => 0,
            'credits_adj' => 0,
            'tax_refund' => 0
        ];

        $daily_rate = (float)($employee['daily_rate'] ?? 0);
        $hourly_rate = (float)($employee['hourly_rate'] ?? 0);
        $salary_rate = (float)($employee['salary_rate'] ?? 0);
        $employment_status = $employee['employment_status'] ?? 'Regular';
        $work_days_mode = $employee['work_days_mode'] ?? '6';
        
        $isMonthlyPaid = in_array($employment_status, ['Regular', 'Probationary']);
        
        if ($salary_rate > 0) {
            $divisor = ($work_days_mode === '5') ? 261 : 313;
            $daily_rate = ($salary_rate * 12) / $divisor;
            $hourly_rate = $daily_rate / 8;
        } elseif ($hourly_rate <= 0 && $daily_rate > 0) {
            $hourly_rate = $daily_rate / 8;
        }
        $minute_rate = $hourly_rate > 0 ? $hourly_rate / 60 : 0;

        foreach ($dtrData as $dateStr => $day) {
            $holidayType = $this->holidayCalculator->getDayType($dateStr);
            $isRestDay = (bool)($day['is_rest_day'] ?? false);

            if ($day['hours'] > 0) {
                $breakdown['days_worked']++;
                $breakdown['hours_ot'] += (float)($day['ot_hours'] ?? 0);
                $breakdown['mins_late'] += (int)($day['late_mins'] ?? 0);
                $breakdown['mins_ut'] += (int)($day['ut_mins'] ?? 0);

                // Logic Fix: For Monthly Paid employees, the fixed salary (salary_rate/2) covers
                // 100% of the daily pay for all 313 working days in a year (including holidays).
                // However, Rest Days (e.g. Sundays) are NOT included in the 313-day divisor,
                // so they are not yet paid. Daily Paid employees get 0% for unworked days.
                $baseMultiplier = 1;
                $holidaySubtrahend = ($isMonthlyPaid && !$isRestDay) ? 1.0 : 0.0;

                // 1. REGULAR DAY hours
                $breakdown['pay_basic'] += $day['regular_hrs'] * $hourly_rate * $baseMultiplier;
                
                $breakdown['hrs_nd'] += $day['nd_hrs'];
                $breakdown['pay_nd'] += $day['nd_hrs'] * $hourly_rate * 0.10;
                
                // 2. OVERTIME (Ordinary)
                $breakdown['hrs_reg_ot'] += $day['regular_ot_hrs'];
                $breakdown['pay_reg_ot'] += $day['regular_ot_hrs'] * $hourly_rate * 1.25;
                
                $breakdown['hrs_nd'] += $day['nd_ot_hrs'];
                $breakdown['pay_nd'] += $day['nd_ot_hrs'] * $hourly_rate * 1.25 * 0.10;

                // 3. REST DAY hours (Multiplier 1.3)
                $breakdown['hrs_rd'] += $day['rest_day_hrs'];
                $breakdown['pay_rd'] += $day['rest_day_hrs'] * $hourly_rate * (1.3 - $holidaySubtrahend);
                
                $breakdown['hrs_rd_sh_ot'] += $day['rest_day_ot_hrs'];
                $breakdown['pay_rd_sh_ot'] += $day['rest_day_ot_hrs'] * $hourly_rate * 1.69;

                // 4. REGULAR HOLIDAY (Multiplier 2.0)
                $breakdown['hrs_rh'] += $day['regular_holiday_hrs'];
                $breakdown['pay_rh'] += $day['regular_holiday_hrs'] * $hourly_rate * (2.0 - $holidaySubtrahend);
                
                $breakdown['hrs_rh_ot'] += $day['regular_holiday_ot_hrs'];
                $breakdown['pay_rh_ot'] += $day['regular_holiday_ot_hrs'] * $hourly_rate * 2.60;
                
                $breakdown['hrs_rh_nd'] += $day['nd_regular_holiday_hrs'];
                $breakdown['pay_rh_nd'] += $day['nd_regular_holiday_hrs'] * $hourly_rate * 2.0 * 0.10;

                // 5. SPECIAL HOLIDAY (Multiplier 1.3)
                $breakdown['hrs_sh'] += $day['special_holiday_hrs'];
                $breakdown['pay_sh'] += $day['special_holiday_hrs'] * $hourly_rate * (1.3 - $holidaySubtrahend);
                
                $breakdown['hrs_rd_sh_ot'] += $day['special_holiday_ot_hrs'];
                $breakdown['pay_rd_sh_ot'] += $day['special_holiday_ot_hrs'] * $hourly_rate * 1.69;
                
                $breakdown['hrs_sh_nd'] += $day['nd_special_holiday_hrs'];
                $breakdown['pay_sh_nd'] += $day['nd_special_holiday_hrs'] * $hourly_rate * 1.3 * 0.10;

                // 6. DOUBLE HOLIDAY (Multiplier 3.0)
                $breakdown['hrs_rh'] += $day['double_holiday_hrs'];
                $breakdown['pay_rh'] += $day['double_holiday_hrs'] * $hourly_rate * (3.0 - $holidaySubtrahend);
                
                $breakdown['hrs_rh_ot'] += $day['double_holiday_ot_hrs'];
                $breakdown['pay_rh_ot'] += $day['double_holiday_ot_hrs'] * $hourly_rate * 3.90;
                
                $breakdown['hrs_rh_nd'] += $day['nd_double_holiday_hrs'];
                $breakdown['pay_rh_nd'] += $day['nd_double_holiday_hrs'] * $hourly_rate * 3.0 * 0.10;

                // 7. REST DAY + REGULAR HOLIDAY (Multiplier 2.6)
                $breakdown['hrs_rh'] += $day['rest_day_regular_holiday_hrs'];
                $breakdown['pay_rh'] += $day['rest_day_regular_holiday_hrs'] * $hourly_rate * (2.6 - $holidaySubtrahend);
                
                // 8. REST DAY + SPECIAL HOLIDAY (Multiplier 1.5)
                $breakdown['hrs_rd_sh'] += $day['rest_day_special_holiday_hrs'];
                $breakdown['pay_rd_sh'] += $day['rest_day_special_holiday_hrs'] * $hourly_rate * (1.5 - $holidaySubtrahend);

                // 9. Late & Undertime Deductions
                $breakdown['deduct_late'] += ($day['late_mins'] * $minute_rate);
                $breakdown['deduct_ut'] += ($day['ut_mins'] * $minute_rate);

            } else { // Not worked
                $isWorkDay = !in_array($day['sched_code'], ['OFF', '-', 'HOLIDAY OFF', 'REST DAY']);
                if ($day['remarks'] === 'ABSENT' || ($day['hours'] == 0 && $isWorkDay)) {
                     if ($holidayType === HolidayCalculator::DAY_TYPE_ORDINARY) {
                        $breakdown['days_absent']++;
                    } elseif ($holidayType == HolidayCalculator::DAY_TYPE_REGULAR_HOLIDAY) {
                        $breakdown['pay_basic'] += $daily_rate; 
                    }
                } elseif ($holidayType == HolidayCalculator::DAY_TYPE_REGULAR_HOLIDAY) {
                    $breakdown['pay_basic'] += $daily_rate;
                }
            }
        }
        
        // Aggregate totals for backward compatibility with existing view logic if needed
        // Round individual components first to ensure aggregates match displayed line items
        $lineItems = [
            'pay_rh', 'pay_sh', 'pay_rd', 'pay_rd_sh', 
            'pay_reg_ot', 'pay_rd_sh_ot', 'pay_rh_ot',
            'pay_nd', 'pay_rd_sh_nd', 'pay_sh_nd', 'pay_rh_nd',
            'pay_basic', 'deduct_late', 'deduct_ut'
        ];
        
        foreach ($lineItems as $item) {
            if (isset($breakdown[$item])) {
                $breakdown[$item] = round($breakdown[$item], 2);
            }
        }

        $breakdown['pay_holiday'] = $breakdown['pay_rh'] + $breakdown['pay_sh'] + $breakdown['pay_rd'] + $breakdown['pay_rd_sh'];
        $breakdown['pay_overtime'] = $breakdown['pay_reg_ot'] + $breakdown['pay_rd_sh_ot'] + $breakdown['pay_rh_ot'];
        $breakdown['pay_nightdiff'] = $breakdown['pay_nd'] + $breakdown['pay_rd_sh_nd'] + $breakdown['pay_sh_nd'] + $breakdown['pay_rh_nd'];

        if ($isMonthlyPaid && $salary_rate > 0) {
            $cutoffBasePayHalf = $salary_rate / 2;
            $breakdown['pay_basic'] = $cutoffBasePayHalf;
            $breakdown['deduct_absent'] = $breakdown['days_absent'] * $daily_rate;
            // Note: deduct_late and deduct_ut are already calculated and will be shown in "Less" section
        }

        foreach ($breakdown as $key => $value) {
            if (is_numeric($value) && (strpos($key, 'pay_') === 0 || strpos($key, 'deduct_') === 0 || strpos($key, 'credits_') === 0 || strpos($key, 'tax_') === 0)) {
                $breakdown[$key] = round($value, 2);
            }
        }
        
        if ($breakdown['pay_basic'] < 0) $breakdown['pay_basic'] = 0;
        
        return $breakdown;
    }
}
