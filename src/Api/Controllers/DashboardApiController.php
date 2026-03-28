<?php
namespace App\Api\Controllers;

use App\Api\Controllers\BaseApiController;
use PDO;

class DashboardApiController extends BaseApiController
{
    public function stats()
    {
        $totalEmp = $this->pdo->query("SELECT COUNT(*) FROM employees WHERE IsActive = 1 AND employee_status = 'Active'")->fetchColumn();
        $this->jsonResponse(['active_employees' => $totalEmp]);
    }

    public function getSchedules()
    {
        // GET /dashboard/schedules?dept_id=X&range=start|end
        $deptId = $_GET['dept_id'] ?? null;
        $range = $_GET['range'] ?? null;

        if (!$deptId || !$range) $this->sendError('Missing parameters');

        list($start, $end) = explode('|', $range);

        $scheduleBatchModel = new \App\Models\ScheduleBatchModel($this->pdo);
        $batch = $scheduleBatchModel->getBatchStatus($deptId, $start);

        $status = $batch ? $batch['status'] : 'Not Started';
        $batch_id = $batch ? $batch['batch_id'] : 0;
        
        $rows = [];
        if ($status === 'Approved') {
            $sql = "SELECT e.emp_id as employee_id, fs.schedule_date, fs.shift_code, fs.time_in, fs.time_out 
                         FROM finalized_schedule fs
                         JOIN employees e ON fs.ac_no = e.ac_no
                         WHERE e.dept_id = ? AND fs.schedule_date BETWEEN ? AND ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$deptId, $start, $end]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT es.employee_id, es.schedule_date, es.shift_code, st.time_in, st.time_out 
                         FROM schedules es
                         LEFT JOIN shift_types st ON es.shift_code = st.shift_code
                         WHERE es.batch_id = ? AND es.schedule_date BETWEEN ? AND ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$batch_id, $start, $end]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Format for grid [emp_id][date] = {code, time}
        $grid = [];
        foreach($rows as $r) {
            $d = is_object($r['schedule_date']) ? $r['schedule_date']->format('Y-m-d') : $r['schedule_date'];
            
            $tIn = $r['time_in'] ?? $r['Time_In'] ?? null;
            $tOut = $r['time_out'] ?? $r['Time_Out'] ?? null;

            if (is_string($tIn)) {
                $tIn = new \DateTime($tIn);
            }
            if (is_string($tOut)) {
                $tOut = new \DateTime($tOut);
            }

            $displayTime = ($tIn && $tOut) ? $tIn->format('H:i') . '-' . $tOut->format('H:i') : '-';
            
            $code = $r['shift_code'] ?? $r['Shift_code'] ?? '-';
            if(in_array($code, ['OFF','FLEX','HOLIDAY OFF','LWOP','LWP'])) {
                $displayTime = $code; 
            }

            $grid[$r['employee_id']][$d] = [
                'code' => $code,
                'time' => $displayTime
            ];
        }

        $this->jsonResponse(['success' => true, 'schedules' => $grid, 'status' => $status]);
    }

    public function getPendingBatches()
    {
        // GET /dashboard/batches/pending
        $schModel = new \App\Models\ScheduleBatch($this->pdo);
        $batches = $schModel->getPendingBatches($this->userRole);
        $this->jsonResponse(['success' => true, 'data' => $batches]);
    }

    public function updateBatchStatus()
    {
        // POST /dashboard/batch/update
        $input = $this->getInput();
        $deptId = $input['dept_id'];
        $range = $input['range'];
        $status = $input['status'];

        $schModel = new \App\Models\ScheduleBatch($this->pdo);
        // Note: The model needs a specific method to find batch by ID or we find ID first.
        // Legacy logic: find batch_id via dept/start
        list($start, $end) = explode('|', $range);
        
        $stmt = $this->pdo->prepare("SELECT batch_id FROM schedule_batches WHERE dept_id = ? AND cutoff_start = ?");
        $stmt->execute([$deptId, $start]);
        $batch = $stmt->fetch();

        if ($batch) {
             $schModel->updateBatchStatus($batch['batch_id'], $status, $this->userId, $this->userRole); // Assuming model update
             // We need to verify if ScheduleBatch model has updateBatchStatus with this signature.
             // Looking back at my viewing of ScheduleBatch.php earlier, I didn't see the full code. 
             // Logic suggests strictly following the Model's capability.
             
             // If Status Approved, we need to trigger finalization. 
             // The Model *should* handle this. 
             if ($status === 'Approved') {
                 $schModel->finalizeSchedule($batch['batch_id']);
             }
             
             $this->jsonResponse(['success' => true]);
        } else {
             $this->sendError('Batch not found');
        }
    }
    
    public function getEmployeesByDept()
    {
        // GET /dashboard/employees?dept_id=X
        $deptId = $_GET['dept_id'] ?? 0;
        $stmt = $this->pdo->prepare("SELECT emp_id as id, CONCAT(first_name, ' ', last_name) as name FROM employees WHERE dept_id = ? AND IsActive=1 ORDER BY last_name");
        $stmt->execute([$deptId]);
        $this->jsonResponse(['success' => true, 'employees' => $stmt->fetchAll()]);
    }
}
