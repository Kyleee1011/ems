<?php
namespace App\Models;

use PDO;

class OvertimeModel
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPendingOTCount() {
        return $this->pdo->query("SELECT COUNT(*) FROM overtime_applications WHERE status = 'Pending'")->fetchColumn();
    }

    public function getPendingOTList() {
        return $this->pdo->query("SELECT ot.*, e.first_name, e.last_name FROM overtime_applications ot JOIN employees e ON ot.emp_id = e.emp_id WHERE ot.status = 'Pending' ORDER BY ot.created_at ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function processOvertimeApplication($otId, $decision, $approverId) {
        $status = ($decision === 'approve') ? 'Approved' : 'Rejected';
        $stmt = $this->pdo->prepare(
            "UPDATE overtime_applications 
             SET status = ?, approved_by = ?, approval_date = NOW() 
             WHERE ot_id = ?"
        );
        return $stmt->execute([$status, $approverId, $otId]);
    }
}
