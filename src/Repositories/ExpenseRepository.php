<?php
namespace Poultry\Repositories;

use PDO;
use Poultry\Core\Database;

class ExpenseRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(array $filters = []): array {
        $where = [];
        $params = [];

        if (!empty($filters['category'])) {
            $where[] = 'e.category = ?';
            $params[] = $filters['category'];
        }

        if (!empty($filters['batch_id'])) {
            $where[] = 'e.batch_id = ?';
            $params[] = (int)$filters['batch_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'e.date >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'e.date <= ?';
            $params[] = $filters['date_to'];
        }

        $sql = "SELECT e.*, b.batch_code, i.item_name as inventory_item_name, u.full_name as recorded_by_name
                FROM expenses e
                LEFT JOIN batches b ON e.batch_id = b.batch_id
                LEFT JOIN inventory i ON e.inventory_id = i.inventory_id
                LEFT JOIN users u ON e.recorded_by = u.user_id";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY e.date DESC, e.expense_id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): int {
        $sql = "INSERT INTO expenses (
                    batch_id, category, amount, description, date,
                    source_type, source_id, inventory_id, recorded_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['batch_id'] ?? null,
            $data['category'] ?? 'other',
            $data['amount'],
            $data['description'] ?? null,
            $data['date'] ?? date('Y-m-d'),
            $data['source_type'] ?? 'manual',
            $data['source_id'] ?? null,
            $data['inventory_id'] ?? null,
            $data['recorded_by'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getSummaryByCategory(?string $dateFrom = null, ?string $dateTo = null): array {
        $where = [];
        $params = [];

        if ($dateFrom) {
            $where[] = 'date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where[] = 'date <= ?';
            $params[] = $dateTo;
        }

        $sql = "SELECT category, SUM(amount) as total_amount, COUNT(*) as record_count
                FROM expenses";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " GROUP BY category ORDER BY total_amount DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
