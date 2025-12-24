<?php
namespace App\Models;

use PDO;
use Exception;
use DateTime;
use DatePeriod;
use DateInterval;

class Leave
{
    protected $emsPdo;
    protected $schedulerPdo;

    public function __construct(PDO $emsPdo, PDO $schedulerPdo)
    {
        $this->emsPdo = $emsPdo;
        $this->schedulerPdo = $schedulerPdo;
    }

    public function getCredits($empId) {
        $stmt = $this->emsPdo->prepare("SELECT sil_credits FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE emp_id = ?");
        $stmt->execute([$empId]);
        return $stmt->fetchColumn() ?: 0.0;
    }

    // ==========================================
    // LEAVES
    // ==========================================
    public function applyLeave($empId, $type, $startStr, $endStr, $reason) {
        $start = new DateTime($startStr);
        $end = new DateTime($endStr);

        $checkSql = "SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[LeaveApplications]
                     WHERE emp_id = ? AND status != 'Rejected' AND ((start_date <= ? AND end_date >= ?))";
        $checkStmt = $this->emsPdo->prepare($checkSql);
        $checkStmt->execute([$empId, $endStr, $startStr]);

        if ($checkStmt->fetchColumn() > 0) throw new Exception("You already have a pending or approved leave for these dates.");

        if ($type === 'Service Incentive Leave') {
            $days = $start->diff($end)->days + 1;
            $credits = $this->getCredits($empId);
            if ($credits < $days) throw new Exception("Insufficient SIL Credits. Available: $credits, Requested: $days.");
        }

        $sql = "INSERT INTO [EmployeeManagementSystem].[dbo].[LeaveApplications] (emp_id, leave_type, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?, 'Submitted')";
        return $this->emsPdo->prepare($sql)->execute([$empId, $type, $startStr, $endStr, $reason]);
    }

