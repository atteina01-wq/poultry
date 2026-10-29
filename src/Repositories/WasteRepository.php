<?php
namespace Poultry\Repositories;

use PDO;
use Exception;
use Poultry\Core\Database;

class WasteRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(array $filters = []): array {
        $where = [];
        $params = [];

        if (!empty($filters['waste_type'])) {
            $where[] = 'w.waste_type = ?';
            $params[] = $filters['waste_type'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'w.waste_date >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'w.waste_date <= ?';
            $params[] = $filters['date_to'];
        }

        $sql = "SELECT w.*, eg.grade_code, eg.grade_name, i.item_name, b.batch_code,
                       u.full_name as recorded_by_name, ex.amount as expense_amount
                FROM waste_records w
                LEFT JOIN egg_grades eg ON w.grade_id = eg.grade_id
                LEFT JOIN inventory i ON w.inventory_id = i.inventory_id
                LEFT JOIN batches b ON w.batch_id = b.batch_id
                LEFT JOIN users u ON w.recorded_by = u.user_id
                LEFT JOIN expenses ex ON w.expense_id = ex.expense_id";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY w.waste_date DESC, w.waste_id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function recordWaste(array $data): int {
        $this->db->beginTransaction();
        try {
            $wasteType = $data['waste_type']; // 'egg', 'supply', 'medicine', 'other'
            $gradeId = !empty($data['grade_id']) ? (int)$data['grade_id'] : null;
            $inventoryId = !empty($data['inventory_id']) ? (int)$data['inventory_id'] : null;
            $batchId = !empty($data['batch_id']) ? (int)$data['batch_id'] : null;
            $quantity = (float)$data['quantity'];
            $unit = $data['unit'] ?? 'pcs';
            $reason = $data['reason'] ?? 'broken';
            $estimatedCost = !empty($data['estimated_cost']) ? (float)$data['estimated_cost'] : null;
            $recordedBy = $data['recorded_by'] ?? null;
            $remarks = $data['remarks'] ?? null;
            $wasteDate = $data['waste_date'] ?? date('Y-m-d');

            // 1. Optionally create an expense record
            $expenseId = null;
            if ($estimatedCost && $estimatedCost > 0) {
                $stmtExp = $this->db->prepare("
                    INSERT INTO expenses (batch_id, category, amount, description, date, source_type, recorded_by, created_at)
                    VALUES (?, 'waste', ?, ?, ?, 'waste', ?, NOW())
                ");
                $stmtExp->execute([
                    $batchId,
                    $estimatedCost,
                    "Waste: {$quantity} {$unit} ({$reason})",
                    $wasteDate,
                    $recordedBy
                ]);
                $expenseId = (int)$this->db->lastInsertId();
            }

            // 2. Insert into waste_records
            $sql = "INSERT INTO waste_records (
                        waste_date, waste_type, grade_id, inventory_id, batch_id,
                        quantity, unit, reason, estimated_cost, expense_id, recorded_by, remarks, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $wasteDate,
                $wasteType,
                $gradeId,
                $inventoryId,
                $batchId,
                $quantity,
                $unit,
                $reason,
                $estimatedCost,
                $expenseId,
                $recordedBy,
                $remarks
            ]);
            $wasteId = (int)$this->db->lastInsertId();

            // 3. Deduct inventory
            if ($inventoryId) {
                $stmtInv = $this->db->prepare("
                    UPDATE inventory SET
                        previous_quantity = quantity,
                        quantity = quantity - ?
                    WHERE inventory_id = ?
                ");
                $stmtInv->execute([$quantity, $inventoryId]);
            }

            $this->db->commit();
            return $wasteId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
