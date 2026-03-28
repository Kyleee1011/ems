<?php
namespace App\Api\Controllers;

use App\Api\Controllers\BaseApiController;
use App\Models\Leave;
use PDO;

class LeaveApiController extends BaseApiController
{
    protected $model;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->model = new Leave($pdo);
    }

    public function index()
    {
        // GET /leave?emp_id=X (Optional HR)
        $targetId = $this->userId;
        if (isset($_GET['emp_id']) && ($this->userRole === 'HR' || $this->userRole === 'CEO')) {
            $targetId = $_GET['emp_id'];
        }

        $history = $this->model->getLeaveHistory($targetId);
        $this->jsonResponse(['logs' => $history]);
    }

    public function apply()
    {
        // POST /leave/apply { leave_type, start_date, end_date, reason }
        $input = $this->getInput();
        
        // Basic validation
        if(empty($input['leave_type']) || empty($input['start_date']) || empty($input['end_date'])) {
            $this->sendError('Missing fields');
        }

        try {
            $this->model->applyLeave($this->userId, $input);
            $this->jsonResponse(['message' => 'Leave application submitted successfully']);
        } catch (\Exception $e) {
            $this->sendError($e->getMessage());
        }
    }
}
