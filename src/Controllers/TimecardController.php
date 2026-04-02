<?php
namespace App\Controllers;

use App\Models\Timecard;
use App\Utils\AppHelpers;
use PDO;

class TimecardController
{
    protected $pdo;
    protected $model;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new Timecard($pdo);
    }

    public function index()
    {
        // 1. Auth Headers
        if (!isset($_SESSION['user_id'])) { header("Location: " . baseUrl('login')); exit; }
        
        $currentRole = $_SESSION['approval_role'] ?? 'Employee';
        $isHr = ($currentRole === 'HR');
        $myAcNo = $_SESSION['ac_no'];

        // 2. AJAX Handler for Manual Adjustments
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_log') {
            $this->handleUpdateLog($isHr);
        }

        // 3. Inputs
        $targetAcNo = $myAcNo;
        $targetName = $_SESSION['full_name'];
        $targetDept = $_SESSION['dept_name'] ?? '--';

        if ($isHr && isset($_GET['search_ac']) && !empty($_GET['search_ac'])) {
            $targetAcNo = $_GET['search_ac'];
            $stmt = $this->pdo->prepare("
                SELECT e.first_name, e.last_name, d.dept_name 
                FROM employees e 
                LEFT JOIN departments d ON e.dept_id = d.dept_id 
                WHERE e.ac_no = ?
            ");
            $stmt->execute([$targetAcNo]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) {
                $targetName = $res['first_name'] . ' ' . $res['last_name'];
                $targetDept = $res['dept_name'];
            }
        }

        // 4. Cutoff - show last 3 months (6 periods)
        $cutoffs = AppHelpers::generateCutoffPeriods($this->pdo, null, null, 3, -2);
        $selectedCutoff = $_GET['cutoff'] ?? ($cutoffs[0]['value'] ?? '');
        if ($selectedCutoff) {
            list($startDate, $endDate) = explode('|', $selectedCutoff);
        } else {
            $startDate = $endDate = date('Y-m-d');
        }

        // 5. Fetch DTR Data
        $data = $this->model->generateDtr($targetAcNo, $startDate, $endDate);

        // 6. Employee List (HR) & Fetch Target Employee Details (Rate/Salary)
        $empList = [];
        if ($isHr) {
            $stmt = $this->pdo->query("SELECT ac_no, first_name, last_name FROM employees ORDER BY last_name");
            $empList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fetch Hourly Rate for Payslip Calculation
        $stmtRate = $this->pdo->prepare("SELECT salary_rate, hourly_rate FROM employees WHERE ac_no = ?");
        $stmtRate->execute([$targetAcNo]);
        $empDetails = $stmtRate->fetch(PDO::FETCH_ASSOC);
        $hourlyRate = $empDetails['hourly_rate'] ?? 0;
        $salaryRate = $empDetails['salary_rate'] ?? 0;

        // 7. Calculate Payroll Estimation (If Cutoff Passed)
        $isCutoffPassed = (date('Y-m-d') > $endDate);
        $payrollSummary = null;

        if ($isCutoffPassed && $hourlyRate > 0) {
            $payrollSummary = [
                'gross_pay' => 0,
                'deductions' => 0,
                'net_pay' => 0,
                'breakdown' => []
            ];

            $rates = [
                'regular' => 1.0,
                'rest_day' => 1.3,
                'special_holiday' => 1.3,
                'regular_holiday' => 2.0,
                'double_holiday' => 3.0,
            ];

            // Initialize accumulators
            $totalPay = 0;
            $totalLateUtMins = 0;

            foreach ($data as $day) {
                // Sum Late/UT
                $totalLateUtMins += ($day['late_mins'] + $day['ut_mins']);

                // Calculate Pay for this day based on buckets
                // Regular Hours
                $totalPay += ($day['regular_hrs'] ?? 0) * $hourlyRate * $rates['regular'];
                $totalPay += ($day['regular_ot_hrs'] ?? 0) * $hourlyRate * $rates['regular'] * 1.25;

                // Rest Day
                $totalPay += ($day['rest_day_hrs'] ?? 0) * $hourlyRate * $rates['rest_day'];
                $totalPay += ($day['rest_day_ot_hrs'] ?? 0) * $hourlyRate * $rates['rest_day'] * 1.3; // Usually 169% but keeping simple 130% base + 30% OT on base? Standard is 130 * 1.3 = 1.69

                // Holidays
                $totalPay += ($day['special_holiday_hrs'] ?? 0) * $hourlyRate * $rates['special_holiday'];
                $totalPay += ($day['special_holiday_ot_hrs'] ?? 0) * $hourlyRate * $rates['special_holiday'] * 1.3;

                $totalPay += ($day['regular_holiday_hrs'] ?? 0) * $hourlyRate * $rates['regular_holiday'];
                $totalPay += ($day['regular_holiday_ot_hrs'] ?? 0) * $hourlyRate * $rates['regular_holiday'] * 1.3;

                $totalPay += ($day['double_holiday_hrs'] ?? 0) * $hourlyRate * $rates['double_holiday'];
                $totalPay += ($day['double_holiday_ot_hrs'] ?? 0) * $hourlyRate * $rates['double_holiday'] * 1.3;

                // Night Diff (Premium Only: 10% of the rate for that hour)
                // We need to know WHICH rate applied to the ND hour, but Timecard model separates them
                $ndPremium = 0.10;
                $totalPay += ($day['nd_hrs'] ?? 0) * $hourlyRate * $rates['regular'] * $ndPremium;
                $totalPay += ($day['nd_ot_hrs'] ?? 0) * $hourlyRate * $rates['regular'] * 1.25 * $ndPremium;
                
                $totalPay += ($day['nd_special_holiday_hrs'] ?? 0) * $hourlyRate * $rates['special_holiday'] * $ndPremium;
                $totalPay += ($day['nd_regular_holiday_hrs'] ?? 0) * $hourlyRate * $rates['regular_holiday'] * $ndPremium;
                $totalPay += ($day['nd_double_holiday_hrs'] ?? 0) * $hourlyRate * $rates['double_holiday'] * $ndPremium;
            }

            // Deductions
            $lateUtDeduction = ($totalLateUtMins / 60) * $hourlyRate;

            $payrollSummary['gross_pay'] = $totalPay;
            $payrollSummary['deductions'] = $lateUtDeduction; // Add gov contributions here if needed later
            $payrollSummary['net_pay'] = $totalPay - $lateUtDeduction;
            $payrollSummary['hourly_rate'] = $hourlyRate;
        }

        // 8. Render
        extract([
            'is_hr' => $isHr,
            'target_ac_no' => $targetAcNo,
            'target_name' => $targetName,
            'cutoffs' => $cutoffs,
            'selected_cutoff' => $selectedCutoff,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'data' => $data,
            'empList' => $empList,
            'payroll_summary' => $payrollSummary,
            'is_cutoff_passed' => $isCutoffPassed
        ]);

        require __DIR__ . '/../Views/timecard_view.php';
    }

    private function handleUpdateLog($isHr)
    {
        if (!$isHr) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

        // CSRF Check
        if (!\App\Utils\AppHelpers::validateCsrfToken($_POST['csrf_token'] ?? null)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh.']);
            exit;
        }

        $targetAc = $_POST['ac_no'] ?? null;
        $targetDate = $_POST['date'] ?? null;
        $type = $_POST['type'] ?? null;
        $newTime = $_POST['new_time'] ?? null;

        if (empty($targetAc) || empty($targetDate) || empty($type)) {
            echo json_encode(['success' => false, 'message' => 'Missing data']); exit;
        }

        try {
            $this->model->updateManualLog($targetAc, $targetDate, $type, $newTime);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
