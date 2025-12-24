<?php
namespace App\Models;

use PDO;

class PayrollConfigModel
{
    protected $schedulerPdo;

    public function __construct(PDO $schedulerPdo)
    {
        $this->schedulerPdo = $schedulerPdo;
    }

    public function updateSssTable($sssData) {
        $stmt = $this->schedulerPdo->prepare("UPDATE Payroll_SSS_Table SET min_salary=?, max_salary=?, ee_share=? WHERE id=?");
        foreach ($sssData as $id => $d) {
            $stmt->execute([$d['min'], $d['max'], $d['ee'], $id]);
        }
    }

    public function getSssData() {
        return $this->schedulerPdo->query("SELECT * FROM Payroll_SSS_Table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updatePhilHealthTable($phData) {
        $stmt = $this->schedulerPdo->prepare("UPDATE Payroll_PhilHealth_Table SET min_salary=?, max_salary=?, rate=? WHERE id=?");
        foreach ($phData as $id => $d) {
            $stmt->execute([$d['min'], $d['max'], $d['rate'], $id]);
        }
    }

    public function getPhilHealthData() {
        return $this->schedulerPdo->query("SELECT * FROM Payroll_PhilHealth_Table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updatePagIbigTable($piData) {
        $stmt = $this->schedulerPdo->prepare("UPDATE Payroll_PagIBIG_Table SET fixed_amt=? WHERE id=?");
        foreach ($piData as $id => $d) {
            $stmt->execute([$d['fixed'], $id]);
        }
    }

    public function getPagIbigData() {
        return $this->schedulerPdo->query("SELECT * FROM Payroll_PagIBIG_Table")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateTaxTable($taxData) {
        $stmt = $this->schedulerPdo->prepare("UPDATE Payroll_Tax_Table SET min_salary=?, max_salary=?, base_tax=?, excess_rate=? WHERE id=?");
        foreach ($taxData as $id => $d) {
            $stmt->execute([$d['min'], $d['max'], $d['base'], $d['rate'], $id]);
        }
    }

    public function getTaxData() {
        return $this->schedulerPdo->query("SELECT * FROM Payroll_Tax_Table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateOvertimeRules($otRulesData) {
        $stmt = $this->schedulerPdo->prepare("UPDATE Payroll_OvertimeRules SET ot_multiplier = ?, night_diff_percent = ?, updated_at = GETDATE() WHERE id = ?");
        foreach ($otRulesData['ot_id'] as $idx => $id) {
            $stmt->execute([$otRulesData['ot_multiplier'][$idx], $otRulesData['nd_multiplier'][$idx], $id]);
        }
    }

    public function getOvertimeRules() {
        return $this->schedulerPdo->query("SELECT * FROM Payroll_OvertimeRules ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateHolidayRules($holRulesData) {
        $stmt = $this->schedulerPdo->prepare("UPDATE Payroll_HolidayRules SET pay_if_unworked = ?, pay_if_worked = ?, updated_at = GETDATE() WHERE id = ?");
        foreach ($holRulesData['hol_id'] as $idx => $id) {
            $stmt->execute([$holRulesData['pay_unworked'][$idx], $holRulesData['pay_worked'][$idx], $id]);
        }
    }

    public function getHolidayRules() {
        return $this->schedulerPdo->query("SELECT * FROM Payroll_HolidayRules ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateGeneralSettings($settingsData) {
        $stmt = $this->schedulerPdo->prepare("UPDATE Payroll_GeneralSettings SET setting_value = ? WHERE setting_key = ?");
        foreach ($settingsData as $key => $val) {
            $stmt->execute([$val, $key]);
        }
    }

    public function getGeneralSettings() {
        $genSettingsRaw = $this->schedulerPdo->query("SELECT * FROM Payroll_GeneralSettings")->fetchAll(PDO::FETCH_ASSOC);
        $genSettings = [];
        foreach($genSettingsRaw as $row) {
            $genSettings[$row['setting_key']] = $row['setting_value'];
        }
        return $genSettings;
    }

    public function addAllowanceType($name, $amount, $deduct, $freq) {
        $stmt = $this->schedulerPdo->prepare("INSERT INTO Payroll_AllowanceTypes (name, amount, deduction_per_absent, frequency) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $amount, $deduct, $freq]);
    }

    public function assignAllowanceToAllActiveEmployees($allow_id, $currentEmsPdo) { // Requires EmployeeManagementSystem PDO
        $activeEmps = $currentEmsPdo->query("SELECT emp_id FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE employee_status = 'Active'")->fetchAll(PDO::FETCH_COLUMN); // Use $emsPdo
        
        $stmtCheck = $this->schedulerPdo->prepare("SELECT id FROM Payroll_EmployeeAllowances WHERE emp_id=? AND allowance_id=?");
        $stmtInsert = $this->schedulerPdo->prepare("INSERT INTO Payroll_EmployeeAllowances (emp_id, allowance_id) VALUES (?, ?)");
        
        foreach ($activeEmps as $emp_id) {
            $stmtCheck->execute([$emp_id, $allow_id]);
            if(!$stmtCheck->fetch()) {
                $stmtInsert->execute([$emp_id, $allow_id]);
            }
        }
    }

    public function getAllowanceTypes() {
        return $this->schedulerPdo->query("SELECT * FROM Payroll_AllowanceTypes WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    }
}
