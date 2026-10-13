<?php
namespace App\Services;

use PDO;
use Throwable;

class NotificationService
{
    private static ?PDO $pdo = null;

    public static function init(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    private static function getPdo(): ?PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        global $pdo;
        if ($pdo instanceof PDO) {
            self::$pdo = $pdo;
            return self::$pdo;
        }
        return null;
    }

    /**
     * Send notification to a single user
     */
    public static function notify(
        int $recipientUserId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null
    ): bool {
        try {
            $pdo = self::getPdo();
            if (!$pdo) return false;

            $sql = "INSERT INTO notifications (recipient_user_id, type, title, body, link, is_read, created_at)
                    VALUES (:recipient_user_id, :type, :title, :body, :link, 0, CURRENT_TIMESTAMP)";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                ':recipient_user_id' => $recipientUserId,
                ':type'              => $type,
                ':title'             => $title,
                ':body'              => $body,
                ':link'              => $link
            ]);
        } catch (Throwable $e) {
            error_log("NotificationService::notify failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to multiple users
     */
    public static function notifyMany(
        array $recipientUserIds,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null
    ): int {
        $count = 0;
        $uniqueIds = array_unique(array_filter($recipientUserIds));
        foreach ($uniqueIds as $userId) {
            if (self::notify((int)$userId, $type, $title, $body, $link)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Send notification to all active users with a specified role
     * Roles: 'HR', 'CEO', 'DeptHead', 'Employee'
     * Optional $deptId restricts to DeptHead of that specific department
     */
    public static function notifyRole(
        string $role,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
        ?int $deptId = null
    ): int {
        try {
            $pdo = self::getPdo();
            if (!$pdo) return 0;

            $sql = "SELECT emp_id FROM employees 
                    WHERE (approval_role = :role1 OR (:role2 = 'HR' AND dept_id = 5))
                      AND IsActive = 1 AND employee_status = 'Active'";
            $params = [':role1' => $role, ':role2' => $role];

            if ($deptId !== null) {
                $sql .= " AND dept_id = :dept_id";
                $params[':dept_id'] = $deptId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($userIds)) return 0;
            return self::notifyMany($userIds, $type, $title, $body, $link);
        } catch (Throwable $e) {
            error_log("NotificationService::notifyRole failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetch paginated notifications for a user
     */
    public static function getForUser(
        int $userId,
        int $limit = 20,
        int $offset = 0,
        ?bool $unreadOnly = null
    ): array {
        try {
            $pdo = self::getPdo();
            if (!$pdo) return ['items' => [], 'total' => 0, 'unread_count' => 0];

            $where = "WHERE recipient_user_id = :user_id";
            $params = [':user_id' => $userId];

            if ($unreadOnly === true) {
                $where .= " AND is_read = 0";
            }

            // Total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications $where");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Unread count
            $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_user_id = :user_id AND is_read = 0");
            $unreadStmt->execute([':user_id' => $userId]);
            $unreadCount = (int)$unreadStmt->fetchColumn();

            // Items
            $sql = "SELECT * FROM notifications $where ORDER BY id DESC LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll();

            return [
                'items' => $items,
                'total' => $total,
                'unread_count' => $unreadCount
            ];
        } catch (Throwable $e) {
            error_log("NotificationService::getForUser failed: " . $e->getMessage());
            return ['items' => [], 'total' => 0, 'unread_count' => 0];
        }
    }

    /**
     * Get unread notification count
     */
    public static function getUnreadCount(int $userId): int
    {
        try {
            $pdo = self::getPdo();
            if (!$pdo) return 0;

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_user_id = :user_id AND is_read = 0");
            $stmt->execute([':user_id' => $userId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log("NotificationService::getUnreadCount failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mark a single notification as read
     */
    public static function markRead(int $notificationId, int $userId): bool
    {
        try {
            $pdo = self::getPdo();
            if (!$pdo) return false;

            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = CURRENT_TIMESTAMP WHERE id = :id AND recipient_user_id = :user_id");
            return $stmt->execute([
                ':id'      => $notificationId,
                ':user_id' => $userId
            ]);
        } catch (Throwable $e) {
            error_log("NotificationService::markRead failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all notifications as read for a user
     */
    public static function markAllRead(int $userId): int
    {
        try {
            $pdo = self::getPdo();
            if (!$pdo) return 0;

            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = CURRENT_TIMESTAMP WHERE recipient_user_id = :user_id AND is_read = 0");
            $stmt->execute([':user_id' => $userId]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log("NotificationService::markAllRead failed: " . $e->getMessage());
            return 0;
        }
    }
}
