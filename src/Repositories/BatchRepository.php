<?php
namespace Poultry\Repositories;

use PDO;
use Poultry\Core\Database;

class BatchRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(?string $flockType = null, ?string $status = null): array {
        $where = [];
        $params = [];

        if ($flockType !== null && $flockType !== '') {
            $where[] = 'flock_type = ?';
            $params[] = $flockType;
        }

        if ($status !== null && $status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $sql = "SELECT * FROM batches";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY arrival_date DESC, created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM batches WHERE batch_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByCode(string $code): ?array {
        $stmt = $this->db->prepare("SELECT * FROM batches WHERE batch_code = ? LIMIT 1");
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int {
        $arrivalDate = $data['arrival_date'] ?? date('Y-m-d');
        $productionDate = $data['production_date'] ?? $arrivalDate;
        $targetWeight = isset($data['target_weight_kg']) ? (float)$data['target_weight_kg'] : 1.80;
        $currentWeight = isset($data['current_weight_kg']) ? (float)$data['current_weight_kg'] : 0.00;

        $sql = "INSERT INTO batches (
                    batch_code, flock_type, arrival_date, production_date, initial_quantity, current_quantity,
                    breed, house_pen, supplier, delivery_receipt_no, chick_unit_cost, target_weight_kg, current_weight_kg, expected_harvest_date, notes, created_by, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['batch_code'],
            $data['flock_type'] ?? 'broiler',
            $arrivalDate,
            $productionDate,
            $data['initial_quantity'],
            $data['initial_quantity'], // starts at initial
            $data['breed'] ?? null,
            $data['house_pen'] ?? null,
            $data['supplier'] ?? null,
            $data['delivery_receipt_no'] ?? null,
            $data['chick_unit_cost'] ?? 38.00,
            $targetWeight,
            $currentWeight,
            $data['expected_harvest_date'] ?? null,
            $data['notes'] ?? null,
            $data['created_by'] ?? null,
            $data['status'] ?? 'active'
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $existing = $this->findById($id);
        if (!$existing) {
            return false;
        }

        $batchCode = $data['batch_code'] ?? $existing['batch_code'];
        $flockType = $data['flock_type'] ?? $existing['flock_type'];
        $arrivalDate = $data['arrival_date'] ?? $existing['arrival_date'];
        $productionDate = $data['production_date'] ?? ($existing['production_date'] ?? $arrivalDate);
        $initialQuantity = isset($data['initial_quantity']) ? (int)$data['initial_quantity'] : (int)$existing['initial_quantity'];
        $status = $data['status'] ?? $existing['status'];
        $breed = array_key_exists('breed', $data) ? $data['breed'] : $existing['breed'];
        $housePen = array_key_exists('house_pen', $data) ? $data['house_pen'] : $existing['house_pen'];
        $supplier = array_key_exists('supplier', $data) ? $data['supplier'] : $existing['supplier'];
        $deliveryReceiptNo = array_key_exists('delivery_receipt_no', $data) ? $data['delivery_receipt_no'] : ($existing['delivery_receipt_no'] ?? null);
        $chickUnitCost = array_key_exists('chick_unit_cost', $data) ? $data['chick_unit_cost'] : ($existing['chick_unit_cost'] ?? 38.00);
        $targetWeight = array_key_exists('target_weight_kg', $data) ? (float)$data['target_weight_kg'] : ($existing['target_weight_kg'] ?? 1.80);
        $currentWeight = array_key_exists('current_weight_kg', $data) ? (float)$data['current_weight_kg'] : ($existing['current_weight_kg'] ?? 0.00);
        $expectedHarvestDate = array_key_exists('expected_harvest_date', $data) ? $data['expected_harvest_date'] : $existing['expected_harvest_date'];
        $notes = array_key_exists('notes', $data) ? $data['notes'] : $existing['notes'];

        $sql = "UPDATE batches SET
                    batch_code = ?, flock_type = ?, arrival_date = ?, production_date = ?,
                    initial_quantity = ?, status = ?,
                    breed = ?, house_pen = ?, supplier = ?, delivery_receipt_no = ?, chick_unit_cost = ?,
                    target_weight_kg = ?, current_weight_kg = ?,
                    expected_harvest_date = ?, notes = ?
                WHERE batch_id = ?";
        $stmt = $this->db->prepare($sql);
        $res = $stmt->execute([
            $batchCode,
            $flockType,
            $arrivalDate,
            $productionDate,
            $initialQuantity,
            $status,
            $breed,
            $housePen,
            $supplier,
            $deliveryReceiptNo,
            $chickUnitCost,
            $targetWeight,
            $currentWeight,
            $expectedHarvestDate,
            $notes,
            $id
        ]);

        if ($res) {
            $this->syncHeadcount($id);
        }

        return $res;
    }

    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->db->prepare("UPDATE batches SET status = ? WHERE batch_id = ?");
        return $stmt->execute([$status, $id]);
    }

    public function syncHeadcount(int $batchId): int {
        $stmt = $this->db->prepare("SELECT initial_quantity FROM batches WHERE batch_id = ?");
        $stmt->execute([$batchId]);
        $initialQty = (int)$stmt->fetchColumn();

        $stmtM = $this->db->prepare("SELECT COALESCE(SUM(ABS(quantity_change)), 0) FROM mortality_records WHERE batch_id = ? AND quantity_change < 0");
        $stmtM->execute([$batchId]);
        $totalMortality = (int)$stmtM->fetchColumn();

        $stmtF = $this->db->prepare("SELECT COALESCE(SUM(accepted_quantity), 0) FROM reservations WHERE batch_id = ? AND status = 'fulfilled'");
        $stmtF->execute([$batchId]);
        $totalFulfilled = (int)$stmtF->fetchColumn();

        $newCurrent = max(0, $initialQty - $totalMortality - $totalFulfilled);

        $stmtU = $this->db->prepare("UPDATE batches SET current_quantity = ? WHERE batch_id = ?");
        $stmtU->execute([$newCurrent, $batchId]);

        if ($newCurrent <= 0) {
            if ($totalFulfilled > 0) {
                $this->updateStatus($batchId, 'harvested');
            } else {
                $this->updateStatus($batchId, 'closed');
            }
        }

        return $newCurrent;
    }

    public function adjustQuantity(int $id, int $qtyChange): bool {
        if ($qtyChange < 0) {
            $stmt = $this->db->prepare("SELECT current_quantity FROM batches WHERE batch_id = ?");
            $stmt->execute([$id]);
            $current = (int)$stmt->fetchColumn();
            if (abs($qtyChange) > $current) {
                throw new \Exception("Mortality loss count (" . abs($qtyChange) . ") cannot exceed current batch headcount (" . $current . ").");
            }
        }
        $stmt = $this->db->prepare("UPDATE batches SET current_quantity = GREATEST(0, current_quantity + ?) WHERE batch_id = ?");
        return $stmt->execute([$qtyChange, $id]);
    }
}
