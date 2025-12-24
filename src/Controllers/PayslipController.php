<?php
namespace App\Controllers;

use App\Models\Payslip;
use PDO;
use DateTime;

class PayslipController
{
    protected $emsPdo;
    protected $schedulerPdo;
    protected $model;

    public function __construct(PDO $emsPdo, PDO $schedulerPdo)
    {
        $this->emsPdo = $emsPdo;
        $this->schedulerPdo = $schedulerPdo;
        $this->model = new Payslip($emsPdo, $schedulerPdo);
    }

    public function index()
    {
        // 1. Auth & Setup
        if (!isset($_SESSION['user_id'])) { 
            header("Location: login.php"); 
            exit; 
        }

        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['approval_role'] ?? 'Employee';
        $isHr = ($userRole === 'HR');
        $myAcNo = $_SESSION['ac_no'];

        // 2. Cutoff Generation
        $cutoffs = $this->generateCutoffs();
        $selectedCutoff = $_GET['cutoff'] ?? $cutoffs[0]['val'];
        list($startDate, $endDate) = explode('|', $selectedCutoff);

        // 3. Target Employee
        $targetAcNo = $myAcNo;
        if ($isHr && isset($_GET['search_ac']) && !empty($_GET['search_ac'])) {
            $targetAcNo = $_GET['search_ac'];
        }

        // 4. Employee List (for HR dropdown)
        $empList = [];
        if ($isHr) {
            $stmt = $this->emsPdo->query("SELECT ac_no, first_name, last_name, job_title FROM [EmployeeManagementSystem].[dbo].[Employees] ORDER BY last_name");
            $empList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 5. Handle Actions (POST)
        if ($isHr && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_posting') {
            $this->handlePosting($startDate, $endDate, $_POST['new_status'], $_SESSION['full_name']);
            header("Location: payslip.php?cutoff=" . urlencode($selectedCutoff) . "&search_ac=" . urlencode($targetAcNo));
            exit;
        }

        // 6. Fetch Payroll Status
        $statusData = $this->model->getPayrollStatus($startDate, $endDate);
        $isPosted = $statusData ? (bool)$statusData['is_posted'] : false;
        $posterName = $statusData['updated_by'] ?? 'N/A';

        // 7. Access Control
        $accessDenied = (!$isHr && !$isPosted);

        // 8. Fetch Data
        $employee = null;
        $breakdown = [];
        $allowances = [];
        $loans = [];
        $netPay = 0;
        $totalDeductions = 0;
        $grossPay = 0;
        $govDeductions = ['sss' => 0, 'philhealth' => 0, 'pagibig' => 0, 'tax' => 0];

        if (!$accessDenied) {
            $employee = $this->model->getEmployeeData($targetAcNo);
            if (!$employee) {
                die("<div class='p-4 text-red-600'>Employee record not found.</div>");
            }

            // Loans
            $stmtLoans = $this->emsPdo->prepare("SELECT loan_category, description, per_cutoff_deduction, remaining_balance FROM [EmployeeManagementSystem].[dbo].[EmployeeLoans] WHERE emp_id = ? AND status = 'Active' AND remaining_balance > 0");
            $stmtLoans->execute([$employee['emp_id']]);
            $totalLoanDeductions = 0;
            while ($loan = $stmtLoans->fetch(PDO::FETCH_ASSOC)) {
                $deduction = min($loan['per_cutoff_deduction'], $loan['remaining_balance']);
                if ($deduction > 0) {
                    $label = $loan['loan_category'] . (!empty($loan['description']) ? " (" . $loan['description'] . ")" : "");
                    $loans[] = ['type' => $label, 'amount' => $deduction, 'balance' => $loan['remaining_balance'] - $deduction];
                    $totalLoanDeductions += $deduction;
                }
            }

            // Attendance & Breakdown
            $attendanceData = $this->model->getAttendanceData($targetAcNo, $startDate, $endDate);
            $breakdown = $this->model->calculateBreakdown($employee, $startDate, $endDate, $attendanceData);

            // Allowances
            $totalAllowance = 0;
            $stmtAllow = $this->schedulerPdo->prepare("
                SELECT t.name, t.amount, t.deduction_per_absent, t.frequency 
                FROM Payroll_EmployeeAllowances ea
                JOIN Payroll_AllowanceTypes t ON ea.allowance_id = t.allowance_id
                WHERE ea.emp_id = ? AND t.is_active = 1
            ");
            $stmtAllow->execute([$employee['emp_id']]);
            while ($row = $stmtAllow->fetch(PDO::FETCH_ASSOC)) {
                $pay = 0; $notes = "";
                if ($row['frequency'] === 'Semi-Monthly') {
                    $deduction = $breakdown['days_absent'] * 65.00; // Hardcoded in legacy
                    $pay = max(0, $row['amount'] - $deduction);
                    if ($deduction > 0) $notes = "(-" . number_format($deduction,0) . " for absents)";
                } elseif ($row['frequency'] === 'Daily') {
                    $pay = $breakdown['days_worked'] * $row['amount'];
                    $notes = "({$breakdown['days_worked']} days)";
                }
                if ($pay > 0) {
                    $allowances[] = ['name' => $row['name'], 'amount' => $pay, 'notes' => $notes];
                    $totalAllowance += $pay;
                }
            }

            // Totals
            $grossPay = $breakdown['pay_basic'] + $breakdown['pay_holiday'] + $breakdown['pay_overtime'] + $breakdown['pay_nightdiff'] + $totalAllowance;
            $totalTardiness = $breakdown['deduct_late'] + $breakdown['deduct_ut'];

            // Govt Deductions
            $startDay = (int)date('d', strtotime($startDate));
            $isDeductionPeriod = ($startDay >= 23);
            $monthlySalary = $employee['salary_rate'];

            if ($isDeductionPeriod) {
                // Assuming helper functions are available in scope
                if(function_exists('getSSSDeduction')) $govDeductions['sss'] = getSSSDeduction($this->schedulerPdo, $monthlySalary); 
                if(function_exists('getPhilHealthDeduction')) $govDeductions['philhealth'] = getPhilHealthDeduction($this->schedulerPdo,  $monthlySalary);
                if(function_exists('getPagIBIGDeduction')) $govDeductions['pagibig'] = getPagIBIGDeduction($this->schedulerPdo, $monthlySalary);
            }
            if(function_exists('calculateTax')) $govDeductions['tax'] = calculateTax($this->schedulerPdo, $monthlySalary);

            $totalGov = array_sum($govDeductions);
            $totalDeductions = $totalGov + $totalTardiness + $totalLoanDeductions;
            $netPay = $grossPay - $totalDeductions;
        }

        // Render View
        extract([
            'is_hr' => $isHr,
            'is_posted' => $isPosted,
            'poster_name' => $posterName,
            'empList' => $empList,
            'target_ac_no' => $targetAcNo,
            'cutoffs' => $cutoffs,
            'sel_cutoff' => $selectedCutoff,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'access_denied' => $accessDenied,
            'employee' => $employee,
            'breakdown' => $breakdown,
            'allowances' => $allowances,
            'loans' => $loans,
            'gross_pay' => $grossPay,
            'net_pay' => $netPay,
            'gov_deductions' => $govDeductions,
            'total_deductions' => $totalDeductions
        ]);

        require __DIR__ . '/../Views/payslip_view.php';
    }

    private function generateCutoffs()
    {
        // Copied from payslip.php
        $cutoffs = [];
        $current = new DateTime();
        $current->modify('-4 months'); 
        for ($i = 0; $i < 8; $i++) { 
            $year = $current->format('Y');
            $month = $current->format('m');
            
            $p1_start = "$year-$month-08";
            $p1_end   = "$year-$month-22";
            $cutoffs[] = ['val' => "$p1_start|$p1_end", 'label' => date('M 08', strtotime($p1_start)) . " - " . date('M 22, Y', strtotime($p1_end))];

            $p2_start = "$year-$month-23";
            $nextMonthDate = clone $current;
            $nextMonthDate->modify('+1 month');
            $p2_end = $nextMonthDate->format('Y-m-07');
            $nextYearLabel = $nextMonthDate->format('Y');
            $cutoffs[] = ['val' => "$p2_start|$p2_end", 'label' => date('M 23', strtotime($p2_start)) . " - " . date('M 07, Y', strtotime($p2_end))];

            $current->modify('+1 month');
        }
        return array_reverse($cutoffs);
    }

    private function handlePosting($startDate, $endDate, $newStatus, $userName)
    {
        try {
            $this->schedulerPdo->beginTransaction();
            $this->model->updatePayrollStatus($startDate, $endDate, $newStatus, $userName);
            
            if ($newStatus == '1') {
                $this->model->processLoanDeductions($endDate);
            } else {
                $this->model->reverseLoanDeductions($endDate);
            }
            
            $this->schedulerPdo->commit();
        } catch (Exception $e) {
            $this->schedulerPdo->rollBack();
            die("Error processing payroll posting: " . $e->getMessage());
        }
    }
}
