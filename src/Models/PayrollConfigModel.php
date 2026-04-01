<?php
namespace App\Models;

use PDO;

class PayrollConfigModel
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function updateSssTable($sssData) {
        $stmt = $this->pdo->prepare("UPDATE payroll_sss_table SET min_salary=?, max_salary=?, ee_share=?, mpf=?, ee_total=?, er_share=? WHERE id=?");
        foreach ($sssData as $d) {
            $stmt->execute([
                $d['min'], 
                $d['max'], 
                $d['ee'], 
                $d['mpf'] ?? 0, 
                $d['ee_total'] ?? 0, 
                $d['er'] ?? 0, 
                $d['id']
            ]);
        }
    }

    public function getSssData() {
        return $this->pdo->query("SELECT * FROM payroll_sss_table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updatePhilHealthTable($phData) {
        $stmt = $this->pdo->prepare("UPDATE payroll_philhealth_table SET min_salary=?, max_salary=?, rate=? WHERE id=?");
        foreach ($phData as $d) {
            $stmt->execute([$d['min'], $d['max'], $d['rate'], $d['id']]);
        }
    }

    public function getPhilHealthData() {
        return $this->pdo->query("SELECT * FROM payroll_philhealth_table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updatePagIbigTable($piData) {
        $stmt = $this->pdo->prepare("UPDATE payroll_pagibig_table SET fixed_amt=? WHERE id=?");
        foreach ($piData as $d) {
            $stmt->execute([$d['fixed'], $d['id']]);
        }
    }

    public function getPagIbigData() {
        return $this->pdo->query("SELECT * FROM payroll_pagibig_table")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateTaxTable($taxData) {
        $stmt = $this->pdo->prepare("UPDATE payroll_tax_table SET min_salary=?, max_salary=?, base_tax=?, excess_rate=? WHERE id=?");
        foreach ($taxData as $d) {
            // Handle both 'rate' and 'excess' field names for compatibility
            $excessRate = $d['excess'] ?? $d['rate'] ?? 0;
            $stmt->execute([$d['min'], $d['max'], $d['base'], $excessRate, $d['id']]);
        }
    }

    public function getTaxData() {
        return $this->pdo->query("SELECT * FROM payroll_tax_table ORDER BY min_salary ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateOvertimeRules($otRulesData) {
        $stmt = $this->pdo->prepare("UPDATE payroll_overtime_rules SET ot_multiplier = ?, night_diff_percent = ?, updated_at = NOW() WHERE id = ?");
        foreach ($otRulesData['ot_id'] as $idx => $id) {
            $stmt->execute([$otRulesData['ot_multiplier'][$idx], $otRulesData['nd_multiplier'][$idx], $id]);
        }
    }

    public function getOvertimeRules() {
        return $this->pdo->query("SELECT * FROM payroll_overtime_rules ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateHolidayRules($holRulesData) {
        $stmt = $this->pdo->prepare("UPDATE payroll_holiday_rules SET pay_if_unworked = ?, pay_if_worked = ?, updated_at = NOW() WHERE id = ?");
        foreach ($holRulesData['hol_id'] as $idx => $id) {
            $stmt->execute([$holRulesData['pay_unworked'][$idx], $holRulesData['pay_worked'][$idx], $id]);
        }
    }

    public function getHolidayRules() {
        return $this->pdo->query("SELECT * FROM payroll_holiday_rules ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateGeneralSettings($settingsData) {
        $stmt = $this->pdo->prepare("UPDATE payroll_general_settings SET setting_value = ? WHERE setting_key = ?");
        foreach ($settingsData as $key => $val) {
            $stmt->execute([$val, $key]);
        }
    }

    public function getGeneralSettings() {
        $genSettingsRaw = $this->pdo->query("SELECT * FROM payroll_general_settings")->fetchAll(PDO::FETCH_ASSOC);
        $genSettings = [];
        foreach($genSettingsRaw as $row) {
            $genSettings[$row['setting_key']] = $row['setting_value'];
        }
        return $genSettings;
    }

    public function addAllowanceType($name, $amount, $deduct, $freq, $startDate = null) {
        $this->ensureAllowanceSchema();
        $stmt = $this->pdo->prepare("INSERT INTO payroll_allowance_types (name, amount, deduction_per_absent, frequency, start_cutoff_date, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$name, $amount, $deduct, $freq, $startDate ?: null]);
        return (int)$this->pdo->lastInsertId();
    }

    public function assignAllowanceToAllActiveEmployees($allow_id, $startDate = null) {
        $this->ensureAllowanceSchema();
        $activeEmps = $this->pdo->query("SELECT emp_id FROM employees WHERE employee_status = 'Active'")->fetchAll(PDO::FETCH_COLUMN);
        
        $stmtCheck = $this->pdo->prepare("SELECT id FROM payroll_employee_allowances WHERE emp_id=? AND allowance_id=?");
        $stmtInsert = $this->pdo->prepare("INSERT INTO payroll_employee_allowances (emp_id, allowance_id, start_cutoff_date, is_active) VALUES (?, ?, ?, 1)");
        
        foreach ($activeEmps as $emp_id) {
            $stmtCheck->execute([$emp_id, $allow_id]);
            if(!$stmtCheck->fetch()) {
                $stmtInsert->execute([$emp_id, $allow_id, $startDate ?: null]);
            }
        }
    }

    public function deleteAllowanceType($allowanceId) {
        $this->ensureAllowanceSchema();
        $stmt = $this->pdo->prepare("UPDATE payroll_allowance_types SET is_active = 0 WHERE allowance_id = ?");
        $stmt->execute([$allowanceId]);
        $stmt2 = $this->pdo->prepare("UPDATE payroll_employee_allowances SET is_active = 0 WHERE allowance_id = ?");
        $stmt2->execute([$allowanceId]);
    }

    public function getAssignedEmployeeCount($allowanceId) {
        $this->ensureAllowanceSchema();
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM payroll_employee_allowances WHERE allowance_id = ? AND is_active = 1");
        $stmt->execute([$allowanceId]);
        return (int)$stmt->fetchColumn();
    }

    public function getAllowanceTypes() {
        $this->ensureAllowanceSchema();
        return $this->pdo->query(
            "SELECT at.*, 
                    (SELECT COUNT(*) FROM payroll_employee_allowances ea WHERE ea.allowance_id = at.allowance_id AND ea.is_active = 1) as emp_count
             FROM payroll_allowance_types at 
             WHERE at.is_active = 1 
             ORDER BY at.name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Auto-ensure the allowance tables have the required columns.
     * Safe to call multiple times — only adds columns if missing.
     */
    private function ensureAllowanceSchema() {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        // payroll_allowance_types: add is_active, start_cutoff_date if missing
        $cols = [];
        foreach ($this->pdo->query("SHOW COLUMNS FROM payroll_allowance_types") as $row) {
            $cols[] = $row['Field'];
        }
        if (!in_array('is_active', $cols)) {
            $this->pdo->exec("ALTER TABLE payroll_allowance_types ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
        }
        if (!in_array('start_cutoff_date', $cols)) {
            $this->pdo->exec("ALTER TABLE payroll_allowance_types ADD COLUMN start_cutoff_date DATE DEFAULT NULL");
        }

        // payroll_employee_allowances: add is_active, start_cutoff_date if missing
        $cols2 = [];
        foreach ($this->pdo->query("SHOW COLUMNS FROM payroll_employee_allowances") as $row) {
            $cols2[] = $row['Field'];
        }
        if (!in_array('is_active', $cols2)) {
            $this->pdo->exec("ALTER TABLE payroll_employee_allowances ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
        }
        if (!in_array('start_cutoff_date', $cols2)) {
            $this->pdo->exec("ALTER TABLE payroll_employee_allowances ADD COLUMN start_cutoff_date DATE DEFAULT NULL");
        }
    }
}