    public function getLeaveHistory($empId) {
        $stmt = $this->emsPdo->prepare("SELECT * FROM [EmployeeManagementSystem].[dbo].[LeaveApplications] WHERE emp_id = ? ORDER BY created_at DESC");
        $stmt->execute([$empId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingLeaves() {
         $sql = "SELECT l.*, e.first_name, e.last_name, d.dept_name, e.sil_credits
               FROM [EmployeeManagementSystem].[dbo].[LeaveApplications] l
               JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON l.emp_id = e.emp_id
               LEFT JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON e.dept_id = d.dept_id
               WHERE l.status = 'Submitted' OR l.status = 'Pending'
               ORDER BY l.created_at ASC";
        return $this->emsPdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateLeaveStatus($leaveId, $status, $approverId) {
        $this->emsPdo->beginTransaction();
        try {
            $sql = "UPDATE [EmployeeManagementSystem].[dbo].[LeaveApplications] SET status = ?, approved_by = ?, approval_date = GETDATE() WHERE leave_id = ? AND status != 'Approved'";
            $stmt = $this->emsPdo->prepare($sql);
            $stmt->execute([$status, $approverId, $leaveId]);

            if ($stmt->rowCount() > 0 && $status === 'Approved') {
                $this->processApprovedLeave($leaveId);
            } elseif ($stmt->rowCount() == 0) {
                 if ($this->emsPdo->inTransaction()) $this->emsPdo->rollBack();
                 throw new Exception("Request already processed.");
            }
            $this->emsPdo->commit(); 
        } catch (Exception $e) {
            if ($this->emsPdo->inTransaction()) $this->emsPdo->rollBack();
            throw $e;
        }
    }

    private function processApprovedLeave($leaveId) {
        $getLeave = $this->emsPdo->prepare("SELECT emp_id, leave_type, start_date, end_date FROM [EmployeeManagementSystem].[dbo].[LeaveApplications] WHERE leave_id = ?");
        $getLeave->execute([$leaveId]);
        $leaveData = $getLeave->fetch(PDO::FETCH_ASSOC);

        if ($leaveData) {
            $start = new DateTime($leaveData['start_date']);
            $end = new DateTime($leaveData['end_date']);

            if ($leaveData['leave_type'] === 'Service Incentive Leave') {
                $daysCount = $start->diff($end)->days + 1;
                $this->emsPdo->prepare("UPDATE [EmployeeManagementSystem].[dbo].[Employees] SET sil_credits = sil_credits - ? WHERE emp_id = ?")->execute([$daysCount, $leaveData['emp_id']]);
            }

            // Map Code
            $rawType = $leaveData['leave_type'];
            $mappedCode = $this->mapShiftCode($rawType);

            $end->modify('+1 day');
            $period = new DatePeriod($start, new DateInterval('P1D'), $end);

            $acNo = $this->getAcNo($leaveData['emp_id']);
            $upd = $this->schedulerPdo->prepare("UPDATE [SchedulerDB].[dbo].[FinalizedSchedule] SET Shift_code = ?, Time_In = NULL, Time_Out = NULL WHERE Ac_no = ? AND schedule_Date = ?");
            
            foreach ($period as $dt) {
                $upd->execute([$mappedCode, $acNo, $dt->format('Y-m-d')]);
            }
        }
    }

    // ==========================================
    // OVERTIME
    // ==========================================
    public function applyOvertime($empIds, $date, $hours, $reason) {
        $this->emsPdo->beginTransaction();
        try {
            $checkStmt = $this->emsPdo->prepare("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[OvertimeApplications] WHERE emp_id = ? AND ot_date = ? AND status != 'Rejected'");
            $insStmt = $this->emsPdo->prepare("INSERT INTO [EmployeeManagementSystem].[dbo].[OvertimeApplications] (emp_id, ot_date, ot_hours, reason, status) VALUES (?, ?, ?, ?, 'Pending')");

            $added = 0; $dupes = 0;
            foreach ($empIds as $eid) {
                $checkStmt->execute([$eid, $date]);
                if ($checkStmt->fetchColumn() > 0) { $dupes++; continue; }
                $insStmt->execute([$eid, $date, $hours, $reason]);
                $added++;
            }
            $this->emsPdo->commit();
            return ['added' => $added, 'dupes' => $dupes];
        } catch (Exception $e) {
            $this->emsPdo->rollBack();
            throw $e;
        }
    }

    public function getOvertimeHistory($empId = null, $deptId = null) {
        if ($deptId) {
             $sql = "SELECT ot.*, e.first_name, e.last_name FROM [EmployeeManagementSystem].[dbo].[OvertimeApplications] ot JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON ot.emp_id = e.emp_id WHERE e.dept_id = ? ORDER BY ot.created_at DESC";
             $stmt = $this->emsPdo->prepare($sql);
             $stmt->execute([$deptId]);
        } else {
             $stmt = $this->emsPdo->prepare("SELECT * FROM [EmployeeManagementSystem].[dbo].[OvertimeApplications] WHERE emp_id = ? ORDER BY created_at DESC");
             $stmt->execute([$empId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingOvertimes() {
        return $this->emsPdo->query("SELECT ot.*, e.first_name, e.last_name, d.dept_name FROM [EmployeeManagementSystem].[dbo].[OvertimeApplications] ot JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON ot.emp_id = e.emp_id LEFT JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON e.dept_id = d.dept_id WHERE ot.status = 'Pending' OR ot.status = 'Submitted' ORDER BY ot.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateOvertimeStatus($otId, $status, $approverId) {
        $this->emsPdo->beginTransaction();
        try {
            $sql = "UPDATE [EmployeeManagementSystem].[dbo].[OvertimeApplications] SET status = ?, approved_by = ?, approval_date = GETDATE() WHERE ot_id = ? AND status != 'Approved'";
            $stmt = $this->emsPdo->prepare($sql);
            $stmt->execute([$status, $approverId, $otId]);

            if ($stmt->rowCount() > 0 && $status === 'Approved') {
                $getOT = $this->emsPdo->prepare("SELECT ot.ot_date, ot.ot_hours, e.ac_no FROM [EmployeeManagementSystem].[dbo].[OvertimeApplications] ot JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON ot.emp_id = e.emp_id WHERE ot.ot_id = ?");
                $getOT->execute([$otId]);
                $otData = $getOT->fetch(PDO::FETCH_ASSOC);

                if ($otData) {
                    $minutes = (int)((float)$otData['ot_hours'] * 60);
                    $this->schedulerPdo->prepare("UPDATE [SchedulerDB].[dbo].[FinalizedSchedule] SET Time_Out = DATEADD(minute, CAST(? AS INT), Time_Out) WHERE Ac_no = ? AND schedule_Date = ? AND Time_Out IS NOT NULL")->execute([$minutes, $otData['ac_no'], $otData['ot_date']]);
                }
            } elseif ($stmt->rowCount() == 0) {
                if ($this->emsPdo->inTransaction()) $this->emsPdo->rollBack();
                throw new Exception("Request already processed.");
            }
            $this->emsPdo->commit();
        } catch (Exception $e) { $this->emsPdo->rollBack(); throw $e; }
    }

    // ==========================================
    // SCHEDULE CHANGES
    // ==========================================
    public function applyScheduleChange($empId, $date, $code, $reason) {
        $check = $this->emsPdo->prepare("SELECT COUNT(*) FROM [EmployeeManagementSystem].[dbo].[ScheduleChangeRequests] WHERE emp_id = ? AND schedule_date = ? AND status = 'Pending'");
        $check->execute([$empId, $date]);
        if ($check->fetchColumn() > 0) throw new Exception("You already have a pending change request for this date.");

        $sql = "INSERT INTO [EmployeeManagementSystem].[dbo].[ScheduleChangeRequests] (emp_id, schedule_date, new_shift_code, reason, status) VALUES (?, ?, ?, ?, 'Pending')";
        return $this->emsPdo->prepare($sql)->execute([$empId, $date, $code, $reason]);
    }

    public function getScheduleChangeHistory($empId) {
        $stmt = $this->emsPdo->prepare("SELECT * FROM [EmployeeManagementSystem].[dbo].[ScheduleChangeRequests] WHERE emp_id = ? ORDER BY created_at DESC");
        $stmt->execute([$empId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingScheduleChanges() {
        return $this->emsPdo->query("SELECT r.*, e.first_name, e.last_name, d.dept_name FROM [EmployeeManagementSystem].[dbo].[ScheduleChangeRequests] r JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON r.emp_id = e.emp_id LEFT JOIN [EmployeeManagementSystem].[dbo].[Departments] d ON e.dept_id = d.dept_id WHERE r.status = 'Pending' ORDER BY r.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateScheduleChangeStatus($reqId, $status, $approverId) {
        $this->emsPdo->beginTransaction();
        try {
            $sql = "UPDATE [EmployeeManagementSystem].[dbo].[ScheduleChangeRequests] SET status = ?, approved_by = ?, approval_date = GETDATE() WHERE id = ? AND status != 'Approved'";
            $stmt = $this->emsPdo->prepare($sql);
            $stmt->execute([$status, $approverId, $reqId]);

            if ($stmt->rowCount() > 0 && $status === 'Approved') {
                $getReq = $this->emsPdo->prepare("SELECT r.schedule_date, r.new_shift_code, e.ac_no FROM [EmployeeManagementSystem].[dbo].[ScheduleChangeRequests] r JOIN [EmployeeManagementSystem].[dbo].[Employees] e ON r.emp_id = e.emp_id WHERE r.id = ?");
                $getReq->execute([$reqId]);
                $reqData = $getReq->fetch(PDO::FETCH_ASSOC);

                if ($reqData) {
                    $newCode = $reqData['new_shift_code'];
                    $acNo = $reqData['ac_no'];
                    $date = $reqData['schedule_date'];

                    if (in_array($newCode, ['OFF', 'FLEX', 'HOLIDAY OFF', 'LWOP', 'LWP'])) {
                        $this->schedulerPdo->prepare("UPDATE [SchedulerDB].[dbo].[FinalizedSchedule] SET Shift_code = ?, Time_In = NULL, Time_Out = NULL WHERE Ac_no = ? AND schedule_Date = ?")->execute([$newCode, $acNo, $date]);
                    } else {
                        $this->schedulerPdo->prepare("
                            UPDATE fs
                            SET fs.Shift_code = ?,
                                fs.Time_In = CAST(fs.schedule_Date AS DATETIME) + CAST(st.time_in AS DATETIME),
                                fs.Time_Out = CASE 
                                    WHEN st.time_out < st.time_in THEN DATEADD(day, 1, CAST(fs.schedule_Date AS DATETIME)) + CAST(st.time_out AS DATETIME)
                                    ELSE CAST(fs.schedule_Date AS DATETIME) + CAST(st.time_out AS DATETIME)
                                END
                            FROM [SchedulerDB].[dbo].[FinalizedSchedule] fs
                            INNER JOIN [SchedulerDB].[dbo].[shift_types] st ON st.shift_code = ?
                            WHERE fs.Ac_no = ? AND fs.schedule_Date = ?
                        ")->execute([$newCode, $newCode, $acNo, $date]);
                    }
                }
            } elseif ($stmt->rowCount() == 0) {
                 if ($this->emsPdo->inTransaction()) $this->emsPdo->rollBack();
                 throw new Exception("Request already processed.");
            }
            $this->emsPdo->commit();
        } catch (Exception $e) { $this->emsPdo->rollBack(); throw $e; }
    }

    // ==========================================
    // HELPERS
    // ==========================================
    private function getAcNo($empId) {
        $stmt = $this->emsPdo->prepare("SELECT ac_no FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE emp_id = ?");
        $stmt->execute([$empId]);
        return $stmt->fetchColumn();
    }

    private function mapShiftCode($type) {
        $map = [
            'DAYOFF' => 'OFF', 'FLEX' => 'FLEX', 'HOLIDAY OFF' => 'HOLIDAY OFF',
            'Leave Without Pay' => 'LWOP', 'Leave With Pay' => 'LWP',
            'Vacation Leave' => 'VL', 'Sick Leave' => 'SL',
            'Service Incentive Leave' => 'SIL'
        ];
        return $map[$type] ?? $type;
    }

    public function getEmployeesByDept($deptId) {
        $stmt = $this->emsPdo->prepare("SELECT emp_id, first_name, last_name FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE dept_id = ? AND employee_status = 'Active' ORDER BY last_name");
        $stmt->execute([$deptId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAllShifts() {
        return $this->schedulerPdo->query("SELECT shift_code, shift_name, time_in, time_out FROM [SchedulerDB].[dbo].[shift_types]")->fetchAll(PDO::FETCH_ASSOC);
    }
}
