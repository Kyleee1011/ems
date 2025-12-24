<?php
namespace App\Models;

use PDO;
use Exception;

class PayrollConfig
{
    protected $schedulerPdo;
    protected $emsPdo;

    public function __construct(PDO $schedulerPdo, PDO $emsPdo = null)
    {
        $this->schedulerPdo = $schedulerPdo;
        if($emsPdo) $this->emsPdo = $emsPdo;
    }

    // SSS
    public function getSSS() { return $this->schedulerPdo->query("SELECT * FROM Payroll_SSS_Table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function updateSSS($data) {
        $stmt = $this->schedulerPdo->prepare("UPDATE Payroll_SSS_Table SET min_salary=?, max_salary=?, ee_share=? WHERE id=?");
        foreach ($data as $id => $d) $stmt->execute([$d['min'], $d['max'], $d['ee'], $id]);
    }

    // PhilHealth
    public function getPhilHealth() { return $this->schedulerPdo->query("SELECT * FROM Payroll_PhilHealth_Table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function updatePhilHealth($data) {
        $stmt = $this->schedulerPdo->prepare("UPDATE Payroll_PhilHealth_Table SET min_salary=?, max_salary=?, rate=? WHERE id=?");
        foreach ($data as $id => $d) $stmt->execute([$d['min'], $d['max'], $d['rate'], $id]);
    }

    // Pag-IBIG
    public function getPagibig() { return $this->schedulerPdo->query("SELECT * FROM Payroll_PagIBIG_Table")->fetchAll(PDO::FETCH_ASSOC); }
    public function updatePagibig($data) {
        $stmt = $this->schedulerPdo->prepare("UPDATE Payroll_PagIBIG_Table SET fixed_amt=? WHERE id=?");
        foreach ($data as $id => $d) $stmt->execute([$d['fixed'], $id]);
    }

    // Tax
    public function getTax() { return $this->schedulerPdo->query("SELECT * FROM Payroll_Tax_Table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function updateTax($data) {
        $stmt = $this->schedulerPdo->prepare("UPDATE Payroll_Tax_Table SET min_salary=?, max_salary=?, base_tax=?, excess_rate=? WHERE id=?");
        foreach ($data as $id => $d) $stmt->execute([$d['min'], $d['max'], $d['base'], $d['rate'], $id]);
    }

    // Rules
    public function getOTRules() { return $this->schedulerPdo->query("SELECT * FROM Payroll_OvertimeRules ORDER BY id")->fetchAll(PDO::FETCH_ASSOC); }
    public function updateOTRules($ids, $multipliers, $ndMultipliers) {
        $stmt = $this->schedulerPdo->prepare("UPDATE Payroll_OvertimeRules SET ot_multiplier = ?, night_diff_percent = ?, updated_at = GETDATE() WHERE id = ?");
        foreach ($ids as $idx => $id) $stmt->execute([$multipliers[$idx], $ndMultipliers[$idx], $id]);
    }

    public function getHolidayRules() { return $this->schedulerPdo->query("SELECT * FROM Payroll_HolidayRules ORDER BY id")->fetchAll(PDO::FETCH_ASSOC); }
    public function updateHolidayRules($ids, $payUnworked, $payWorked) {
        $stmt = $this->schedulerPdo->prepare("UPDATE Payroll_HolidayRules SET pay_if_unworked = ?, pay_if_worked = ?, updated_at = GETDATE() WHERE id = ?");
        foreach ($ids as $idx => $id) $stmt->execute([$payUnworked[$idx], $payWorked[$idx], $id]);
    }

    public function getGeneralSettings() {
        $data = $this->schedulerPdo->query("SELECT * FROM Payroll_GeneralSettings")->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach($data as $row) { $settings[$row['setting_key']] = $row['setting_value']; }
        return $settings;
    }
    public function updateGeneralSettings($settings) {
        $sql = "MERGE Payroll_GeneralSettings AS t USING (SELECT ? as k, ? as v) AS s ON (t.setting_key = s.k) WHEN MATCHED THEN UPDATE SET setting_value = s.v WHEN NOT MATCHED THEN INSERT (setting_key, setting_value) VALUES (s.k, s.v);";
        $stmt = $this->schedulerPdo->prepare($sql);
        foreach ($settings as $key => $val) $stmt->execute([$key, $val]);
    }

    // Allowances
    public function getAllowanceTypes() { return $this->schedulerPdo->query("SELECT * FROM Payroll_AllowanceTypes WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC); }
    public function addAllowanceType($name, $amount, $deduct, $freq) {
        $stmt = $this->schedulerPdo->prepare("INSERT INTO Payroll_AllowanceTypes (name, amount, deduction_per_absent, frequency) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$name, $amount, $deduct, $freq]);
    }
    public function assignAllowanceToAll($allowId) {
        if (!$this->emsPdo) return false;
        $activeEmps = $this->emsPdo->query("SELECT emp_id FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE IsActive = 1")->fetchAll(PDO::FETCH_COLUMN);
        
        $stmtCheck = $this->schedulerPdo->prepare("SELECT id FROM Payroll_EmployeeAllowances WHERE emp_id=? AND allowance_id=?");
        $stmtInsert = $this->schedulerPdo->prepare("INSERT INTO Payroll_EmployeeAllowances (emp_id, allowance_id) VALUES (?, ?)");
        
        foreach ($activeEmps as $emp_id) {
            $stmtCheck->execute([$emp_id, $allowId]);
            if(!$stmtCheck->fetch()) {
                $stmtInsert->execute([$emp_id, $allowId]);
            }
        }
        return true;
    }
}
