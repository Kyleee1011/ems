<?php
namespace App\Api\Controllers;

use App\Api\Controllers\BaseApiController;
use App\Models\Timecard;
use PDO;

class TimecardApiController extends BaseApiController
{
    protected $model;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->model = new Timecard($pdo);
    }

    public function index()
    {
        // GET /timecard?start=YYYY-MM-DD&end=YYYY-MM-DD&emp_id=X
        $start = $_GET['start'] ?? date('Y-m-d');
        $end = $_GET['end'] ?? date('Y-m-d');
        
        // HR Check for emp_id override
        $targetId = $this->userId;
        if (isset($_GET['emp_id']) && ($this->userRole === 'HR' || $this->userRole === 'CEO')) {
            $targetId = $_GET['emp_id'];
        }

        // Fetch Logs
        $schedule = $this->model->getScheduleAndLogs($targetId, $start, $end);
        
        // Format response
        $this->jsonResponse([
            'employee_id' => $targetId,
            'period' => "$start to $end",
            'logs' => $schedule
        ]);
    }

    public function punch()
    {
        // POST /timecard/log { type: "IN", time: "08:00" } (Example, or real-time)
        // Note: The logic for punching is not fully migrated to Model yet (it was in timecard.php handler and AppHelper). 
        // For Phase 1 reuse, we might need to expose a method in Timecard model or just return mock for now if not ready.
        // Let's assume we want to just fetch logs for now as per previous refactor.
        
        $input = $this->getInput();
        if (!isset($input['type']) || !isset($input['time'])) {
            $this->sendError('Missing type or time');
        }

        // TODO: Implement actual Punch logic in Timecard Model. 
        // Currently Timecard model focuses on Fetching DTR. 
        // We will add a placeholder response.
        
        $this->jsonResponse(['message' => 'Punch logic to be implemented in Model'], 501);
    }
}
