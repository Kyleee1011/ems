<?php
namespace App\Models;

use PDO;

class AllowanceModel
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureSchema();
    }

    /**
     * Auto-ensure the allowance tables have the required columns.
     */
    private function ensureSchema() {
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

    public function getAllAllowanceTypes(): array
    {
        return $this->pdo->query(
            "SELECT at.*, 
                    (SELECT COUNT(*) FROM payroll_employee_allowances ea WHERE ea.allowance_id = at.allowance_id AND ea.is_active = 1) as emp_count
             FROM payroll_allowance_types at 
             WHERE at.is_active = 1 
             ORDER BY at.name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createAllowanceType(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO payroll_allowance_types (name, amount, deduction_per_absent, frequency, start_cutoff_date, is_active) 
             VALUES (?, ?, ?, ?, ?, 1)"
        );
        $stmt->execute([
            $data['name'],
            $data['amount'],
            $data['deduction_per_absent'],
            $data['frequency'],
            $data['start_cutoff_date'] ?: null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function assignToEmployees(int $allowanceId, array $empIds, ?string $startDate): int
    {
        $count = 0;
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO payroll_employee_allowances (emp_id, allowance_id, start_cutoff_date, is_active) 
             VALUES (?, ?, ?, 1)"
        );
        foreach ($empIds as $empId) {
            $stmt->execute([(int)$empId, $allowanceId, $startDate ?: null]);
            $count++;
        }
        return $count;
    }

    public function assignToAll(int $allowanceId, ?string $startDate): int
    {
        $employees = $this->pdo->query("SELECT emp_id FROM employees WHERE employee_status = 'Active'")->fetchAll(PDO::FETCH_COLUMN);
        return $this->assignToEmployees($allowanceId, $employees, $startDate);
    }

    public function deleteAllowanceType(int $allowanceId): void
    {
        // Soft delete: mark as inactive
        $stmt = $this->pdo->prepare("UPDATE payroll_allowance_types SET is_active = 0 WHERE allowance_id = ?");
        $stmt->execute([$allowanceId]);
        // Also deactivate all assignments
        $stmt2 = $this->pdo->prepare("UPDATE payroll_employee_allowances SET is_active = 0 WHERE allowance_id = ?");
        $stmt2->execute([$allowanceId]);
    }

    public function removeEmployeeAllowance(int $empId, int $allowanceId): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM payroll_employee_allowances WHERE emp_id = ? AND allowance_id = ?"
        );
        $stmt->execute([$empId, $allowanceId]);
    }

    public function getAssignedEmployees(int $allowanceId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.emp_id, e.first_name, e.last_name, ea.start_cutoff_date
             FROM payroll_employee_allowances ea
             JOIN employees e ON ea.emp_id = e.emp_id
             WHERE ea.allowance_id = ? AND ea.is_active = 1
             ORDER BY e.last_name, e.first_name"
        );
        $stmt->execute([$allowanceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

