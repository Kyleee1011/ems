<?php
namespace App\Models;

use PDO;
use Exception;

class PayrollConfig
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // SSS
    public function getSSS() { return $this->pdo->query("SELECT * FROM payroll_sss_table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function updateSSS($data) {
        $stmt = $this->pdo->prepare("UPDATE payroll_sss_table SET min_salary=?, max_salary=?, ee_share=? WHERE id=?");
        foreach ($data as $id => $d) $stmt->execute([$d['min'], $d['max'], $d['ee'], $id]);
    }

    // PhilHealth
    public function getPhilHealth() { return $this->pdo->query("SELECT * FROM payroll_philhealth_table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function updatePhilHealth($data) {
        $stmt = $this->pdo->prepare("UPDATE payroll_philhealth_table SET min_salary=?, max_salary=?, rate=? WHERE id=?");
        foreach ($data as $id => $d) $stmt->execute([$d['min'], $d['max'], $d['rate'], $id]);
    }

    // Pag-IBIG
    public function getPagibig() { return $this->pdo->query("SELECT * FROM payroll_pagibig_table")->fetchAll(PDO::FETCH_ASSOC); }
    public function updatePagibig($data) {
        $stmt = $this->pdo->prepare("UPDATE payroll_pagibig_table SET fixed_amt=? WHERE id=?");
        foreach ($data as $id => $d) $stmt->execute([$d['fixed'], $id]);
    }

    // Tax
    public function getTax() { return $this->pdo->query("SELECT * FROM payroll_tax_table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function updateTax($data) {
        $stmt = $this->pdo->prepare("UPDATE payroll_tax_table SET min_salary=?, max_salary=?, base_tax=?, excess_rate=? WHERE id=?");
        foreach ($data as $id => $d) $stmt->execute([$d['min'], $d['max'], $d['base'], $d['rate'], $id]);
    }

    // Rules
    public function getOTRules() { return $this->pdo->query("SELECT * FROM payroll_overtime_rules ORDER BY id")->fetchAll(PDO::FETCH_ASSOC); }
    public function updateOTRules($ids, $multipliers, $ndMultipliers) {
        $stmt = $this->pdo->prepare("UPDATE payroll_overtime_rules SET ot_multiplier = ?, night_diff_percent = ?, updated_at = NOW() WHERE id = ?");
        foreach ($ids as $idx => $id) $stmt->execute([$multipliers[$idx], $ndMultipliers[$idx], $id]);
    }

    public function getHolidayRules() { return $this->pdo->query("SELECT * FROM payroll_holiday_rules ORDER BY id")->fetchAll(PDO::FETCH_ASSOC); }
    public function updateHolidayRules($ids, $payUnworked, $payWorked) {
        $stmt = $this->pdo->prepare("UPDATE payroll_holiday_rules SET pay_if_unworked = ?, pay_if_worked = ?, updated_at = NOW() WHERE id = ?");
        foreach ($ids as $idx => $id) $stmt->execute([$payUnworked[$idx], $payWorked[$idx], $id]);
    }

    public function getGeneralSettings() {
        $data = $this->pdo->query("SELECT * FROM payroll_general_settings")->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach($data as $row) { $settings[$row['setting_key']] = $row['setting_value']; }
        return $settings;
    }
    public function updateGeneralSettings($settings) {
        $sql = "INSERT INTO payroll_general_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);";
        $stmt = $this->pdo->prepare($sql);
        foreach ($settings as $key => $val) $stmt->execute([$key, $val]);
    }

    // Allowances
    public function getAllowanceTypes() { return $this->pdo->query("SELECT * FROM payroll_allowance_types WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC); }
    public function addAllowanceType($name, $amount, $deduct, $freq) {
        $stmt = $this->pdo->prepare("INSERT INTO payroll_allowance_types (name, amount, deduction_per_absent, frequency) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$name, $amount, $deduct, $freq]);
    }
    public function assignAllowanceToAll($allowId) {
        $activeEmps = $this->pdo->query("SELECT emp_id FROM employees WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
        
        $stmtCheck = $this->pdo->prepare("SELECT id FROM payroll_employee_allowances WHERE emp_id=? AND allowance_id=?");
        $stmtInsert = $this->pdo->prepare("INSERT INTO payroll_employee_allowances (emp_id, allowance_id) VALUES (?, ?)");
        
        foreach ($activeEmps as $emp_id) {
            $stmtCheck->execute([$emp_id, $allowId]);
            if(!$stmtCheck->fetch()) {
                $stmtInsert->execute([$emp_id, $allowId]);
            }
        }
        return true;
    }
}
