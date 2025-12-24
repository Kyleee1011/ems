<?php
namespace App\Models;

use PDO;

class LeaveModel
{
    protected $emsPdo;

    public function __construct(PDO $emsPdo)
    {
        $this->emsPdo = $emsPdo;
    }

    public function getPendingLeaveCount() {
        return $this->emsPdo->query("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[LeaveApplications] WHERE status = 'Submitted' OR status = 'Pending'")->fetchColumn();
    }

    public function getPendingLeaveList() {
        return $this->emsPdo->query("SELECT TOP 5 l.*, e.first_name, e.last_name FROM [EmployeeManagementSystem].[dbo].[LeaveApplications] l JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON l.emp_id = e.emp_id WHERE l.status IN ('Submitted', 'Pending') ORDER BY l.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function processLeaveApplication($leaveId, $decision, $approverId) {
        $status = ($decision === 'approve') ? 'Approved' : 'Rejected';
        $stmt = $this->emsPdo->prepare(
            "UPDATE [EmployeeManagementSystem].[dbo].[LeaveApplications] 
             SET status = ?, approved_by = ?, approval_date = GETDATE() 
             WHERE leave_id = ?"
        );
        return $stmt->execute([$status, $approverId, $leaveId]);
    }

    // Add more methods for leave applications (add, update status, etc.)
}
