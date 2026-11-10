<?php

namespace Tests;

use App\Models\Leave;
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
use PHPUnit\Framework\TestCase;

class OvertimeIntegrationTest extends TestCase
{
    private PDO $pdo;
    private Leave $leaveModel;
    private OvertimeCalculator $otCalc;
    private int $testEmpId1;
    private int $testEmpId2;

    protected function setUp(): void
    {
        global $pdo;
        if (!$pdo) {
            require_once 'C:/xampp/htdocs/ems/config.php';
        }
        $this->pdo = $pdo;
        $this->leaveModel = new Leave($this->pdo);
        $this->otCalc = new OvertimeCalculator();

        // Get two active employees for testing
        $stmt = $this->pdo->query("SELECT emp_id, ac_no FROM employees WHERE employee_status = 'Active' LIMIT 2");
        $emps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->testEmpId1 = (int)($emps[0]['emp_id'] ?? 1);
        $this->testEmpId2 = (int)($emps[1]['emp_id'] ?? 2);

        // Clean up test OT applications
        $this->pdo->prepare("DELETE FROM overtime_applications WHERE ot_date LIKE '2099-%'")->execute();
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare("DELETE FROM overtime_applications WHERE ot_date LIKE '2099-%'")->execute();
    }

    public function testEmployeeCanApplyOvertime(): void
    {
        $date = '2099-05-10';
        $hours = 2.5;
        $reason = 'Urgent server maintenance';

        // Apply overtime for single employee
        $result = $this->leaveModel->applyOvertime([$this->testEmpId1], $date, $hours, $reason);

        $this->assertSame(1, $result['added']);
        $this->assertSame(0, $result['dupes']);

        // Verify record in database
        $stmt = $this->pdo->prepare("SELECT * FROM overtime_applications WHERE emp_id = ? AND ot_date = ?");
        $stmt->execute([$this->testEmpId1, $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($row);
        $this->assertEquals($hours, (float)$row['ot_hours']);
        $this->assertSame($reason, $row['reason']);
        $this->assertSame('Pending', $row['status']);
    }

    public function testDuplicateOvertimeApplicationIsPrevented(): void
    {
        $date = '2099-05-11';
        $hours = 3.0;
        $reason = 'Quarter-end closing';

        // First application
        $res1 = $this->leaveModel->applyOvertime([$this->testEmpId1], $date, $hours, $reason);
        $this->assertSame(1, $res1['added']);

        // Second application on the same date for the same employee
        $res2 = $this->leaveModel->applyOvertime([$this->testEmpId1], $date, $hours, 'Different reason');
        $this->assertSame(0, $res2['added']);
        $this->assertSame(1, $res2['dupes']);
    }

    public function testManagerCanApplyForMultipleEmployees(): void
    {
        $date = '2099-05-12';
        $hours = 2.0;
        $reason = 'Inventory audit team OT';

        $res = $this->leaveModel->applyOvertime([$this->testEmpId1, $this->testEmpId2], $date, $hours, $reason);

        $this->assertSame(2, $res['added']);
        $this->assertSame(0, $res['dupes']);
    }

    public function testEmployeeOvertimeHistoryIncludesName(): void
    {
        $date = '2099-05-13';
        $this->leaveModel->applyOvertime([$this->testEmpId1], $date, 2.0, 'History name check');

        // Non-manager history query for employee
        $history = $this->leaveModel->getOvertimeHistory($this->testEmpId1, null);

        $this->assertNotEmpty($history);
        $first = $history[0];
        $this->assertArrayHasKey('first_name', $first);
        $this->assertArrayHasKey('last_name', $first);
        $this->assertNotEmpty($first['first_name']);
        $this->assertNotEmpty($first['last_name']);
    }

    public function testApproveOvertimeDoesNotCorruptFinalizedSchedule(): void
    {
        $date = '2099-05-14';
        $this->leaveModel->applyOvertime([$this->testEmpId1], $date, 3.0, 'Schedule stability test');

        $stmt = $this->pdo->prepare("SELECT ot_id FROM overtime_applications WHERE emp_id = ? AND ot_date = ?");
        $stmt->execute([$this->testEmpId1, $date]);
        $otId = (int)$stmt->fetchColumn();

        // Approve the overtime
        $this->leaveModel->updateOvertimeStatus($otId, 'Approved', 1);

        // Verify status is approved
        $check = $this->pdo->prepare("SELECT status, approved_by FROM overtime_applications WHERE ot_id = ?");
        $check->execute([$otId]);
        $app = $check->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('Approved', $app['status']);
        $this->assertEquals(1, $app['approved_by']);
    }

    public function testOvertimePayCalculationsForOrdinaryAndHolidays(): void
    {
        $hourlyRate = 100.00; // PHP 100 per hour base
        $otHours = 2.0;

        // 1. Ordinary Day OT: 125% -> 2 * 100 * 1.25 = 250.00
        $payOrdinary = $this->otCalc->calculate($otHours, $hourlyRate, 'REGULAR_DAY', false);
        $this->assertEquals(250.00, $payOrdinary);

        // 2. Rest Day OT: 130% * 1.30 = 169% -> 2 * 100 * 1.69 = 338.00
        $payRestDay = $this->otCalc->calculate($otHours, $hourlyRate, 'REGULAR_DAY', true);
        $this->assertEquals(338.00, $payRestDay);

        // 3. Special Non-Working Holiday OT: 130% * 1.30 = 169% -> 2 * 100 * 1.69 = 338.00
        $paySpecial = $this->otCalc->calculate($otHours, $hourlyRate, 'SPECIAL', false);
        $this->assertEquals(338.00, $paySpecial);

        // 4. Special Holiday on Rest Day OT: 150% * 1.30 = 195% -> 2 * 100 * 1.95 = 390.00
        $paySpecialRest = $this->otCalc->calculate($otHours, $hourlyRate, 'SPECIAL', true);
        $this->assertEquals(390.00, $paySpecialRest);

        // 5. Regular Holiday OT: 200% * 1.30 = 260% -> 2 * 100 * 2.60 = 520.00
        $payRegular = $this->otCalc->calculate($otHours, $hourlyRate, 'REGULAR', false);
        $this->assertEquals(520.00, $payRegular);

        // 6. Regular Holiday on Rest Day OT: 260% * 1.30 = 338% -> 2 * 100 * 3.38 = 676.00
        $payRegularRest = $this->otCalc->calculate($otHours, $hourlyRate, 'REGULAR', true);
        $this->assertEquals(676.00, $payRegularRest);

        // 7. Double Holiday OT: 300% * 1.30 = 390% -> 2 * 100 * 3.90 = 780.00
        $payDouble = $this->otCalc->calculate($otHours, $hourlyRate, 'DOUBLE', false);
        $this->assertEquals(780.00, $payDouble);

        // 8. Double Holiday on Rest Day OT: 390% * 1.30 = 507% -> 2 * 100 * 5.07 = 1014.00
        $payDoubleRest = $this->otCalc->calculate($otHours, $hourlyRate, 'DOUBLE', true);
        $this->assertEquals(1014.00, $payDoubleRest);
    }

    public function testPayslipBreakdownIntegrationWithOvertimeCalculator(): void
    {
        $holidayCalc = new HolidayCalculator($this->pdo);
        $nightDiffCalc = new NightDiffCalculator();
        $undertimeCalc = new UndertimeCalculator();
        $sssCalc = new SssCalculator($this->pdo);
        $philHealthCalc = new PhilHealthCalculator($this->pdo);
        $pagIbigCalc = new PagIbigCalculator($this->pdo);
        $taxCalc = new TaxCalculator($this->pdo);

        $payslip = new Payslip(
            $this->pdo,
            $holidayCalc,
            $this->otCalc,
            $nightDiffCalc,
            $undertimeCalc,
            $sssCalc,
            $philHealthCalc,
            $pagIbigCalc,
            $taxCalc
        );

        // Create simulated employee and mock DTR with various overtime types
        $employee = [
            'emp_id' => $this->testEmpId1,
            'ac_no' => 'TEST_AC',
            'salary_rate' => 0,
            'daily_rate' => 800.00,
            'hourly_rate' => 100.00,
            'employment_status' => 'Contractual',
            'work_days_mode' => '6'
        ];

        $mockDtr = [
            // Day 1: Ordinary day with 2 hrs OT
            '2099-06-01' => [
                'hours' => 10,
                'ot_hours' => 2,
                'late_mins' => 0,
                'ut_mins' => 0,
                'is_rest_day' => false,
                'regular_hrs' => 8,
                'regular_ot_hrs' => 2,
                'nd_hrs' => 0,
                'nd_ot_hrs' => 0,
                'rest_day_hrs' => 0,
                'rest_day_ot_hrs' => 0,
                'regular_holiday_hrs' => 0,
                'regular_holiday_ot_hrs' => 0,
                'special_holiday_hrs' => 0,
                'special_holiday_ot_hrs' => 0,
                'double_holiday_hrs' => 0,
                'double_holiday_ot_hrs' => 0,
                'rest_day_regular_holiday_hrs' => 0,
                'rest_day_regular_holiday_ot_hrs' => 0,
                'rest_day_special_holiday_hrs' => 0,
                'rest_day_special_holiday_ot_hrs' => 0,
                'nd_regular_holiday_hrs' => 0,
                'nd_special_holiday_hrs' => 0,
                'nd_double_holiday_hrs' => 0,
                'nd_rest_day_hrs' => 0,
                'nd_rest_day_regular_holiday_hrs' => 0,
                'nd_rest_day_special_holiday_hrs' => 0,
            ],
            // Day 2: Regular holiday on rest day with 2 hrs OT
            '2099-06-02' => [
                'hours' => 10,
                'ot_hours' => 2,
                'late_mins' => 0,
                'ut_mins' => 0,
                'is_rest_day' => true,
                'regular_hrs' => 0,
                'regular_ot_hrs' => 0,
                'nd_hrs' => 0,
                'nd_ot_hrs' => 0,
                'rest_day_hrs' => 0,
                'rest_day_ot_hrs' => 0,
                'regular_holiday_hrs' => 0,
                'regular_holiday_ot_hrs' => 0,
                'special_holiday_hrs' => 0,
                'special_holiday_ot_hrs' => 0,
                'double_holiday_hrs' => 0,
                'double_holiday_ot_hrs' => 0,
                'rest_day_regular_holiday_hrs' => 8,
                'rest_day_regular_holiday_ot_hrs' => 2,
                'rest_day_special_holiday_hrs' => 0,
                'rest_day_special_holiday_ot_hrs' => 0,
                'nd_regular_holiday_hrs' => 0,
                'nd_special_holiday_hrs' => 0,
                'nd_double_holiday_hrs' => 0,
                'nd_rest_day_hrs' => 0,
                'nd_rest_day_regular_holiday_hrs' => 0,
                'nd_rest_day_special_holiday_hrs' => 0,
            ],
        ];

        $breakdown = $payslip->calculateBreakdown($employee, '2099-06-01', '2099-06-02', $mockDtr);

        // Day 1 regular OT pay = 2 * 100 * 1.25 = 250.00
        $this->assertEquals(250.00, $breakdown['pay_reg_ot']);

        // Day 2 rest day + regular holiday OT pay = 2 * 100 * 3.38 = 676.00
        $this->assertEquals(676.00, $breakdown['pay_rh_ot']);

        // Total OT pay = 250 + 676 = 926.00
        $this->assertEquals(926.00, $breakdown['pay_overtime']);

        // Total OT hours = 2 + 2 = 4.0
        $this->assertEquals(4.0, $breakdown['hours_ot']);
    }

    public function testDeptHeadCanViewAllActiveEmployeesWithOwnDepartmentFirst(): void
    {
        $userDeptId = 2; // MIS
        $sql = "SELECT e.emp_id, e.ac_no, e.first_name, e.last_name, e.dept_id, COALESCE(d.dept_name, 'Unassigned') as dept_name 
                FROM employees e 
                LEFT JOIN departments d ON e.dept_id = d.dept_id 
                WHERE e.employee_status = 'Active'
                ORDER BY (e.dept_id = :user_dept) DESC, d.dept_name ASC, e.last_name ASC, e.first_name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_dept' => $userDeptId]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($employees);
        $this->assertGreaterThanOrEqual(10, count($employees));
        
        // First employee must belong to user's department
        $this->assertEquals($userDeptId, (int)$employees[0]['dept_id']);

        // Check department name is joined
        $this->assertNotEmpty($employees[0]['dept_name']);
    }

    public function testDeptHeadCanApplyOvertimeForSubordinatesAndInspectHistory(): void
    {
        $date = '2099-07-15';
        $hours = 3.0;
        $reason = 'Database and system upgrade deployment';

        // Apply OT for two employees
        $res = $this->leaveModel->applyOvertime([$this->testEmpId1, $this->testEmpId2], $date, $hours, $reason);
        $this->assertSame(2, $res['added']);

        // Dept Head retrieves history for employee 1
        $history = $this->leaveModel->getOvertimeHistory($this->testEmpId1, null);
        $this->assertNotEmpty($history);
        $this->assertSame($this->testEmpId1, (int)$history[0]['emp_id']);
        $this->assertNotEmpty($history[0]['first_name']);
        $this->assertArrayHasKey('dept_name', $history[0]);
    }

    public function testCrossDayShiftNightDiffAndOtDistribution(): void
    {
        $timecard = new \App\Models\Timecard($this->pdo);
        $reflector = new \ReflectionClass($timecard);
        $m = $reflector->getMethod('classifyHoursCrossDay');
        $m->setAccessible(true);

        $dStr = '2099-10-23';
        $tsIn = strtotime("$dStr 14:00:00");
        $tsOut = strtotime("$dStr +1 day 03:00:00");
        $ndLimitTsIn = $tsIn;
        $ndLimitTsOut = $tsOut;

        // Shift 14:00 to 03:00 (13h elapsed, 1h break = 12h paid, 4h OT)
        $res = $m->invoke($timecard, $tsIn, $tsOut, 12.0, 4.0, $dStr, false, $ndLimitTsIn, $ndLimitTsOut, 0);

        // 8 regular hours, 4 overtime hours
        $this->assertEquals(8.0, $res['regular_hrs']);
        $this->assertEquals(4.0, $res['regular_ot_hrs']);

        // Total night diff is 5.0 hours (300 minutes, from 22:00 to 03:00)
        $this->assertEquals(300, $res['night_diff_mins']);

        // Regular ND is 1.0 hour (22:00 to 23:00 within regular shift)
        $this->assertEquals(1.0, $res['nd_hrs']);

        // Overtime ND is 4.0 hours (23:00 to 03:00 during overtime)
        $this->assertEquals(4.0, $res['nd_ot_hrs']);
    }

    public function testCrossDayEveningShiftNightDiff(): void
    {
        $timecard = new \App\Models\Timecard($this->pdo);
        $reflector = new \ReflectionClass($timecard);
        $m = $reflector->getMethod('classifyHoursCrossDay');
        $m->setAccessible(true);

        $dStr = '2099-10-24';
        $tsIn = strtotime("$dStr 17:00:00");
        $tsOut = strtotime("$dStr +1 day 04:00:00");
        $ndLimitTsIn = $tsIn;
        $ndLimitTsOut = $tsOut;

        // Shift 17:00 to 04:00 (11h elapsed, 1h break = 10h paid, 2h OT)
        $res = $m->invoke($timecard, $tsIn, $tsOut, 10.0, 2.0, $dStr, false, $ndLimitTsIn, $ndLimitTsOut, 0);

        $this->assertEquals(8.0, $res['regular_hrs']);
        $this->assertEquals(2.0, $res['regular_ot_hrs']);
        $this->assertEquals(360, $res['night_diff_mins']);
        // 4 hours regular ND (22:00 - 02:00)
        $this->assertEquals(4.0, $res['nd_hrs']);
        // 2 hours OT ND (02:00 - 04:00)
        $this->assertEquals(2.0, $res['nd_ot_hrs']);
    }
}
