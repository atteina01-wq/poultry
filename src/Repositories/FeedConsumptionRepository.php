<?php
namespace Poultry\Repositories;

use PDO;
use Exception;
use Poultry\Core\Database;

class FeedConsumptionRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(array $filters = []): array {
        $where = [];
        $params = [];

        if (!empty($filters['batch_id'])) {
            $where[] = 'fc.batch_id = ?';
            $params[] = (int)$filters['batch_id'];
        }

        if (!empty($filters['inventory_id'])) {
            $where[] = 'fc.inventory_id = ?';
            $params[] = (int)$filters['inventory_id'];
        }

        if (!empty($filters['category'])) {
            $where[] = 'i.category = ?';
            $params[] = $filters['category'];
        }

        if (!empty($filters['feed_stage'])) {
            $where[] = 'fc.feed_stage = ?';
            $params[] = $filters['feed_stage'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'fc.date >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'fc.date <= ?';
            $params[] = $filters['date_to'];
        }

        $sql = "SELECT fc.*, b.batch_code, b.flock_type, i.item_name, i.category as item_category, i.unit,
                       u.full_name as recorded_by_name
                FROM feed_consumption fc
                JOIN batches b ON fc.batch_id = b.batch_id
                JOIN inventory i ON fc.inventory_id = i.inventory_id
                LEFT JOIN users u ON fc.recorded_by = u.user_id";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY fc.date DESC, fc.consumption_id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function recordConsumption(int $batchId, int $inventoryId, float $qty, string $date, ?int $recordedBy = null, ?string $remarks = null, ?string $feedStage = null): int {
        $this->db->beginTransaction();
        try {
            // 1. Verify broiler batch
            $stmtBatch = $this->db->prepare("SELECT flock_type FROM batches WHERE batch_id = ?");
            $stmtBatch->execute([$batchId]);
            $flockType = $stmtBatch->fetchColumn();
            if (!$flockType) {
                throw new Exception("Batch #{$batchId} not found");
            }
            if (!empty($flockType) && strtolower($flockType) !== 'broiler') {
                throw new Exception("Feed consumption must be linked to a broiler batch");
            }

            // 2. Lock inventory row and check stock
            $stmtInv = $this->db->prepare("SELECT quantity, unit_cost, item_name, unit FROM inventory WHERE inventory_id = ? FOR UPDATE");
            $stmtInv->execute([$inventoryId]);
            $inv = $stmtInv->fetch();
            if (!$inv) {
                throw new Exception("Inventory item not found");
            }
            $available = (float)$inv['quantity'];
            if ($qty > $available) {
                throw new Exception("Insufficient stock for {$inv['item_name']}! Available: " . number_format($available, 2) . " {$inv['unit']}, Requested: " . number_format($qty, 2) . " {$inv['unit']}.");
            }

            // 3. Validate feed_stage if provided
            $validStages = ['Pre-Starter', 'Starter', 'Grower', 'Finisher'];
            $stageVal = null;
            if ($feedStage !== null && trim($feedStage) !== '') {
                $trimmed = trim($feedStage);
                foreach ($validStages as $vs) {
                    if (strcasecmp($vs, $trimmed) === 0) {
                        $stageVal = $vs;
                        break;
                    }
                }
                if ($stageVal === null) {
                    throw new Exception("Invalid feed stage: {$feedStage}. Must be Pre-Starter, Starter, Grower, or Finisher.");
                }
            }

            // 4. Insert record
            $sql = "INSERT INTO feed_consumption (
                        batch_id, inventory_id, feed_stage, quantity_used, date,
                        recorded_by, remarks, unit_cost_snapshot, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $batchId,
                $inventoryId,
                $stageVal,
                $qty,
                $date,
                $recordedBy,
                $remarks,
                $inv['unit_cost'] ?? 0.00
            ]);
            $consumptionId = (int)$this->db->lastInsertId();

            // 5. Deduct inventory
            $stmtUpdate = $this->db->prepare("
                UPDATE inventory SET
                    previous_quantity = quantity,
                    quantity = quantity - ?
                WHERE inventory_id = ?
            ");
            $stmtUpdate->execute([$qty, $inventoryId]);

            $this->db->commit();
            return $consumptionId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getAnalyticsByBatch(int $batchId): array {
        $stmt = $this->db->prepare("
            SELECT 
                i.item_name,
                i.unit,
                SUM(fc.quantity_used) as total_used,
                COUNT(fc.consumption_id) as feed_days,
                ROUND(AVG(fc.quantity_used), 2) as avg_daily_use
            FROM feed_consumption fc
            JOIN inventory i ON fc.inventory_id = i.inventory_id
            WHERE fc.batch_id = ?
            GROUP BY fc.inventory_id
        ");
        $stmt->execute([$batchId]);
        return $stmt->fetchAll();
    }
}
