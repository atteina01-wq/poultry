<?php
namespace Poultry\Repositories;

use PDO;
use Poultry\Core\Database;

class AuditLogRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function log(
        ?int $userId,
        string $action,
        string $tableName,
        ?int $recordId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): bool {
        try {
            $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $userId,
                $action,
                $tableName,
                $recordId,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function getAll(array $filters = [], int $limit = 100, int $offset = 0): array {
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = ?';
            $params[] = (int)$filters['user_id'];
        }

        if (!empty($filters['table_name'])) {
            $where[] = 'al.table_name = ?';
            $params[] = $filters['table_name'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'al.action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'al.created_at >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'al.created_at <= ?';
            $params[] = $filters['date_to'];
        }

        $sql = "SELECT al.*, u.username, u.full_name, u.role
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.user_id";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY al.created_at DESC, al.log_id DESC LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $idx => $val) {
            $stmt->bindValue($idx + 1, $val);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
