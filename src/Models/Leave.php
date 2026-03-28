<?php
namespace App\Models;

use PDO;
use Exception;
use DateTime;
use DatePeriod;
use DateInterval;

class Leave
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getCredits($empId) {
        $stmt = $this->pdo->prepare("SELECT sil_credits FROM employees WHERE emp_id = ?");
        $stmt->execute([$empId]);
        return $stmt->fetchColumn() ?: 0.0;
    }

    // ==========================================
    // LEAVES
    // ==========================================
    public function applyLeave($empId, $type, $startStr, $endStr, $reason) {
        $start = new DateTime($startStr);
        $end = new DateTime($endStr);

        $checkSql = "SELECT COUNT(*) FROM leave_applications
                     WHERE emp_id = ? AND status != 'Rejected' AND ((start_date <= ? AND end_date >= ?))";
        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->execute([$empId, $endStr, $startStr]);

        if ($checkStmt->fetchColumn() > 0) throw new Exception("You already have a pending or approved leave for these dates.");

        if ($type === 'Service Incentive Leave') {
            $days = $start->diff($end)->days + 1;
            $credits = $this->getCredits($empId);
            if ($credits < $days) throw new Exception("Insufficient SIL Credits. Available: $credits, Requested: $days.");
        }

        $sql = "INSERT INTO leave_applications (emp_id, leave_type, start_date, end_date, reason, status, created_at) VALUES (?, ?, ?, ?, ?, 'Submitted', NOW())";
        return $this->pdo->prepare($sql)->execute([$empId, $type, $startStr, $endStr, $reason]);
    }

    public function getLeaveHistory($empId) {
        $stmt = $this->pdo->prepare("SELECT * FROM leave_applications WHERE emp_id = ? ORDER BY created_at DESC");
        $stmt->execute([$empId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConsumedLeaveHistory($empId, $year = null) {
        if (!$year) $year = date('Y');
        
        $sql = "SELECT leave_type, start_date, end_date, reason, DATEDIFF(end_date, start_date) + 1 as days_used
                FROM leave_applications 
                WHERE emp_id = ? 
                AND status = 'Approved' 
                AND YEAR(start_date) = ?
                ORDER BY start_date DESC";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$empId, $year]);
        
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totals = [];
        foreach ($history as $row) {
            $type = $row['leave_type'];
            if (!isset($totals[$type])) $totals[$type] = 0;
            $totals[$type] += (float)$row['days_used'];
        }
        
        return [
            'history' => $history,
            'totals' => $totals
        ];
    }

    public function getPendingLeaves() {
         $sql = "SELECT l.*, e.first_name, e.last_name, d.dept_name, e.sil_credits
               FROM leave_applications l
               JOIN employees e ON l.emp_id = e.emp_id
               LEFT JOIN departments d ON e.dept_id = d.dept_id
               WHERE l.status = 'Submitted' OR l.status = 'Pending'
               ORDER BY l.created_at ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateLeaveStatus($leaveId, $status, $approverId) {
        $this->pdo->beginTransaction();
        try {
            $sql = "UPDATE leave_applications SET status = ?, approved_by = ?, approval_date = NOW() WHERE leave_id = ? AND status != 'Approved'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$status, $approverId, $leaveId]);

            if ($stmt->rowCount() > 0 && $status === 'Approved') {
                $this->processApprovedLeave($leaveId);
            } elseif ($stmt->rowCount() == 0) {
                 if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                 throw new Exception("Request already processed.");
            }
            $this->pdo->commit(); 
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function processApprovedLeave($leaveId) {
        $getLeave = $this->pdo->prepare("SELECT emp_id, leave_type, start_date, end_date FROM leave_applications WHERE leave_id = ?");
        $getLeave->execute([$leaveId]);
        $leaveData = $getLeave->fetch(PDO::FETCH_ASSOC);

        if ($leaveData) {
            $start = new DateTime($leaveData['start_date']);
            $end = new DateTime($leaveData['end_date']);

            if ($leaveData['leave_type'] === 'Service Incentive Leave') {
                $daysCount = $start->diff($end)->days + 1;
                $this->pdo->prepare("UPDATE employees SET sil_credits = sil_credits - ? WHERE emp_id = ?")->execute([$daysCount, $leaveData['emp_id']]);
            }

            // Map Code
            $rawType = $leaveData['leave_type'];
            $mappedCode = $this->mapShiftCode($rawType);

            $end->modify('+1 day');
            $period = new DatePeriod($start, new DateInterval('P1D'), $end);

            $acNo = $this->getAcNo($leaveData['emp_id']);
            $upd = $this->pdo->prepare("UPDATE finalized_schedule SET shift_code = ?, time_in = NULL, time_out = NULL WHERE ac_no = ? AND schedule_date = ?");
            
            foreach ($period as $dt) {
                $upd->execute([$mappedCode, $acNo, $dt->format('Y-m-d')]);
            }
        }
    }

    // ==========================================
    // OVERTIME
    // ==========================================
    public function applyOvertime($empIds, $date, $hours, $reason) {
        $this->pdo->beginTransaction();
        try {
            $checkStmt = $this->pdo->prepare("SELECT COUNT(*) FROM overtime_applications WHERE emp_id = ? AND ot_date = ? AND status != 'Rejected'");
            $insStmt = $this->pdo->prepare("INSERT INTO overtime_applications (emp_id, ot_date, ot_hours, reason, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");

            $added = 0; $dupes = 0;
            foreach ($empIds as $eid) {
                $checkStmt->execute([$eid, $date]);
                if ($checkStmt->fetchColumn() > 0) { $dupes++; continue; }
                $insStmt->execute([$eid, $date, $hours, $reason]);
                $added++;
            }
            $this->pdo->commit();
            return ['added' => $added, 'dupes' => $dupes];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getOvertimeHistory($empId = null, $deptId = null) {
        if ($deptId) {
             $sql = "SELECT ot.*, e.first_name, e.last_name FROM overtime_applications ot JOIN employees e ON ot.emp_id = e.emp_id WHERE e.dept_id = ? ORDER BY ot.created_at DESC";
             $stmt = $this->pdo->prepare($sql);
             $stmt->execute([$deptId]);
        } else {
             $stmt = $this->pdo->prepare("SELECT * FROM overtime_applications WHERE emp_id = ? ORDER BY created_at DESC");
             $stmt->execute([$empId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingOvertimes() {
        return $this->pdo->query("SELECT ot.*, e.first_name, e.last_name, d.dept_name FROM overtime_applications ot JOIN employees e ON ot.emp_id = e.emp_id LEFT JOIN departments d ON e.dept_id = d.dept_id WHERE ot.status = 'Pending' OR ot.status = 'Submitted' ORDER BY ot.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateOvertimeStatus($otId, $status, $approverId) {
        $this->pdo->beginTransaction();
        try {
            $sql = "UPDATE overtime_applications SET status = ?, approved_by = ?, approval_date = NOW() WHERE ot_id = ? AND status != 'Approved'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$status, $approverId, $otId]);

            if ($stmt->rowCount() > 0 && $status === 'Approved') {
                $getOT = $this->pdo->prepare("SELECT ot.ot_date, ot.ot_hours, e.ac_no FROM overtime_applications ot JOIN employees e ON ot.emp_id = e.emp_id WHERE ot.ot_id = ?");
                $getOT->execute([$otId]);
                $otData = $getOT->fetch(PDO::FETCH_ASSOC);

                if ($otData) {
                    $minutes = (int)((float)$otData['ot_hours'] * 60);
                    $this->pdo->prepare("UPDATE finalized_schedule SET time_out = ADDTIME(time_out, SEC_TO_TIME(? * 60)) WHERE ac_no = ? AND schedule_date = ? AND time_out IS NOT NULL")->execute([$minutes, $otData['ac_no'], $otData['ot_date']]);
                }
            } elseif ($stmt->rowCount() == 0) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                throw new Exception("Request already processed.");
            }
            $this->pdo->commit();
        } catch (Exception $e) { $this->pdo->rollBack(); throw $e; }
    }

    // ==========================================
    // SCHEDULE CHANGES
    // ==========================================
    public function applyScheduleChange($empId, $date, $code, $reason) {
        $check = $this->pdo->prepare("SELECT COUNT(*) FROM schedule_change_requests WHERE emp_id = ? AND schedule_date = ? AND status = 'Pending'");
        $check->execute([$empId, $date]);
        if ($check->fetchColumn() > 0) throw new Exception("You already have a pending change request for this date.");

        $sql = "INSERT INTO schedule_change_requests (emp_id, schedule_date, new_shift_code, reason, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())";
        return $this->pdo->prepare($sql)->execute([$empId, $date, $code, $reason]);
    }

    public function getScheduleChangeHistory($empId) {
        $stmt = $this->pdo->prepare("SELECT * FROM schedule_change_requests WHERE emp_id = ? ORDER BY created_at DESC");
        $stmt->execute([$empId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingScheduleChanges() {
        return $this->pdo->query("SELECT r.*, e.first_name, e.last_name, d.dept_name FROM schedule_change_requests r JOIN employees e ON r.emp_id = e.emp_id LEFT JOIN departments d ON e.dept_id = d.dept_id WHERE r.status = 'Pending' ORDER BY r.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateScheduleChangeStatus($reqId, $status, $approverId) {
        $this->pdo->beginTransaction();
        try {
            $sql = "UPDATE schedule_change_requests SET status = ?, approved_by = ?, approval_date = NOW() WHERE id = ? AND status != 'Approved'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$status, $approverId, $reqId]);

            if ($stmt->rowCount() > 0 && $status === 'Approved') {
                $this->applyScheduleChangeToFinalized($reqId);
            } elseif ($stmt->rowCount() == 0) {
                 if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                 throw new Exception("Request already processed.");
            }
            $this->pdo->commit();
        } catch (Exception $e) { $this->pdo->rollBack(); throw $e; }
    }

    /**
     * Apply an approved schedule change to finalized_schedule.
     * Uses INSERT if no row exists, UPDATE if it does.
     */
    public function applyScheduleChangeToFinalized($reqId) {
        // Get request details with employee info for potential INSERT
        $getReq = $this->pdo->prepare(
            "SELECT r.schedule_date, r.new_shift_code, e.ac_no,
                    CONCAT(e.first_name, ' ', e.last_name) AS emp_name,
                    d.dept_name
             FROM schedule_change_requests r
             JOIN employees e ON r.emp_id = e.emp_id
             LEFT JOIN departments d ON e.dept_id = d.dept_id
             WHERE r.id = ?"
        );
        $getReq->execute([$reqId]);
        $reqData = $getReq->fetch(PDO::FETCH_ASSOC);

        if (!$reqData) return;

        $newCode = $reqData['new_shift_code'];
        $acNo = $reqData['ac_no'];
        $date = $reqData['schedule_date'];
        $empName = $reqData['emp_name'];
        $deptName = $reqData['dept_name'];

        // Determine the time_in/time_out for the new shift
        $timeIn = null;
        $timeOut = null;

        if (!in_array($newCode, ['OFF', 'FLEX', 'HOLIDAY OFF', 'LWOP', 'LWP'])) {
            $shiftStmt = $this->pdo->prepare("SELECT time_in, time_out FROM shift_types WHERE shift_code = ?");
            $shiftStmt->execute([$newCode]);
            $shift = $shiftStmt->fetch(PDO::FETCH_ASSOC);

            if ($shift) {
                $timeIn = $shift['time_in'];
                $timeOut = $shift['time_out'];
            }
        }

        // Check if a finalized_schedule row exists
        $checkStmt = $this->pdo->prepare("SELECT id FROM finalized_schedule WHERE ac_no = ? AND schedule_date = ?");
        $checkStmt->execute([$acNo, $date]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // UPDATE existing row
            $upd = $this->pdo->prepare(
                "UPDATE finalized_schedule SET shift_code = ?, time_in = ?, time_out = ?, updated_at = NOW() WHERE ac_no = ? AND schedule_date = ?"
            );
            $upd->execute([$newCode, $timeIn, $timeOut, $acNo, $date]);
        } else {
            // INSERT new row
            $ins = $this->pdo->prepare(
                "INSERT INTO finalized_schedule (ac_no, name, department, schedule_date, shift_code, time_in, time_out, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $ins->execute([$acNo, $empName, $deptName, $date, $newCode, $timeIn, $timeOut]);
        }
    }

    // ==========================================
    // HELPERS
    // ==========================================
    private function getAcNo($empId) {
        $stmt = $this->pdo->prepare("SELECT ac_no FROM employees WHERE emp_id = ?");
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
        $stmt = $this->pdo->prepare("SELECT emp_id, first_name, last_name FROM employees WHERE dept_id = ? AND employee_status = 'Active' ORDER BY last_name");
        $stmt->execute([$deptId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAllShifts() {
        return $this->pdo->query("SELECT shift_code, shift_name, time_in, time_out FROM shift_types")->fetchAll(PDO::FETCH_ASSOC);
    }
}
