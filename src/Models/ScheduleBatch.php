<?php
namespace App\Models;

use PDO;
use Exception;
use DateTime;

class ScheduleBatch
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPendingBatches($status) {
        $pending = [];
        $stmt = $this->pdo->prepare("SELECT b.batch_id, b.cutoff_start, b.cutoff_end, b.dept_id, d.dept_name FROM schedule_batches b LEFT JOIN departments d ON b.dept_id = d.dept_id WHERE b.status = ? ORDER BY b.updated_at DESC");
        $stmt->execute([$status]);
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cS = is_object($row['cutoff_start']) ? $row['cutoff_start']->format('Y-m-d') : $row['cutoff_start'];
            $cE = is_object($row['cutoff_end']) ? $row['cutoff_end']->format('Y-m-d') : $row['cutoff_end'];
            $pending[] = [
                'dept_name' => $row['dept_name'],
                'range_val' => $cS . '|' . $cE,
                'dept_id' => $row['dept_id'],
                'display' => date('M d', strtotime($cS)) . ' - ' . date('M d, Y', strtotime($cE))
            ];
        }
        return $pending;
    }

    public function getBatchStatus($deptId, $start) {
        $stmt = $this->pdo->prepare("SELECT batch_id, status FROM schedule_batches WHERE dept_id = ? AND cutoff_start = ?");
        $stmt->execute([$deptId, $start]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getSchedules($deptId, $start, $end, $batchId = 0, $isApproved = false) {
        $schedules = [];
        
        if ($isApproved) {
            $sql = "SELECT e.emp_id as employee_id, fs.schedule_date, fs.shift_code, fs.time_in, fs.time_out 
                    FROM finalized_schedule fs
                    JOIN employees e ON fs.ac_no = e.ac_no
                    WHERE e.dept_id = ? AND fs.schedule_date BETWEEN ? AND ?";
             $stmt = $this->pdo->prepare($sql);
             $stmt->execute([$deptId, $start, $end]);
        } elseif ($batchId) {
             $sql = "SELECT es.employee_id, es.schedule_date, es.shift_code, st.time_in, st.time_out 
                     FROM schedules es
                     LEFT JOIN shift_types st ON es.shift_code = st.shift_code
                     WHERE es.batch_id = ? AND es.schedule_date BETWEEN ? AND ?";
             $stmt = $this->pdo->prepare($sql);
             $stmt->execute([$batchId, $start, $end]);
        }

        if (isset($stmt)) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $date = is_object($row['schedule_date'] ?? $row['schedule_Date']) ? ($row['schedule_date'] ?? $row['schedule_Date'])->format('Y-m-d') : ($row['schedule_date'] ?? $row['schedule_Date']);
                $tInObj = $row['Time_In'] ?? $row['time_in'] ?? null;
                $tOutObj = $row['Time_Out'] ?? $row['time_out'] ?? null;
                $code = $row['Shift_code'] ?? $row['shift_code'] ?? '-';
                
                $tIn = ($tInObj instanceof DateTime) ? $tInObj->format('H:i') : (string)$tInObj;
                $tOut = ($tOutObj instanceof DateTime) ? $tOutObj->format('H:i') : (string)$tOutObj;
                $displayTime = ($tIn && $tOut) ? substr($tIn,0,5) . '-' . substr($tOut,0,5) : '-';
                if(in_array($code, ['OFF','FLEX','HOLIDAY OFF','LWOP','LWP'])) $displayTime = $code;

                $schedules[$row['employee_id']][$date] = ['code' => $code, 'time' => $displayTime];
            }
        }
        return $schedules;
    }

    public function updateStatus($deptId, $start, $end, $status, $actorName, $actorRole) {
        $batchData = $this->getBatchStatus($deptId, $start);
        if ($batchData) {
            $batchId = $batchData['batch_id'];
            $this->pdo->prepare("UPDATE schedule_batches SET status = ? WHERE batch_id = ?")->execute([$status, $batchId]);
            
            $this->pdo->prepare("INSERT INTO schedule_history (batch_id, action, actor_name, actor_role, comments) VALUES (?, ?, ?, ?, ?)")
                 ->execute([$batchId, $status, $actorName, $actorRole, 'Status update via Dashboard']);

            if ($status === 'Approved') {
                $this->finalizeSchedule($batchId, $start, $end);
            }
        }
    }

    private function finalizeSchedule($batchId, $start, $end) {
        // Clear existing
        $this->pdo->prepare("DELETE FROM finalized_schedule WHERE ac_no IN (SELECT e.ac_no FROM schedules es JOIN employees e ON es.employee_id = e.emp_id WHERE es.batch_id = ?) AND schedule_date BETWEEN ? AND ?")->execute([$batchId, $start, $end]);
        
        // Insert new
        $sql = "INSERT INTO finalized_schedule (ac_no, name, department, schedule_date, shift_code, time_in, time_out)
                SELECT e.ac_no, CONCAT(e.first_name, ' ', e.last_name), d.dept_name, es.schedule_date, es.shift_code,
                    CASE WHEN st.time_in IS NULL THEN NULL ELSE TIMESTAMP(es.schedule_date, st.time_in) END,
                    CASE WHEN st.time_out IS NULL THEN NULL 
                         WHEN st.time_out < st.time_in THEN TIMESTAMP(DATE_ADD(es.schedule_date, INTERVAL 1 DAY), st.time_out)
                         ELSE TIMESTAMP(es.schedule_date, st.time_out) END
                FROM schedules es
                JOIN employees e ON es.employee_id = e.emp_id
                JOIN departments d ON e.dept_id = d.dept_id
                LEFT JOIN shift_types st ON es.shift_code = st.shift_code
                WHERE es.batch_id = ?";
        $this->pdo->prepare($sql)->execute([$batchId]);
    }
}
