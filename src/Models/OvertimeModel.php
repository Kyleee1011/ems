<?php
namespace App\Models;

use PDO;

class OvertimeModel
{
    protected $emsPdo;

    public function __construct(PDO $emsPdo)
    {
        $this->emsPdo = $emsPdo;
    }

    public function getPendingOTCount() {
        return $this->emsPdo->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[OvertimeApplications] WHERE status = 'Pending'")->fetchColumn();
    }

    public function getPendingOTList() {
        return $this->emsPdo->query("SELECT TOP 5 ot.*, e.first_name, e.last_name FROM [EmployeeManagementSystem].[dbo].[OvertimeApplications] ot JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON ot.emp_id = e.emp_id WHERE ot.status = 'Pending' ORDER BY ot.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function processOvertimeApplication($otId, $decision, $approverId) {
        $status = ($decision === 'approve') ? 'Approved' : 'Rejected';
        $stmt = $this->emsPdo->prepare(
            "UPDATE [EmployeeManagementSystem].[dbo].[OvertimeApplications] 
             SET status = ?, approved_by = ?, approval_date = GETDATE() 
             WHERE ot_id = ?"
        );
        return $stmt->execute([$status, $approverId, $otId]);
    }
}
