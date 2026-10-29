<?php
namespace Poultry\Repositories;

use PDO;
use Exception;
use Poultry\Core\Database;

class MortalityRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(array $filters = []): array {
        $where = [];
        $params = [];

        if (!empty($filters['batch_id'])) {
            $where[] = 'mr.batch_id = ?';
            $params[] = (int)$filters['batch_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'mr.date >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'mr.date <= ?';
            $params[] = $filters['date_to'];
        }

        $sql = "SELECT mr.*, b.batch_code, b.flock_type, u.full_name as recorded_by_name
                FROM mortality_records mr
                JOIN batches b ON mr.batch_id = b.batch_id
                LEFT JOIN users u ON mr.recorded_by = u.user_id";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY mr.date DESC, mr.mortality_id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function recordMortality(int $batchId, int $qtyLoss, string $reason, string $date, ?int $recordedBy = null): int {
        $this->db->beginTransaction();
        try {
            // Guard against excessive mortality: mortality cannot exceed current batch headcount
            $stmtCheck = $this->db->prepare("SELECT current_quantity FROM batches WHERE batch_id = ? FOR UPDATE");
            $stmtCheck->execute([$batchId]);
            $current = $stmtCheck->fetchColumn();
            if ($current === false) {
                throw new Exception("Batch #{$batchId} not found.");
            }
            if (abs($qtyLoss) > (int)$current) {
                throw new Exception("Mortality loss count (" . abs($qtyLoss) . ") cannot exceed current batch headcount (" . (int)$current . ").");
            }

            // Negative signed integer representing reduction
            $signedChange = -abs($qtyLoss);

            // 1. Insert into mortality_records
            $userId = $recordedBy ?? ($_SESSION['user_id'] ?? null);
            $sql = "INSERT INTO mortality_records (batch_id, date, quantity_change, reason, recorded_by, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$batchId, $date, $signedChange, $reason, $userId]);
            $mortalityId = (int)$this->db->lastInsertId();

            // 2. Deduct from batch current_quantity
            $stmtBatch = $this->db->prepare("UPDATE batches SET current_quantity = GREATEST(0, current_quantity + ?) WHERE batch_id = ?");
            $stmtBatch->execute([$signedChange, $batchId]);

            $this->db->commit();
            return $mortalityId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getSummary(string $period = 'daily', ?int $batchId = null): array {
        $where = "WHERE quantity_change < 0";
        $params = [];

        if ($batchId !== null) {
            $where .= " AND batch_id = ?";
            $params[] = $batchId;
        }

        $groupBy = "date";
        switch ($period) {
            case 'weekly':
                $groupBy = "YEARWEEK(date, 1)";
                break;
            case 'monthly':
                $groupBy = "DATE_FORMAT(date, '%Y-%m')";
                break;
            case 'yearly':
                $groupBy = "YEAR(date)";
                break;
            default: // daily
                $groupBy = "date";
                break;
        }

        $sql = "SELECT 
                    MIN(date) as period_start,
                    $groupBy as period_label,
                    ABS(SUM(quantity_change)) as total_loss,
                    COUNT(*) as occurrences
                FROM mortality_records
                $where
                GROUP BY $groupBy
                ORDER BY period_start DESC LIMIT 30";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
