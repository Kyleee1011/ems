<?php
namespace App\Controllers;

use PDO;
use App\Models\CutoffModel;

class CutoffController
{
    protected $pdo;
    protected $model;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new CutoffModel($pdo);
    }

    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . baseUrl('login'));
            exit;
        }

        // Handle POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            // CSRF Check
            if (!\App\Utils\AppHelpers::validateCsrfToken($_POST['csrf_token'] ?? null)) {
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
                exit;
            }
            $action = $_POST['action'] ?? '';
            try {
                if ($action === 'save') {
                    $this->model->saveConfig([
                        'cutoff_1_start' => (int)($_POST['cutoff_1_start'] ?? 8),
                        'cutoff_1_end' => (int)($_POST['cutoff_1_end'] ?? 22),
                        'cutoff_2_start' => (int)($_POST['cutoff_2_start'] ?? 23),
                        'cutoff_2_end' => (int)($_POST['cutoff_2_end'] ?? 7),
                        'effective_date' => $_POST['effective_date'] ?? date('Y-m-01', strtotime('+1 month')),
                        'created_by' => $_SESSION['user_id'] ?? null,
                    ]);
                    echo json_encode(['success' => true, 'message' => 'Configuration saved.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
                }
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }

        // GET
        $currentConfig = $this->model->getActiveConfig(date('Y-m-d'));
        $history = $this->model->getConfigHistory();
        $nextMonth = date('Y-m-01', strtotime('+1 month'));

        require __DIR__ . '/../Views/cutoff_setup_view.php';
    }
}
