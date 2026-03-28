<?php
namespace App\Controllers;

use App\Models\Timecard;
use App\Models\Payslip;
use App\Services\Payroll\HolidayCalculator;
use App\Services\Payroll\NightDiffCalculator;
use App\Services\Payroll\OvertimeCalculator;
use App\Services\Payroll\PagIbigCalculator;
use App\Services\Payroll\PhilHealthCalculator;
use App\Services\Payroll\SssCalculator;
use App\Services\Payroll\TaxCalculator;
use App\Services\Payroll\UndertimeCalculator;
use PDO;
use DateTime;
use Exception;

class PayslipController
{
    protected $pdo;
    protected $model;
    protected $timecardModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        
        // Instantiate all services
        $holidayCalculator = new HolidayCalculator($pdo);
        $overtimeCalculator = new OvertimeCalculator();
        $nightDiffCalculator = new NightDiffCalculator();
        $undertimeCalculator = new UndertimeCalculator();
        $sssCalculator = new SssCalculator($pdo);
        $philHealthCalculator = new PhilHealthCalculator($pdo);
        $pagIbigCalculator = new PagIbigCalculator($pdo);
        $taxCalculator = new TaxCalculator($pdo);

        // Instantiate the main model with all its dependencies
        $this->model = new Payslip(
            $pdo,
            $holidayCalculator,
            $overtimeCalculator,
            $nightDiffCalculator,
            $undertimeCalculator,
            $sssCalculator,
            $philHealthCalculator,
            $pagIbigCalculator,
            $taxCalculator
        );
        
        $this->timecardModel = new Timecard($pdo);
    }

    public function index()
    {
        // 1. Auth & Setup
        if (!isset($_SESSION['user_id'])) { 
            header("Location: " . baseUrl('login')); 
            exit; 
        }

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

        // 4. Employee List for HR dropdown (This is UI-specific, so it can stay in the controller)
        $empList = [];
        if ($isHr) {
            $stmt = $this->pdo->query("SELECT ac_no, first_name, last_name, job_title FROM employees ORDER BY last_name");
            $empList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 5. Automated Loan Deduction Trigger
        // If the period has ended, ensure loan installments are committed to the ledger
        $today = date('Y-m-d');
        if ($today > $endDate) {
            $this->model->processLoanDeductions($endDate, $startDate);
        }

        // 6. Fetch Payroll Generation Metadata (Poster info is now legacy)
        $statusData = $this->model->getPayrollStatus($startDate, $endDate);
        $isPosted = ($today > $endDate); // In the new system, "Posted" means period is over
        $posterName = 'System (Auto)';

        // 7. Access Control
        $accessDenied = (!$isHr && !$isPosted);

        // 8. Initialize view variables
        $viewData = [
            'employee' => null, 'breakdown' => [], 'allowances' => [], 'loans' => [],
            'netPay' => 0, 'totalDeductions' => 0, 'grossPay' => 0,
            'gov_deductions' => ['sss' => 0, 'philhealth' => 0, 'pagibig' => 0, 'tax' => 0]
        ];

        if (!$accessDenied) {
            $employee = $this->model->getEmployeeData($targetAcNo);
            if (!$employee) {
                die("<div class='p-4 text-red-600'>Employee record not found.</div>");
            }
            $viewData['employee'] = $employee;

            // All calculation logic is now in the Model
            $dtrData = $this->timecardModel->generateDtr($targetAcNo, $startDate, $endDate);
            $breakdown = $this->model->calculateBreakdown($employee, $startDate, $endDate, $dtrData);
            
            $allowances = $this->model->getCalculatedAllowances($employee['emp_id'], $breakdown, $startDate);
            $loans = $this->model->getActiveLoans($employee['emp_id'], $startDate);
            
            $totalAllowance = array_sum(array_column($allowances, 'amount'));
            $totalLoanDeductions = array_sum(array_column($loans, 'amount'));

            // CALCULATE TOTALS (Sum raw values first, round at the very end)
            $rawGross = $breakdown['pay_basic'] + $breakdown['pay_holiday'] + $breakdown['pay_overtime'] + $breakdown['pay_nightdiff'] + 
                        $breakdown['credits_adj'] + $breakdown['tax_refund'] + $totalAllowance;
            $grossPay = round($rawGross, 2);
            
            $startDay = (int)date('d', strtotime($startDate));
            // Determine cutoff period type: 23-07 = 'sss', 08-22 = 'phpi'
            $cutoffPeriodType = ($startDay >= 23 || $startDay <= 7) ? 'sss' : 'phpi';
            
            // Calculate monthly de minimis allowances for tax exclusion
            $monthlyAllowances = $totalAllowance * 2;
            
            $govDeductions = $this->model->calculateGovernmentDeductions((float)$employee['salary_rate'], $grossPay, $cutoffPeriodType, $monthlyAllowances);

            $totalTardiness = round($breakdown['deduct_late'] + $breakdown['deduct_ut'] + ($breakdown['deduct_absent'] ?? 0), 2);
            $totalGov = round(array_sum($govDeductions), 2);
            $totalDeductions = round($totalGov + $totalTardiness + $totalLoanDeductions, 2);
            
            $netPay = round($grossPay - $totalDeductions, 2);

            // Assign calculated data to view variables
            $viewData['breakdown'] = $breakdown;
            $viewData['allowances'] = $allowances;
            $viewData['loans'] = $loans;
            $viewData['gross_pay'] = $grossPay;
            $viewData['gov_deductions'] = $govDeductions;
            $viewData['total_deductions'] = $totalDeductions;
            $viewData['net_pay'] = $netPay;
        }

        // Render View
        extract(array_merge([
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
        ], $viewData));

        require __DIR__ . '/../Views/payslip_view.php';
    }

    private function generateCutoffs()
    {
        return \App\Utils\AppHelpers::generateCutoffPeriods($this->pdo, null, null, 3, -2);
    }


}
