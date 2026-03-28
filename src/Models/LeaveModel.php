<?php
namespace App\Models;

use PDO;

class LeaveModel
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPendingLeaveCount() {
        return $this->pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status = 'Submitted' OR status = 'Pending'")->fetchColumn();
    }

    public function getPendingLeaveList() {
        return $this->pdo->query("SELECT l.*, e.first_name, e.last_name FROM leave_applications l JOIN employees e ON l.emp_id = e.emp_id WHERE l.status IN ('Submitted', 'Pending') ORDER BY l.created_at ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function processLeaveApplication($leaveId, $decision, $approverId) {
        $status = ($decision === 'approve') ? 'Approved' : 'Rejected';
        $stmt = $this->pdo->prepare(
            "UPDATE leave_applications 
             SET status = ?, approved_by = ?, approval_date = NOW() 
             WHERE leave_id = ?"
        );
        return $stmt->execute([$status, $approverId, $leaveId]);
    }

    public function getPendingScheduleChanges() {
        return $this->pdo->query("SELECT r.*, e.first_name, e.last_name, d.dept_name 
                                 FROM schedule_change_requests r 
                                 JOIN employees e ON r.emp_id = e.emp_id 
                                 LEFT JOIN departments d ON e.dept_id = d.dept_id 
                                 WHERE r.status = 'Pending' 
                                 ORDER BY r.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateChangeScheduleStatus($reqId, $decision, $approverId) {
        $status = ($decision === 'approve') ? 'Approved' : 'Rejected';
        $this->pdo->beginTransaction();
        try {
            $sql = "UPDATE schedule_change_requests SET status = ?, approved_by = ?, approval_date = NOW() WHERE id = ? AND status != 'Approved'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$status, $approverId, $reqId]);

            if ($stmt->rowCount() > 0 && $status === 'Approved') {
                $getReq = $this->pdo->prepare("SELECT r.schedule_date, r.new_shift_code, e.ac_no, e.emp_id, e.dept_id FROM schedule_change_requests r JOIN employees e ON r.emp_id = e.emp_id WHERE r.id = ?");
                $getReq->execute([$reqId]);
                $reqData = $getReq->fetch(PDO::FETCH_ASSOC);

                if ($reqData) {
                    $newCode = $reqData['new_shift_code'];
                    $acNo = $reqData['ac_no'];
                    $empId = $reqData['emp_id'];
                    $date = $reqData['schedule_date'];

                    // 1. Get Shift Type Details
                    $stmtShift = $this->pdo->prepare("SELECT id, time_in, time_out, is_special FROM shift_types WHERE shift_code = ?");
                    $stmtShift->execute([$newCode]);
                    $shift = $stmtShift->fetch(PDO::FETCH_ASSOC);

                    if (!$shift) {
                        // Auto-create special shift type if not exists
                        $this->pdo->prepare("INSERT INTO shift_types (shift_code, shift_name, is_special) VALUES (?, ?, 1)")->execute([$newCode, $newCode]);
                        $shiftId = $this->pdo->lastInsertId();
                        $shift = ['id' => $shiftId, 'time_in' => null, 'time_out' => null, 'is_special' => 1];
                    }

                    // 2. Update Draft Schedules (schedules table)
                    $this->pdo->prepare("DELETE FROM schedules WHERE employee_id = ? AND schedule_date = ?")->execute([$empId, $date]);
                    
                    $stmtBatch = $this->pdo->prepare("SELECT cutoff_label, cutoff_start, cutoff_end FROM schedule_batches WHERE dept_id = ? AND ? BETWEEN cutoff_start AND cutoff_end ORDER BY batch_id DESC LIMIT 1");
                    $stmtBatch->execute([$reqData['dept_id'], $date]);
                    $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);
                    
                    $cutoffPeriod = $batch ? $batch['cutoff_start'] . '|' . $batch['cutoff_end'] : null;
                    $cutoffStart = $batch ? $batch['cutoff_start'] : null;
                    $cutoffEnd = $batch ? $batch['cutoff_end'] : null;

                    $this->pdo->prepare("INSERT INTO schedules (employee_id, schedule_date, shift_type_id, cutoff_period, cutoff_start_date, cutoff_end_date) VALUES (?, ?, ?, ?, ?, ?)")
                              ->execute([$empId, $date, $shift['id'], $cutoffPeriod, $cutoffStart, $cutoffEnd]);

                    // 3. Update Finalized Schedule
                    $this->pdo->prepare("DELETE FROM finalized_schedule WHERE ac_no = ? AND schedule_date = ?")->execute([$acNo, $date]);

                    $timeIn = null;
                    $timeOut = null;
                    if (!in_array($newCode, ['OFF', 'FLEX', 'HOLIDAY OFF', 'LWOP', 'LWP']) && $shift['time_in'] !== null) {
                        $timeIn = date('Y-m-d H:i:s', strtotime("$date {$shift['time_in']}"));
                        $timeOutRaw = strtotime("$date {$shift['time_out']}");
                        if (strtotime($shift['time_out']) < strtotime($shift['time_in'])) {
                            $timeOutRaw += 86400; // Next day
                        }
                        $timeOut = date('Y-m-d H:i:s', $timeOutRaw);
                    }

                    $stmtEmp = $this->pdo->prepare("SELECT e.first_name, e.last_name, d.dept_name FROM employees e LEFT JOIN departments d ON e.dept_id = d.dept_id WHERE e.emp_id = ?");
                    $stmtEmp->execute([$empId]);
                    $empRow = $stmtEmp->fetch(PDO::FETCH_ASSOC);
                    $fullName = $empRow['first_name'] . ' ' . $empRow['last_name'];
                    $deptName = $empRow['dept_name'];

                    $this->pdo->prepare("
                        INSERT INTO finalized_schedule (ac_no, schedule_date, shift_code, time_in, time_out, name, department)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ")->execute([$acNo, $date, $newCode, $timeIn, $timeOut, $fullName, $deptName]);
                }
            }
            $this->pdo->commit();
            return true;
        } catch (\Exception $e) { 
            if ($this->pdo->inTransaction()) $this->pdo->rollBack(); 
            throw $e; 
        }
    }
}
