<?php
namespace Poultry\Repositories;

use PDO;
use Poultry\Core\Database;

class NotificationRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(?int $userId = null, ?string $status = null, int $limit = 50): array {
        $where = [];
        $params = [];

        if ($userId !== null) {
            $where[] = '(user_id = ? OR user_id IS NULL)';
            $params[] = $userId;
        }

        if ($status !== null) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $sql = "SELECT * FROM notifications";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY created_at DESC LIMIT ?";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $idx => $val) {
            $stmt->bindValue($idx + 1, $val);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): int {
        $sql = "INSERT INTO notifications (
                    user_id, message, type, entity_type, entity_id, severity, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'unread', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['user_id'] ?? null,
            $data['message'],
            $data['type'] ?? 'general',
            $data['entity_type'] ?? null,
            $data['entity_id'] ?? null,
            $data['severity'] ?? 'info'
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function markAsRead(int $id): bool {
        $stmt = $this->db->prepare("UPDATE notifications SET status = 'read', read_at = NOW() WHERE notification_id = ?");
        return $stmt->execute([$id]);
    }

    public function markAllAsRead(?int $userId = null): bool {
        if ($userId) {
            $stmt = $this->db->prepare("UPDATE notifications SET status = 'read', read_at = NOW() WHERE user_id = ? AND status = 'unread'");
            return $stmt->execute([$userId]);
        }
        $stmt = $this->db->query("UPDATE notifications SET status = 'read', read_at = NOW() WHERE status = 'unread'");
        return (bool)$stmt;
    }
}
