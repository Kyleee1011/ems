<?php
namespace App\Models;

use PDO;

class ScheduleBatchModel
{
    protected $schedulerPdo;

    public function __construct(PDO $schedulerPdo)
    {
        $this->schedulerPdo = $schedulerPdo;
    }

    public function getBatchStatus($dept_id, $cutoff_start)
    {
        $stmtBatch = $this->schedulerPdo->prepare("SELECT batch_id, status FROM ScheduleBatches WHERE dept_id = ? AND cutoff_start = ?");
        $stmtBatch->execute([$dept_id, $cutoff_start]);
        return $stmtBatch->fetch(PDO::FETCH_ASSOC);
    }

    public function getPendingBatches($targetStatus) {
        $pending = [];
        if($targetStatus) {
            $stmt = $this->schedulerPdo->prepare("SELECT b.batch_id, b.cutoff_start, b.cutoff_end, b.dept_id, d.dept_name FROM SchedulerDB.dbo.ScheduleBatches b LEFT JOIN EmployeeManagementSystem.dbo.Departments d ON b.dept_id = d.dept_id WHERE b.status = ? ORDER BY b.updated_at DESC");
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
        return $this->schedulerPdo->query("SELECT COUNT(*) FROM ScheduleBatches WHERE status = 'Pending HR' OR status = 'Pending CEO'")->fetchColumn();
    }

    public function updateBatchStatus($batch_id, $new_status, $current_fullname, $user_role)
    {
        $this->schedulerPdo->prepare("UPDATE ScheduleBatches SET status = ? WHERE batch_id = ?")->execute([$new_status, $batch_id]);
        $this->schedulerPdo->prepare("INSERT INTO ScheduleHistory (batch_id, action, actor_name, actor_role, comments) VALUES (?, ?, ?, ?, ?)")
             ->execute([$batch_id, $new_status, $current_fullname, $user_role, 'Status update via Dashboard']);
    }

    public function deleteFinalizedSchedule($batch_id, $start, $end)
    {
        $this->schedulerPdo->prepare("DELETE FROM FinalizedSchedule WHERE Ac_no IN (SELECT e.ac_no FROM SchedulerDB.dbo.schedules es JOIN EmployeeManagementSystem.dbo.Employees e ON es.employee_id = e.emp_id WHERE es.batch_id = ?) AND schedule_Date BETWEEN ? AND ?")->execute([$batch_id, $start, $end]);
    }

    public function insertFinalizedSchedule($batch_id)
    {
        $sqlFin = "INSERT INTO FinalizedSchedule (Ac_no, Name, Department, schedule_Date, Shift_code, Time_In, Time_Out)
        SELECT e.ac_no, (e.first_name + ' ' + e.last_name), d.dept_name, es.schedule_date, es.shift_code,
            CASE WHEN st.time_in IS NULL THEN NULL ELSE CAST(es.schedule_date AS DATETIME) + CAST(st.time_in AS DATETIME) END,
            CASE WHEN st.time_out IS NULL THEN NULL 
                 WHEN st.time_out < st.time_in THEN DATEADD(day, 1, CAST(es.schedule_date AS DATETIME)) + CAST(st.time_out AS DATETIME)
                 ELSE CAST(es.schedule_date AS DATETIME) + CAST(st.time_out AS DATETIME) END
        FROM SchedulerDB.dbo.schedules es
        JOIN EmployeeManagementSystem.dbo.Employees e ON es.employee_id = e.emp_id
        JOIN EmployeeManagementSystem.dbo.Departments d ON e.dept_id = d.dept_id
        LEFT JOIN SchedulerDB.dbo.shift_types st ON es.shift_code = st.shift_code
        WHERE es.batch_id = ?";
        $this->schedulerPdo->prepare($sqlFin)->execute([$batch_id]);
    }
}
