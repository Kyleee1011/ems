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

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getCredits($empId, $leaveType = 'Service Incentive Leave') {
        $credits = $this->getAllCredits($empId);
        switch ($leaveType) {
            case 'Service Incentive Leave':
                return (float)($credits['sil_credits'] ?? 0.0);
            case 'Vacation Leave':
                return (float)($credits['vl_credits'] ?? 0.0);
            case 'Sick Leave':
                return (float)($credits['sl_credits'] ?? 0.0);
            case 'Bereavement (Immediate Family)':
                return (float)($credits['bereavement_immediate_credits'] ?? 5.0);
            case 'Bereavement (Extended Family)':
                return (float)($credits['bereavement_extended_credits'] ?? 3.0);
            case 'Paternity Leave':
                return (float)($credits['paternity_credits'] ?? 7.0);
            case 'Maternity Leave':
                return (float)($credits['maternity_credits'] ?? 105.0);
            default:
                return null;
        }
    }

    public function getAllCredits($empId) {
        $stmt = $this->pdo->prepare("SELECT sil_credits, vl_credits, sl_credits, paternity_credits, maternity_credits, bereavement_immediate_credits, bereavement_extended_credits FROM employees WHERE emp_id = ?");
        $stmt->execute([$empId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [
                'sil_credits' => 0.0,
                'vl_credits' => 0.0,
                'sl_credits' => 0.0,
                'paternity_credits' => 7.0,
                'maternity_credits' => 105.0,
                'bereavement_immediate_credits' => 5.0,
                'bereavement_extended_credits' => 3.0
            ];
        }
        return $row;
    }

    /**
     * HR only: Update leave credits for an employee.
     */
    public function updateEmployeeCredits($empId, array $credits) {
        $sql = "UPDATE employees SET 
                sil_credits = ?,
                vl_credits = ?,
                sl_credits = ?,
                paternity_credits = ?,
                maternity_credits = ?,
                bereavement_immediate_credits = ?,
                bereavement_extended_credits = ?
                WHERE emp_id = ?";
        return $this->pdo->prepare($sql)->execute([
            (float)($credits['sil_credits'] ?? 0),
            (float)($credits['vl_credits'] ?? 0),
            (float)($credits['sl_credits'] ?? 0),
            (float)($credits['paternity_credits'] ?? 7),
            (float)($credits['maternity_credits'] ?? 105),
            (float)($credits['bereavement_immediate_credits'] ?? 5),
            (float)($credits['bereavement_extended_credits'] ?? 3),
            $empId
        ]);
    }

    public function getAllEmployeesWithCredits() {
        return $this->pdo->query("SELECT emp_id, first_name, last_name, ac_no, dept_id, sil_credits, vl_credits, sl_credits, paternity_credits, maternity_credits, bereavement_immediate_credits, bereavement_extended_credits FROM employees WHERE employee_status = 'Active' ORDER BY last_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function validateLeaveCredits($empId, $type, $days) {
        if ($type === 'Service Incentive Leave') {
            $credits = $this->getCredits($empId, $type);
            if ($credits <= 0) {
                throw new Exception("SIL cannot be selected without SIL credits (Insufficient SIL Credits).");
            }
            if ($credits < $days) {
                throw new Exception("Insufficient SIL credits for this duration. Available: " . number_format($credits, 1) . " day(s), Requested: {$days} day(s).");
            }
        } elseif ($type === 'Vacation Leave') {
            $credits = $this->getCredits($empId, $type);
            // If employee has a credit pool, validate against it
            if ($credits > 0 && $credits < $days) {
                throw new Exception("Insufficient Vacation Leave credits. Available: " . number_format($credits, 1) . " day(s), Requested: {$days} day(s).");
            }
        } elseif ($type === 'Sick Leave') {
            $credits = $this->getCredits($empId, $type);
            if ($credits > 0 && $credits < $days) {
                throw new Exception("Insufficient Sick Leave credits. Available: " . number_format($credits, 1) . " day(s), Requested: {$days} day(s).");
            }
        } elseif ($type === 'Bereavement (Immediate Family)') {
            if ($days > 5) {
                throw new Exception("Bereavement leave for immediate family is capped at maximum 5 days under Philippine standard.");
            }
        } elseif ($type === 'Bereavement (Extended Family)') {
            if ($days > 3) {
                throw new Exception("Bereavement leave for extended family is capped at maximum 3 days.");
            }
        } elseif ($type === 'Paternity Leave') {
            if ($days > 7) {
                throw new Exception("Paternity leave is capped at maximum 7 days under Republic Act 8187.");
            }
        } elseif ($type === 'Maternity Leave') {
            if ($days > 105) {
                throw new Exception("Maternity leave is capped at maximum 105 days under Republic Act 11210.");
            }
        }
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

        $days = $start->diff($end)->days + 1;
        $this->validateLeaveCredits($empId, $type, $days);

        // Enforce leave filing rules for VL and SL
        $ruleCheck = $this->checkLeaveRules($empId, $type, $startStr);
        if (!$ruleCheck['valid']) {
            throw new Exception("RULE_VIOLATION|" . $ruleCheck['rule'] . "|" . $ruleCheck['message'] . "|" . $ruleCheck['violation_note']);
        }

        $sql = "INSERT INTO leave_applications (emp_id, leave_type, start_date, end_date, reason, status, created_at) VALUES (?, ?, ?, ?, ?, 'Submitted', NOW())";
        return $this->pdo->prepare($sql)->execute([$empId, $type, $startStr, $endStr, $reason]);
    }

    /**
     * Submit a leave application as an appeal (when filing rules are violated).
     */
    public function applyLeaveAppeal($empId, $type, $startStr, $endStr, $reason, $appealReason, $attachmentPath, $violationNote) {
        $start = new DateTime($startStr);
        $end = new DateTime($endStr);

        $checkSql = "SELECT COUNT(*) FROM leave_applications
                     WHERE emp_id = ? AND status != 'Rejected' AND ((start_date <= ? AND end_date >= ?))";
        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->execute([$empId, $endStr, $startStr]);

        if ($checkStmt->fetchColumn() > 0) throw new Exception("You already have a pending or approved leave for these dates.");

        $days = $start->diff($end)->days + 1;
        $this->validateLeaveCredits($empId, $type, $days);

        $sql = "INSERT INTO leave_applications (emp_id, leave_type, start_date, end_date, reason, status, is_appeal, appeal_reason, attachment_path, violation_note, created_at)
                VALUES (?, ?, ?, ?, ?, 'Submitted', 1, ?, ?, ?, NOW())";
        return $this->pdo->prepare($sql)->execute([$empId, $type, $startStr, $endStr, $reason, $appealReason, $attachmentPath, $violationNote]);
    }

    /**
     * Check leave filing rules:
     *  - Vacation Leave: must be filed at least 3 days before start_date
     *  - Sick Leave: must be filed at least 2 hours before scheduled duty start
     *
     * @return array ['valid' => bool, 'rule' => string, 'message' => string, 'violation_note' => string]
     */
    public function checkLeaveRules($empId, $leaveType, $startDateStr) {
        $today = new DateTime('today');
        $startDate = new DateTime($startDateStr);

        // Vacation Leave — must be filed at least 3 calendar days before
        if ($leaveType === 'Vacation Leave') {
            $diffDays = (int)$today->diff($startDate)->format('%r%a');
            if ($diffDays < 3) {
                $label = $diffDays <= 0 ? 'same day or past date' : "$diffDays day(s) before leave";
                return [
                    'valid' => false,
                    'rule' => 'VL_3_DAYS',
                    'message' => 'Vacation Leave must be filed at least 3 days in advance. You may submit an appeal instead.',
                    'violation_note' => "Filed $label (Minimum: 3 days)"
                ];
            }
        }

        // Sick Leave — must be filed at least 2 hours before duty start
        if ($leaveType === 'Sick Leave') {
            $dutyStartTime = $this->getDutyStartTime($empId, $startDateStr);
            $dutyDateTime = strtotime("$startDateStr $dutyStartTime");
            $now = time();
            $diffSeconds = $dutyDateTime - $now;
            $diffHours = round($diffSeconds / 3600, 1);

            if ($diffSeconds < 7200) { // less than 2 hours
                if ($diffSeconds <= 0) {
                    $label = 'after duty already started';
                } else {
                    $label = number_format($diffHours, 1) . " hour(s) before duty";
                }
                return [
                    'valid' => false,
                    'rule' => 'SL_2_HOURS',
                    'message' => "Sick Leave must be filed at least 2 hours before duty start ($dutyStartTime). You may submit an appeal instead.",
                    'violation_note' => "Filed $label (Minimum: 2 hours before duty at $dutyStartTime)"
                ];
            }
        }

        return ['valid' => true, 'rule' => '', 'message' => '', 'violation_note' => ''];
    }

    /**
     * Look up the employee's scheduled duty start time for a given date.
     * Checks finalized_schedule first, then schedules+shift_types, defaults to 08:00.
     */
    private function getDutyStartTime($empId, $dateStr) {
        // Get ac_no for the employee
        $acNo = $this->getAcNo($empId);

        // 1. Check finalized_schedule
        if ($acNo) {
            $stmt = $this->pdo->prepare("SELECT time_in FROM finalized_schedule WHERE ac_no = ? AND schedule_date = ? AND time_in IS NOT NULL LIMIT 1");
            $stmt->execute([$acNo, $dateStr]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['time_in'])) {
                return $row['time_in'];
            }
        }

        // 2. Check schedules joined with shift_types
        $stmt2 = $this->pdo->prepare(
            "SELECT st.time_in FROM schedules es
             JOIN shift_types st ON es.shift_code = st.shift_code
             WHERE es.employee_id = ? AND es.schedule_date = ? AND st.time_in IS NOT NULL
             LIMIT 1"
        );
        $stmt2->execute([$empId, $dateStr]);
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($row2 && !empty($row2['time_in'])) {
            return $row2['time_in'];
        }

        // 3. Default fallback
        return '08:00:00';
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
         $sql = "SELECT l.*, e.first_name, e.last_name, d.dept_name, e.sil_credits, e.vl_credits, e.sl_credits, e.paternity_credits, e.maternity_credits, e.bereavement_immediate_credits, e.bereavement_extended_credits
               FROM leave_applications l
               JOIN employees e ON l.emp_id = e.emp_id
               LEFT JOIN departments d ON e.dept_id = d.dept_id
               WHERE l.status = 'Submitted' OR l.status = 'Pending'
               ORDER BY l.is_appeal DESC, l.created_at ASC";
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

            $daysCount = $start->diff($end)->days + 1;
            if ($leaveData['leave_type'] === 'Service Incentive Leave') {
                $this->pdo->prepare("UPDATE employees SET sil_credits = GREATEST(0, sil_credits - ?) WHERE emp_id = ?")->execute([$daysCount, $leaveData['emp_id']]);
            } elseif ($leaveData['leave_type'] === 'Vacation Leave') {
                $this->pdo->prepare("UPDATE employees SET vl_credits = GREATEST(0, vl_credits - ?) WHERE emp_id = ?")->execute([$daysCount, $leaveData['emp_id']]);
            } elseif ($leaveData['leave_type'] === 'Sick Leave') {
                $this->pdo->prepare("UPDATE employees SET sl_credits = GREATEST(0, sl_credits - ?) WHERE emp_id = ?")->execute([$daysCount, $leaveData['emp_id']]);
            } elseif ($leaveData['leave_type'] === 'Bereavement (Immediate Family)') {
                $this->pdo->prepare("UPDATE employees SET bereavement_immediate_credits = GREATEST(0, bereavement_immediate_credits - ?) WHERE emp_id = ?")->execute([$daysCount, $leaveData['emp_id']]);
            } elseif ($leaveData['leave_type'] === 'Bereavement (Extended Family)') {
                $this->pdo->prepare("UPDATE employees SET bereavement_extended_credits = GREATEST(0, bereavement_extended_credits - ?) WHERE emp_id = ?")->execute([$daysCount, $leaveData['emp_id']]);
            } elseif ($leaveData['leave_type'] === 'Paternity Leave') {
                $this->pdo->prepare("UPDATE employees SET paternity_credits = GREATEST(0, paternity_credits - ?) WHERE emp_id = ?")->execute([$daysCount, $leaveData['emp_id']]);
            } elseif ($leaveData['leave_type'] === 'Maternity Leave (105 Days Unpaid)' || $leaveData['leave_type'] === 'Maternity Leave') {
                $this->pdo->prepare("UPDATE employees SET maternity_credits = GREATEST(0, maternity_credits - ?) WHERE emp_id = ?")->execute([$daysCount, $leaveData['emp_id']]);
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
            'Service Incentive Leave' => 'SIL',
            'Bereavement (Immediate Family)' => 'BL',
            'Bereavement (Extended Family)' => 'BL',
            'Bereavement' => 'BL',
            'Paternity Leave' => 'PL',
            'Maternity Leave (105 Days Unpaid)' => 'ML',
            'Maternity Leave' => 'ML',
            'Emergency Leave' => 'EL'
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
