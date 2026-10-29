<?php
namespace Poultry\Repositories;

use PDO;
use Exception;
use Poultry\Core\Database;

class ReservationRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(array $filters = []): array {
        $where = [];
        $params = [];

        if (!empty($filters['customer_id'])) {
            $where[] = 'r.customer_id = ?';
            $params[] = (int)$filters['customer_id'];
        }

        if (!empty($filters['batch_id'])) {
            $where[] = 'r.batch_id = ?';
            $params[] = (int)$filters['batch_id'];
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'r.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['pickup_date'])) {
            $where[] = 'r.pickup_date = ?';
            $params[] = $filters['pickup_date'];
        }

        $sql = "SELECT r.*, c.name as customer_name, c.customer_code, c.phone as customer_phone, c.address as customer_address,
                       b.batch_code, b.current_weight_kg, b.target_weight_kg as batch_target_weight, b.arrival_date,
                       h.house_name, h.capacity as house_capacity,
                       COALESCE(r.quantity_birds, 0) AS total_quantity_reserved,
                       COALESCE(r.quantity_birds, 0) AS reserved_birds,
                       r.accepted_quantity AS total_quantity_fulfilled,
                       r.actual_price,
                       r.receipt_number,
                       0 AS items_count,
                       u.full_name as created_by_name
                FROM reservations r
                JOIN customers c ON r.customer_id = c.customer_id
                LEFT JOIN batches b ON r.batch_id = b.batch_id
                LEFT JOIN houses h ON r.house_id = h.house_id
                LEFT JOIN users u ON r.created_by = u.user_id";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " GROUP BY r.reservation_id ORDER BY r.pickup_date ASC, r.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT r.*, c.name as customer_name, c.customer_code, c.phone as customer_phone, c.address as customer_address,
                   b.batch_code, b.current_weight_kg, b.target_weight_kg as batch_target_weight, b.arrival_date,
                   h.house_name, h.capacity as house_capacity,
                   COALESCE(r.quantity_birds, 0) AS reserved_birds,
                   (COALESCE(r.total_amount, 0.00) - COALESCE(r.advance_payment, 0.00)) as remaining_balance,
                   r.actual_price,
                   r.receipt_number,
                   u.full_name as created_by_name, NULL as converted_order_number
            FROM reservations r
            JOIN customers c ON r.customer_id = c.customer_id
            LEFT JOIN batches b ON r.batch_id = b.batch_id
            LEFT JOIN houses h ON r.house_id = h.house_id
            LEFT JOIN users u ON r.created_by = u.user_id
            WHERE r.reservation_id = ? LIMIT 1
        ");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($res) {
            $res['items'] = [];
            if (empty($res['total_quantity_reserved'])) {
                $res['total_quantity_reserved'] = !empty($res['quantity_birds']) ? (int)$res['quantity_birds'] : 0;
            }
        }

        return $res ?: null;
    }

    public function getReservationItems(int $reservationId): array {
        return [];
    }

    public function generateReservationNumber(string $date): string {
        $prefix = 'RES-' . date('Ymd', strtotime($date)) . '-';
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM reservations WHERE reservation_number LIKE ?");
        $stmt->execute([$prefix . '%']);
        $seq = (int)$stmt->fetchColumn() + 1;
        return $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
    }

    public function createBroilerReservation(array $data): int {
        $pickupDate = !empty($data['pickup_date']) ? $data['pickup_date'] : date('Y-m-d', strtotime('+3 days'));
        $resNumber = $this->generateReservationNumber($pickupDate);
        $houseId = !empty($data['house_id']) ? (int)$data['house_id'] : null;
        $targetWeight = isset($data['target_weight_per_bird']) && $data['target_weight_per_bird'] !== '' ? (float)$data['target_weight_per_bird'] : 1.80;

        $breed = !empty($data['breed']) ? trim($data['breed']) : 'Cobb 500';
        $chickCost = isset($data['chick_cost']) && $data['chick_cost'] !== '' ? (float)$data['chick_cost'] : 38.00;

        $workflowStage = !empty($data['workflow_stage']) 
            ? $data['workflow_stage'] 
            : (!empty($data['batch_id']) ? 'growing' : 'pending_approval');

        $sql = "INSERT INTO reservations (
                    reservation_number, customer_id, batch_id, house_id, quantity_birds, weight_kg, target_weight_per_bird, breed,
                    unit_price, chick_cost, total_amount, advance_payment,
                    pickup_date, status, workflow_stage, notes, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $resNumber,
            (int)$data['customer_id'],
            !empty($data['batch_id']) ? (int)$data['batch_id'] : null,
            $houseId,
            !empty($data['quantity_birds']) ? (int)$data['quantity_birds'] : null,
            isset($data['weight_kg']) && $data['weight_kg'] !== '' ? (float)$data['weight_kg'] : null,
            $targetWeight,
            $breed,
            isset($data['unit_price']) && $data['unit_price'] !== '' ? (float)$data['unit_price'] : null,
            $chickCost,
            isset($data['total_amount']) && $data['total_amount'] !== '' ? (float)$data['total_amount'] : 0.00,
            isset($data['advance_payment']) && $data['advance_payment'] !== '' ? (float)$data['advance_payment'] : 0.00,
            $pickupDate,
            $workflowStage,
            !empty($data['notes']) ? trim($data['notes']) : null,
            !empty($data['created_by']) ? (int)$data['created_by'] : null
        ]);

        $resId = (int)$this->db->lastInsertId();

        if ($houseId > 0) {
            try {
                // Check if house still has remaining vacant capacity
                $stmtHCheck = $this->db->prepare("
                    SELECT capacity,
                        COALESCE((SELECT SUM(r.quantity_birds) FROM reservations r WHERE r.house_id = ? AND r.status = 'active'), 0) as reserved_birds,
                        COALESCE((SELECT SUM(b.current_quantity) FROM batches b WHERE (b.house_pen = houses.house_name OR b.batch_id = houses.current_batch_id) AND b.status = 'active' AND b.current_quantity > 0), 0) as flock_birds
                    FROM houses WHERE house_id = ?
                ");
                $stmtHCheck->execute([$houseId, $houseId]);
                $hRow = $stmtHCheck->fetch(\PDO::FETCH_ASSOC);
                $cap = (int)($hRow['capacity'] ?? 5000);
                $used = max((int)($hRow['reserved_birds'] ?? 0), (int)($hRow['flock_birds'] ?? 0));
                
                $newStatus = ($used >= $cap) ? 'reserved' : 'available';
                $stmtH = $this->db->prepare("UPDATE houses SET status = ?, notes = CONCAT(COALESCE(notes, ''), ' [Booked by Res #', ?, ']') WHERE house_id = ?");
                $stmtH->execute([$newStatus, $resNumber, $houseId]);
            } catch (\Exception $ex) {}
        }



        return $resId;
    }

    public function recordFulfillment(int $id, array $data): bool {
        $actualDate = !empty($data['actual_pickup_date']) ? $data['actual_pickup_date'] : date('Y-m-d');
        $acceptedQty = isset($data['accepted_quantity']) ? (int)$data['accepted_quantity'] : 0;
        $rejectedQty = isset($data['rejected_quantity']) ? (int)$data['rejected_quantity'] : 0;
        $reason = !empty($data['rejection_reason']) ? trim($data['rejection_reason']) : null;
        $status = !empty($data['status']) ? $data['status'] : 'fulfilled';

        $actualWeight = isset($data['actual_weight_kg']) && $data['actual_weight_kg'] !== '' ? (float)$data['actual_weight_kg'] : null;
        $actualPrice = isset($data['actual_price']) && $data['actual_price'] !== '' ? (float)$data['actual_price'] : null;
        $receiptNumber = !empty($data['receipt_number']) ? trim($data['receipt_number']) : null;
        $notes = !empty($data['notes']) ? trim($data['notes']) : null;

        $stmt = $this->db->prepare("
            UPDATE reservations SET
                actual_pickup_date = ?,
                accepted_quantity = ?,
                actual_weight_kg = ?,
                actual_price = ?,
                receipt_number = ?,
                rejected_quantity = ?,
                rejection_reason = ?,
                notes = COALESCE(?, notes),
                status = ?,
                updated_at = NOW()
            WHERE reservation_id = ?
        ");
        $res = $stmt->execute([
            $actualDate, $acceptedQty, $actualWeight, $actualPrice, $receiptNumber, 
            $rejectedQty, $reason, $notes, $status, $id
        ]);



        return $res;
    }

    public function updateReservation(int $id, array $data): bool {
        $houseId = !empty($data['house_id']) ? (int)$data['house_id'] : null;
        $targetWeight = isset($data['target_weight_per_bird']) && $data['target_weight_per_bird'] !== '' ? (float)$data['target_weight_per_bird'] : 1.80;

        $customerId = (int)($data['customer_id'] ?? 0);
        if ($customerId <= 0) {
            $customerId = (int)$this->db->query("SELECT customer_id FROM customers WHERE is_active = 1 LIMIT 1")->fetchColumn();
        } else {
            $chk = $this->db->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
            $chk->execute([$customerId]);
            if (!$chk->fetchColumn()) {
                $customerId = (int)$this->db->query("SELECT customer_id FROM customers WHERE is_active = 1 LIMIT 1")->fetchColumn();
            }
        }

        $breed = !empty($data['breed']) ? trim($data['breed']) : null;
        $chickCost = isset($data['chick_cost']) && $data['chick_cost'] !== '' ? (float)$data['chick_cost'] : 38.00;

        $stmt = $this->db->prepare("
            UPDATE reservations SET
                customer_id = ?,
                batch_id = ?,
                house_id = ?,
                quantity_birds = ?,
                weight_kg = ?,
                target_weight_per_bird = ?,
                breed = COALESCE(?, breed),
                unit_price = ?,
                chick_cost = ?,
                total_amount = ?,
                advance_payment = ?,
                pickup_date = ?,
                notes = ?,
                updated_at = NOW()
            WHERE reservation_id = ?
        ");
        $res = $stmt->execute([
            $customerId,
            !empty($data['batch_id']) ? (int)$data['batch_id'] : null,
            $houseId,
            !empty($data['quantity_birds']) ? (int)$data['quantity_birds'] : null,
            isset($data['weight_kg']) && $data['weight_kg'] !== '' ? (float)$data['weight_kg'] : null,
            $targetWeight,
            $breed,
            isset($data['unit_price']) && $data['unit_price'] !== '' ? (float)$data['unit_price'] : null,
            $chickCost,
            isset($data['total_amount']) && $data['total_amount'] !== '' ? (float)$data['total_amount'] : 0.00,
            isset($data['advance_payment']) && $data['advance_payment'] !== '' ? (float)$data['advance_payment'] : 0.00,
            !empty($data['pickup_date']) ? $data['pickup_date'] : date('Y-m-d'),
            !empty($data['notes']) ? trim($data['notes']) : null,
            $id
        ]);



        return $res;
    }




    public function dispatchReservation(int $id, ?string $notes = null, ?int $userId = null): bool {
        $stmt = $this->db->prepare("
            UPDATE reservations SET
                is_dispatched = 1,
                dispatched_at = NOW(),
                dispatched_by = ?,
                notes = COALESCE(?, notes),
                updated_at = NOW()
            WHERE reservation_id = ?
        ");
        return $stmt->execute([$userId, $notes, $id]);
    }

    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->db->prepare("UPDATE reservations SET status = ? WHERE reservation_id = ?");
        return $stmt->execute([$status, $id]);
    }

    public function updateWorkflowStage(int $id, string $stage): bool {
        $stmt = $this->db->prepare("UPDATE reservations SET workflow_stage = ?, updated_at = NOW() WHERE reservation_id = ?");
        return $stmt->execute([$stage, $id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM reservations WHERE reservation_id = ?");
        return $stmt->execute([$id]);
    }
}
