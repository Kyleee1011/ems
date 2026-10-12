<?php
namespace App\Controllers;

use PDO;
use App\Services\NotificationService;
use App\Utils\AppHelpers;

class NotificationController
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
            if ($this->isAjax()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            header('Location: ' . baseUrl('login'));
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $action = $_GET['action'] ?? $_POST['action'] ?? 'page';

        switch ($action) {
            case 'list':
                $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 15;
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $offset = ($page - 1) * $limit;
                $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === '1';

                $data = NotificationService::getForUser($userId, $limit, $offset, $unreadOnly ? true : null);
                $data['current_page'] = $page;
                $data['limit'] = $limit;

                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'data' => $data]);
                exit;

            case 'unread_count':
                $count = NotificationService::getUnreadCount($userId);
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'count' => $count]);
                exit;

            case 'mark_read':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    http_response_code(405);
                    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
                    exit;
                }

                $notifId = (int)($_POST['notification_id'] ?? 0);
                if ($notifId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
                    exit;
                }

                $ok = NotificationService::markRead($notifId, $userId);
                header('Content-Type: application/json');
                echo json_encode(['success' => $ok]);
                exit;

            case 'mark_all_read':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    http_response_code(405);
                    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
                    exit;
                }

                $count = NotificationService::markAllRead($userId);
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'marked' => $count]);
                exit;

            case 'page':
            default:
                // View all notifications page
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = 20;
                $offset = ($page - 1) * $limit;
                $unreadOnly = isset($_GET['filter']) && $_GET['filter'] === 'unread';

                $notifData = NotificationService::getForUser($userId, $limit, $offset, $unreadOnly ? true : null);
                $notifications = $notifData['items'];
                $totalCount = $notifData['total'];
                $totalPages = ceil($totalCount / $limit);
                $unreadCount = $notifData['unread_count'];

                require BASE_PATH . '/src/Views/notifications_view.php';
                exit;
        }
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
            isset($_GET['action']) || isset($_POST['action']);
    }
}
