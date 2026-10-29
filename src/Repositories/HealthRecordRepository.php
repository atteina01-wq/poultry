<?php
namespace Poultry\Repositories;

use PDO;
use Exception;
use Poultry\Core\Database;

class HealthRecordRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(array $filters = []): array {
        $where = [];
        $params = [];

        if (!empty($filters['batch_id'])) {
            $where[] = 'hr.batch_id = ?';
            $params[] = (int)$filters['batch_id'];
        }

        if (!empty($filters['type'])) {
            $where[] = 'hr.type = ?';
            $params[] = $filters['type'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'hr.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'hr.date >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'hr.date <= ?';
            $params[] = $filters['date_to'];
        }

        $sql = "SELECT hr.*, b.batch_code, b.flock_type, i.item_name as inventory_item_name, i.unit,
                       u.full_name as recorded_by_name
                FROM health_records hr
                JOIN batches b ON hr.batch_id = b.batch_id
                LEFT JOIN inventory i ON hr.inventory_id = i.inventory_id
                LEFT JOIN users u ON hr.recorded_by = u.user_id";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY hr.date DESC, hr.health_id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT hr.*, b.batch_code, b.flock_type, i.item_name as inventory_item_name, i.unit,
                   u.full_name as recorded_by_name
            FROM health_records hr
            JOIN batches b ON hr.batch_id = b.batch_id
            LEFT JOIN inventory i ON hr.inventory_id = i.inventory_id
            LEFT JOIN users u ON hr.recorded_by = u.user_id
            WHERE hr.health_id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByBatch(int $batchId): array {
        return $this->getAll(['batch_id' => $batchId]);
    }

    public function updateStatus(int $healthId, string $status): bool {
        $validStatuses = ['scheduled', 'in_progress', 'completed', 'cancelled'];
        $status = strtolower($status);
        if (!in_array($status, $validStatuses, true)) {
            throw new Exception("Invalid health record status: {$status}");
        }
        $stmt = $this->db->prepare("UPDATE health_records SET status = ? WHERE health_id = ?");
        return $stmt->execute([$status, $healthId]);
    }

    public function recordHealth(array $data): int {
        $this->db->beginTransaction();
        try {
            $batchId = (int)$data['batch_id'];
            $type = $data['type'] ?? 'treatment';
            $medicineName = $data['medicine_name'];
            $quantityUsed = (float)$data['quantity_used'];
            $date = $data['date'] ?? date('Y-m-d');
            $notes = $data['notes'] ?? null;
            $illnessObservation = $data['illness_observation'] ?? null;
            $doctorConsultation = $data['doctor_consultation'] ?? null;
            $diagnosis = $data['diagnosis'] ?? null;
            $status = !empty($data['status']) ? strtolower($data['status']) : 'completed';
            $inventoryId = !empty($data['inventory_id']) ? (int)$data['inventory_id'] : null;
            $nextDueDate = $data['next_due_date'] ?? null;
            $recordedBy = $data['recorded_by'] ?? null;

            // Ensure batch exists and is broiler
            $stmtB = $this->db->prepare("SELECT flock_type FROM batches WHERE batch_id = ?");
            $stmtB->execute([$batchId]);
            $flockType = $stmtB->fetchColumn();
            if (!$flockType) {
                throw new Exception("Batch #{$batchId} does not exist");
            }
            if (!empty($flockType) && strtolower($flockType) !== 'broiler') {
                throw new Exception("Health records must be linked to a broiler batch");
            }

            // If inventory_id not passed directly, try resolving by medicine name
            if (!$inventoryId && !empty($medicineName)) {
                $stmtFind = $this->db->prepare("SELECT inventory_id FROM inventory WHERE item_name = ? AND category = 'medicine' LIMIT 1");
                $stmtFind->execute([$medicineName]);
                $foundId = $stmtFind->fetchColumn();
                if ($foundId) {
                    $inventoryId = (int)$foundId;
                }
            }

            // 1. Insert into health_records
            $sql = "INSERT INTO health_records (
                        batch_id, inventory_id, type, medicine_name, illness_observation,
                        doctor_consultation, diagnosis, quantity_used, date, notes,
                        status, recorded_by, next_due_date, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $batchId,
                $inventoryId,
                $type,
                $medicineName,
                $illnessObservation,
                $doctorConsultation,
                $diagnosis,
                $quantityUsed,
                $date,
                $notes,
                $status,
                $recordedBy,
                $nextDueDate
            ]);
            $healthId = (int)$this->db->lastInsertId();

            // 2. Also log in activities for backward compatibility
            $sqlAct = "INSERT INTO activities (batch_id, type, item_name, quantity, date, remarks)
                       VALUES (?, 'Health', ?, ?, ?, ?)";
            $stmtAct = $this->db->prepare($sqlAct);
            $stmtAct->execute([$batchId, $medicineName, $quantityUsed, $date, $notes]);

            // 3. Deduct stock if inventory_id is mapped
            if ($inventoryId) {
                $stmtDeduct = $this->db->prepare("
                    UPDATE inventory SET
                        previous_quantity = quantity,
                        quantity = quantity - ?
                    WHERE inventory_id = ?
                ");
                $stmtDeduct->execute([$quantityUsed, $inventoryId]);
            }

            $this->db->commit();
            return $healthId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
