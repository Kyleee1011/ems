<?php
namespace App\Models;

use PDO;

class ScheduleBatchModel
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getBatchStatus($dept_id, $cutoff_start)
    {
        $stmtBatch = $this->pdo->prepare("SELECT batch_id, status FROM schedule_batches WHERE dept_id = ? AND cutoff_start = ?");
        $stmtBatch->execute([$dept_id, $cutoff_start]);
        return $stmtBatch->fetch(PDO::FETCH_ASSOC);
    }

    public function getPendingBatches($targetStatus) {
        $pending = [];
        if($targetStatus) {
            $stmt = $this->pdo->prepare("SELECT b.batch_id, b.cutoff_start, b.cutoff_end, b.dept_id, d.dept_name FROM schedule_batches b LEFT JOIN departments d ON b.dept_id = d.dept_id WHERE b.status = ? ORDER BY b.updated_at DESC");
            $stmt->execute([$targetStatus]);
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
        }
        return $pending;
    }

    public function getPendingScheduleCount() {
        return $this->pdo->query("SELECT COUNT(*) FROM schedule_batches WHERE status = 'Pending HR' OR status = 'Pending CEO'")->fetchColumn();
    }

    public function updateBatchStatus($batch_id, $new_status, $current_fullname, $user_role)
    {
        $this->pdo->prepare("UPDATE schedule_batches SET status = ? WHERE batch_id = ?")->execute([$new_status, $batch_id]);
        $this->pdo->prepare("INSERT INTO schedule_history (batch_id, action, actor_name, actor_role, comments) VALUES (?, ?, ?, ?, ?)")
             ->execute([$batch_id, $new_status, $current_fullname, $user_role, 'Status update via Dashboard']);
    }

    public function deleteFinalizedSchedule($batch_id, $start, $end)
    {
        $this->pdo->prepare("DELETE FROM finalized_schedule WHERE ac_no IN (SELECT e.ac_no FROM schedules es JOIN employees e ON es.employee_id = e.emp_id WHERE es.batch_id = ?) AND schedule_date BETWEEN ? AND ?")->execute([$batch_id, $start, $end]);
    }

    public function insertFinalizedSchedule($batch_id)
    {
        $sqlFin = "INSERT INTO finalized_schedule (ac_no, name, department, schedule_date, shift_code, time_in, time_out)
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
        $this->pdo->prepare($sqlFin)->execute([$batch_id]);
    }
}
