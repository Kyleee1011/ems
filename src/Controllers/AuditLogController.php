<?php
namespace App\Controllers;

use PDO;
use App\Services\AuditService;

class AuditLogController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . baseUrl('login'));
            exit;
        }

        $userRole = $_SESSION['approval_role'] ?? 'Employee';
        $isAuthorized = (strcasecmp($userRole, 'CEO') === 0 || strcasecmp($userRole, 'HR') === 0);

        if (!$isAuthorized) {
            header('Location: ' . baseUrl('home'));
            exit;
        }

        $action = $_GET['action'] ?? 'view';

        // CSV Export
        if ($action === 'export_csv') {
            $this->exportCsv();
            return;
        }

        // Filters
        $filters = [
            'action'        => !empty($_GET['filter_action']) ? trim($_GET['filter_action']) : null,
            'entity_type'   => !empty($_GET['filter_entity']) ? trim($_GET['filter_entity']) : null,
            'actor_role'    => !empty($_GET['filter_role']) ? trim($_GET['filter_role']) : null,
            'date_from'     => !empty($_GET['date_from']) ? trim($_GET['date_from']) : null,
            'date_to'       => !empty($_GET['date_to']) ? trim($_GET['date_to']) : null,
            'search'        => !empty($_GET['search']) ? trim($_GET['search']) : null,
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $result = AuditService::query($filters, $limit, $offset);
        $logs = $result['items'];
        $totalLogs = $result['total'];
        $totalPages = ceil($totalLogs / $limit);

        // Fetch distinct actions and entities for filter dropdowns
        $distinctActions = $this->pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
        $distinctEntities = $this->pdo->query("SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);

        require BASE_PATH . '/src/Views/audit_log_view.php';
    }

    private function exportCsv(): void
    {
        ob_end_clean();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audit_logs_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Timestamp', 'Actor ID', 'Actor Role', 'Action', 'Entity Type', 'Entity ID', 'Before Data', 'After Data', 'IP Address', 'User Agent', 'Request ID']);

        $stmt = $this->pdo->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 5000");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $row['id'],
                $row['timestamp'],
                $row['actor_user_id'],
                $row['actor_role'],
                $row['action'],
                $row['entity_type'],
                $row['entity_id'],
                $row['before_data'],
                $row['after_data'],
                $row['ip_address'],
                $row['user_agent'],
                $row['request_id']
            ]);
        }

        fclose($out);
        exit;
    }
}
