<?php
namespace App\Controllers;

use App\Models\Timecard;
use App\Helpers\AppHelper;
use PDO;

class TimecardController
{
    protected $emsPdo;
    protected $schedulerPdo;
    protected $model;

    public function __construct(PDO $emsPdo, PDO $schedulerPdo)
    {
        $this->emsPdo = $emsPdo;
        $this->schedulerPdo = $schedulerPdo;
        $this->model = new Timecard($emsPdo, $schedulerPdo);
    }

    public function index()
    {
        // 1. Auth Headers
        if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
        
        $currentRole = $_SESSION['approval_role'] ?? 'Employee';
        $isHr = ($currentRole === 'HR');
        $myAcNo = $_SESSION['ac_no'];

        // 2. AJAX Handler for Manual Adjustments
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_log') {
            $this->handleUpdateLog($isHr);
        }

        // 3. Inputs
        $targetAcNo = $myAcNo;
        $targetName = $_SESSION['full_name'];

        if ($isHr && isset($_GET['search_ac']) && !empty($_GET['search_ac'])) {
            $targetAcNo = $_GET['search_ac'];
            $stmt = $this->emsPdo->prepare("SELECT first_name, last_name FROM [EmployeeManagementSystem].[dbo].[Employees] WHERE ac_no = ?");
            $stmt->execute([$targetAcNo]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) $targetName = $res['first_name'] . ' ' . $res['last_name'];
        }

        // 4. Cutoff
        $cutoffs = AppHelper::generateCutoffPeriods();
        $selectedCutoff = $_GET['cutoff'] ?? $cutoffs[0]['val'];
        list($startDate, $endDate) = explode('|', $selectedCutoff);

        // 5. Fetch DTR Data
        $data = $this->model->generateDtr($targetAcNo, $startDate, $endDate);

        // 6. Employee List (HR)
        $empList = [];
        if ($isHr) {
            $stmt = $this->emsPdo->query("SELECT ac_no, first_name, last_name FROM [EmployeeManagementSystem].[dbo].[Employees] ORDER BY last_name");
            $empList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 7. Render
        extract([
            'is_hr' => $isHr,
            'target_ac_no' => $targetAcNo,
            'target_name' => $targetName,
            'cutoffs' => $cutoffs,
            'selected_cutoff' => $selectedCutoff,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'data' => $data,
            'empList' => $empList
        ]);

        require __DIR__ . '/../Views/timecard_view.php';
    }

    private function handleUpdateLog($isHr)
    {
        if (!$isHr) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

        $targetAc = $_POST['ac_no'] ?? null;
        $targetDate = $_POST['date'] ?? null;
        $type = $_POST['type'] ?? null;
        $newTime = $_POST['new_time'] ?? null;

        if (empty($targetAc) || empty($targetDate) || empty($type)) {
            echo json_encode(['success' => false, 'message' => 'Missing data']); exit;
        }

        try {
            $this->model->updateManualLog($targetAc, $targetDate, $type, $newTime);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
