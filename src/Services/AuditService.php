<?php
namespace App\Services;

use PDO;
use Exception;
use Throwable;

class AuditService
{
    private static ?PDO $pdo = null;
    private static ?string $requestId = null;

    private static array $sensitiveFields = [
        'password',
        'password_hash',
        'csrf_token',
        'bank_account_number',
        'esignature',
        'signature_blob'
    ];

    /**
     * Initialize AuditService with PDO connection
     */
    public static function init(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /**
     * Get or generate unique request ID
     */
    public static function getRequestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }
        return self::$requestId;
    }

    /**
     * Sanitize sensitive fields from data array
     */
    public static function sanitize(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $clean = [];
        foreach ($data as $key => $val) {
            $lower = strtolower((string)$key);
            if (in_array($lower, self::$sensitiveFields, true)) {
                $clean[$key] = '***REDACTED***';
            } elseif (is_array($val)) {
                $clean[$key] = self::sanitize($val);
            } else {
                $clean[$key] = $val;
            }
        }
        return $clean;
    }

    /**
     * Compute diff between before and after records (only changed fields)
     */
    public static function computeDiff(array $before, array $after): array
    {
        $diffBefore = [];
        $diffAfter = [];

        $allKeys = array_unique(array_merge(array_keys($before), array_keys($after)));
        foreach ($allKeys as $key) {
            $valBefore = $before[$key] ?? null;
            $valAfter = $after[$key] ?? null;

            // Treat float/int/string loose equivalence reasonably
            if ($valBefore !== $valAfter) {
                $diffBefore[$key] = $valBefore;
                $diffAfter[$key] = $valAfter;
            }
        }

        return [
            'before' => self::sanitize($diffBefore),
            'after' => self::sanitize($diffAfter)
        ];
    }

    /**
     * Record an audit log entry
     */
    public static function log(
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?int $actorUserId = null,
        ?string $actorRole = null
    ): void {
        try {
            if (self::$pdo === null) {
                global $pdo;
                if ($pdo instanceof PDO) {
                    self::$pdo = $pdo;
                } else {
                    return;
                }
            }

            // Derive actor from session if not explicitly provided
            if ($actorUserId === null && isset($_SESSION['user_id'])) {
                $actorUserId = (int)$_SESSION['user_id'];
            }

            if ($actorRole === null && isset($_SESSION['role'])) {
                $actorRole = (string)$_SESSION['role'];
            } elseif ($actorRole === null && isset($_SESSION['approval_role'])) {
                $actorRole = (string)$_SESSION['approval_role'];
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            }
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null;
            $requestId = self::getRequestId();

            $beforeJson = $before !== null ? json_encode(self::sanitize($before), JSON_UNESCAPED_UNICODE) : null;
            $afterJson = $after !== null ? json_encode(self::sanitize($after), JSON_UNESCAPED_UNICODE) : null;

            $sql = "INSERT INTO audit_logs (
                        timestamp, actor_user_id, actor_role, action, 
                        entity_type, entity_id, before_data, after_data, 
                        ip_address, user_agent, request_id
                    ) VALUES (
                        CURRENT_TIMESTAMP, :actor_user_id, :actor_role, :action,
                        :entity_type, :entity_id, :before_data, :after_data,
                        :ip_address, :user_agent, :request_id
                    )";

            $stmt = self::$pdo->prepare($sql);
            $stmt->execute([
                ':actor_user_id' => $actorUserId,
                ':actor_role'    => $actorRole,
                ':action'        => $action,
                ':entity_type'   => $entityType,
                ':entity_id'     => $entityId,
                ':before_data'   => $beforeJson,
                ':after_data'    => $afterJson,
                ':ip_address'    => $ip,
                ':user_agent'    => $userAgent,
                ':request_id'    => $requestId
            ]);
        } catch (Throwable $e) {
            // Audit logging failure must never crash the main application
            error_log("AuditService log error: " . $e->getMessage());
        }
    }

    /**
     * Query audit logs with pagination and filters
     */
    public static function query(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        if (self::$pdo === null) {
            global $pdo;
            self::$pdo = $pdo;
        }

        $conditions = [];
        $params = [];

        if (!empty($filters['action'])) {
            $conditions[] = "action = :action";
            $params[':action'] = $filters['action'];
        }

        if (!empty($filters['entity_type'])) {
            $conditions[] = "entity_type = :entity_type";
            $params[':entity_type'] = $filters['entity_type'];
        }

        if (!empty($filters['entity_id'])) {
            $conditions[] = "entity_id = :entity_id";
            $params[':entity_id'] = $filters['entity_id'];
        }

        if (!empty($filters['actor_user_id'])) {
            $conditions[] = "actor_user_id = :actor_user_id";
            $params[':actor_user_id'] = $filters['actor_user_id'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = "timestamp >= :date_from";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = "timestamp <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $conditions[] = "(action LIKE :search OR entity_type LIKE :search OR entity_id LIKE :search OR actor_role LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";

        // Total count
        $countSql = "SELECT COUNT(*) FROM audit_logs $whereClause";
        $countStmt = self::$pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Items with actor employee name if available
        $sql = "SELECT a.*, e.full_name as actor_name, e.ac_no as actor_ac_no 
                FROM audit_logs a
                LEFT JOIN employees e ON a.actor_user_id = e.emp_id
                $whereClause 
                ORDER BY a.id DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = self::$pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        return [
            'total' => $total,
            'items' => $items
        ];
    }
}
