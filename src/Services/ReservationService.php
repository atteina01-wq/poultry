<?php
namespace Poultry\Services;

use Exception;
use Poultry\Repositories\ReservationRepository;
use Poultry\Repositories\CustomerRepository;
use Poultry\Repositories\AuditLogRepository;

class ReservationService {
    private ReservationRepository $resRepo;
    private CustomerRepository $custRepo;
    private AuditLogRepository $auditRepo;

    public function __construct() {
        $this->resRepo = new ReservationRepository();
        $this->custRepo = new CustomerRepository();
        $this->auditRepo = new AuditLogRepository();
    }

    public function listReservations(array $filters = []): array {
        return $this->resRepo->getAll($filters);
    }

    public function getReservation(int $id): array {
        $res = $this->resRepo->findById($id);
        if (!$res) {
            throw new Exception("Reservation not found");
        }
        return $res;
    }

    /**
     * Create Broiler Chicken Reservation (Chapter 1)
     */
    public function createBroilerReservation(array $data, ?int $userId = null): array {
        $customerId = (int)($data['customer_id'] ?? 0);
        $pickupDate = !empty($data['pickup_date']) ? $data['pickup_date'] : date('Y-m-d', strtotime('+3 days'));
        $birds = isset($data['quantity_birds']) ? (int)$data['quantity_birds'] : 0;
        $totalAmount = isset($data['total_amount']) ? (float)$data['total_amount'] : 0.0;
        $advancePayment = isset($data['advance_payment']) ? (float)$data['advance_payment'] : 0.0;

        if ($customerId <= 0) {
            throw new Exception("Customer is required");
        }

        $batchId = (int)($data['batch_id'] ?? 0);
        $houseId = (int)($data['house_id'] ?? 0);
        if ($batchId <= 0 && $houseId <= 0) {
            throw new Exception("Please select a valid broiler batch or reserve a vacant house.");
        }

        $dbConn = \Poultry\Core\Database::getInstance()->getConnection();

        if ($houseId > 0) {
            // Verify house exists
            $stmtHouse = $dbConn->prepare("SELECT house_id, house_name, capacity, status, current_batch_id FROM houses WHERE house_id = ?");
            $stmtHouse->execute([$houseId]);
            $house = $stmtHouse->fetch(\PDO::FETCH_ASSOC);
            if (!$house) {
                throw new Exception("Selected poultry pen/house does not exist.");
            }

            // Calculate occupied flock birds and active reserved birds in this house
            $stmtComm = $dbConn->prepare("
                SELECT 
                    COALESCE((SELECT SUM(r.quantity_birds) FROM reservations r WHERE r.house_id = ? AND r.status = 'active'), 0) as reserved_birds,
                    COALESCE((SELECT SUM(b.current_quantity) FROM batches b WHERE (b.house_pen = ? OR b.batch_id = ?) AND b.status = 'active' AND b.current_quantity > 0), 0) as flock_birds
            ");
            $stmtComm->execute([$houseId, $house['house_name'], (int)$house['current_batch_id']]);
            $commData = $stmtComm->fetch(\PDO::FETCH_ASSOC);
            $occupiedBirds = max((int)($commData['reserved_birds'] ?? 0), (int)($commData['flock_birds'] ?? 0));
            $houseCap = (int)($house['capacity'] > 0 ? $house['capacity'] : 5000);
            $remainingCap = max(0, $houseCap - $occupiedBirds);

            $reqBirds = isset($data['quantity_birds']) ? (int)$data['quantity_birds'] : 0;
            if ($remainingCap <= 0) {
                throw new Exception("Reservation blocked: " . $house['house_name'] . " is currently fully occupied/reserved (" . number_format($occupiedBirds) . " / " . number_format($houseCap) . " birds). No vacant space available.");
            }
            if ($reqBirds > $remainingCap) {
                throw new Exception("Exceeds available pen capacity: " . $house['house_name'] . " has only " . number_format($remainingCap) . " available spaces (requested " . number_format($reqBirds) . " birds, total capacity: " . number_format($houseCap) . ").");
            }
        }

        if ($batchId > 0) {
            $chkBatch = $dbConn->prepare("SELECT batch_id FROM batches WHERE batch_id = ?");
            $chkBatch->execute([$batchId]);
            if (!$chkBatch->fetchColumn()) {
                throw new Exception("Selected broiler batch does not exist.");
            }
            $data['batch_id'] = $batchId;
        } else {
            if ($houseId > 0) {
                $stmtHBatch = $dbConn->prepare("SELECT current_batch_id FROM houses WHERE house_id = ? AND status = 'occupied'");
                $stmtHBatch->execute([$houseId]);
                $hCurBatch = (int)$stmtHBatch->fetchColumn();
                $data['batch_id'] = $hCurBatch > 0 ? $hCurBatch : null;
            } else {
                $data['batch_id'] = null;
            }
        }

        // Idempotency check: Prevent duplicate submissions within 30 seconds
        $stmtDup = $dbConn->prepare("
            SELECT reservation_id, reservation_number 
            FROM reservations 
            WHERE customer_id = ? 
              AND (house_id = ? OR (? IS NULL AND house_id IS NULL))
              AND quantity_birds = ? 
              AND created_at >= (NOW() - INTERVAL 30 SECOND)
            LIMIT 1
        ");
        $stmtDup->execute([$customerId, $houseId > 0 ? $houseId : null, $houseId > 0 ? $houseId : null, $birds]);
        $dup = $stmtDup->fetch(\PDO::FETCH_ASSOC);
        if ($dup) {
            throw new Exception("Duplicate reservation submission detected. Reservation #" . $dup['reservation_number'] . " was already created a few seconds ago.");
        }

        $cust = $this->custRepo->findById($customerId);
        if (!$cust || (int)$cust['is_active'] !== 1) {
            throw new Exception("Selected customer is invalid or inactive");
        }

        if ($birds <= 0) {
            throw new Exception("Reserved birds quantity must be greater than 0");
        }

        if ($birds > 5000) {
            throw new Exception("Exceeds pen capacity. Houses accommodate a maximum of 5,000 birds.");
        }

        if (empty($pickupDate)) {
            throw new Exception("Pickup date is required");
        }

        $today = date('Y-m-d');
        if ($pickupDate < $today) {
            throw new Exception("Cannot select past date for expected pickup (" . htmlspecialchars($pickupDate) . "). Date must be today or in the future.");
        }

        $targetWeight = isset($data['target_weight_per_bird']) && $data['target_weight_per_bird'] !== '' ? (float)$data['target_weight_per_bird'] : 2.20;
        if ($targetWeight < 2.20) {
            throw new Exception("Agreed target weight cannot be below 2.20 kg (strict contract standard: minimum 2.20 kg for commercial broilers).");
        }
        $data['target_weight_per_bird'] = $targetWeight;

        if (!empty($data['arrival_date'])) {
            $arrDate = $data['arrival_date'];
            if ($arrDate < $today) {
                throw new Exception("Chicks arrival date cannot be in the past (" . htmlspecialchars($arrDate) . ").");
            }
            $diffDays = (strtotime($pickupDate) - strtotime($arrDate)) / 86400;
            if ($diffDays < 35) {
                throw new Exception("Strict Standard: Expected harvest date must be at least 35 days from arrival date (" . htmlspecialchars($arrDate) . " to " . htmlspecialchars($pickupDate) . " is only " . round($diffDays) . " days). Harvest cycle duration cannot be below 35 days.");
            }
        }

        $unitPrice = isset($data['unit_price']) ? (float)$data['unit_price'] : 140.00;
        $chickCost = isset($data['chick_cost']) && $data['chick_cost'] !== '' ? (float)$data['chick_cost'] : 38.00;
        if ($totalAmount <= 0.0 && $birds > 0) {
            $totalAmount = round($birds * $targetWeight * $unitPrice, 2);
        }

        // Calculate balance: balance = total amount - advance payment
        $remainingBalance = round($totalAmount - $advancePayment, 2);

        $data['created_by'] = $userId;
        $data['chick_cost'] = $chickCost;
        $data['total_amount'] = $totalAmount;
        $data['advance_payment'] = $advancePayment;

        $resId = $this->resRepo->createBroilerReservation($data);

        $this->auditRepo->log($userId, 'create', 'reservations', $resId, null, [
            'customer_id' => $customerId,
            'batch_id' => $data['batch_id'] ?? null,
            'quantity_birds' => $birds,
            'breed' => $data['breed'] ?? 'Cobb 500',
            'weight_kg' => $data['weight_kg'] ?? null,
            'unit_price' => $data['unit_price'] ?? null,
            'chick_cost' => $chickCost,
            'total_amount' => $totalAmount,
            'advance_payment' => $advancePayment,
            'balance' => $remainingBalance,
            'pickup_date' => $pickupDate
        ]);

        return $this->getReservation($resId);
    }

    /**
     * Record Fulfillment / Pickup with accepted & rejected breakdown
     */
    public function recordFulfillment(int $id, array $data, ?int $userId = null): array {
        $existing = $this->getReservation($id);
        if (!$existing) {
            throw new Exception("Reservation not found");
        }

        $bal = round((float)$existing['total_amount'] - (float)$existing['advance_payment'], 2);
        if ($bal > 0.01) {
            throw new Exception("Security Gate: Cannot pick up flock: Integrator has an outstanding balance of ₱" . number_format($bal, 2) . ". Full payment must be settled before trucks can be dispatched.");
        }

        if (empty($existing['is_dispatched'])) {
            throw new Exception("Security Gate: Flock has not been dispatched by the Farm Admin yet. Even when fully paid, the integrator cannot pick up immediately ('cannot pick up dayon'). The Farm Admin must first inspect the flock and officially dispatch the chicks.");
        }

        $today = date('Y-m-d');
        if (!empty($existing['pickup_date']) && $today < $existing['pickup_date']) {
            throw new Exception("Security Gate: Agreed pickup date (" . date('M d, Y', strtotime($existing['pickup_date'])) . ") has not arrived yet. Flock hauling is scheduled for " . date('F d, Y', strtotime($existing['pickup_date'])) . ".");
        }

        if (empty($data['actual_pickup_date'])) {
            $data['actual_pickup_date'] = $today;
        }

        if ($data['actual_pickup_date'] < $today) {
            throw new Exception("Security Gate: Pick up date cannot be in the past (" . htmlspecialchars($data['actual_pickup_date']) . ").");
        }

        $acceptedQty = isset($data['accepted_quantity']) ? (int)$data['accepted_quantity'] : 0;
        $rejectedQty = isset($data['rejected_quantity']) ? (int)$data['rejected_quantity'] : 0;

        if ($acceptedQty < 0 || $rejectedQty < 0) {
            throw new Exception("Accepted and rejected counts cannot be negative");
        }

        $this->resRepo->recordFulfillment($id, $data);

        if (!empty($existing['batch_id'])) {
            $batchRepo = new \Poultry\Repositories\BatchRepository();
            $batchRepo->syncHeadcount($existing['batch_id']);
        }

        // When fulfilling a house reservation, release house back to cleaning if no other active reservation/flock
        if (!empty($existing['house_id'])) {
            try {
                $db = \Poultry\Core\Database::getInstance()->getConnection();
                $chkOther = $db->prepare("SELECT COUNT(*) FROM reservations WHERE house_id = ? AND status = 'active' AND reservation_id != ?");
                $chkOther->execute([(int)$existing['house_id'], $id]);
                if ((int)$chkOther->fetchColumn() === 0) {
                    $chkBatch = $db->prepare("SELECT COUNT(*) FROM batches WHERE (house_pen = (SELECT house_name FROM houses WHERE house_id = ?) OR batch_id = (SELECT current_batch_id FROM houses WHERE house_id = ?)) AND status = 'active' AND current_quantity > 0");
                    $chkBatch->execute([(int)$existing['house_id'], (int)$existing['house_id']]);
                    if ((int)$chkBatch->fetchColumn() === 0) {
                        $db->prepare("UPDATE houses SET status = 'cleaning', current_batch_id = NULL WHERE house_id = ?")->execute([(int)$existing['house_id']]);
                    }
                }
            } catch (\Exception $ex) {}
        }

        $this->auditRepo->log($userId, 'fulfill', 'reservations', $id, [
            'status' => $existing['status']
        ], [
            'status' => 'fulfilled',
            'actual_pickup_date' => $data['actual_pickup_date'],
            'accepted_quantity' => $acceptedQty,
            'rejected_quantity' => $rejectedQty,
            'rejection_reason' => $data['rejection_reason'] ?? null
        ]);

        return $this->getReservation($id);
    }

    /**
     * Update Broiler Reservation details
     */
    public function updateReservation(int $id, array $data, ?int $userId = null): array {
        $existing = $this->getReservation($id);

        $customerId = (int)($data['customer_id'] ?? $existing['customer_id']);
        $cust = $customerId > 0 ? $this->custRepo->findById($customerId) : null;
        if (!$cust) {
            // Fallback to existing reservation's customer if valid
            if (!empty($existing['customer_id']) && $this->custRepo->findById((int)$existing['customer_id'])) {
                $customerId = (int)$existing['customer_id'];
            } else {
                // Fallback to first available active customer
                $db = \Poultry\Core\Database::getInstance()->getConnection();
                $firstId = $db->query("SELECT customer_id FROM customers WHERE is_active = 1 ORDER BY customer_id ASC LIMIT 1")->fetchColumn();
                if ($firstId) {
                    $customerId = (int)$firstId;
                } else {
                    throw new Exception("Please select a valid customer.");
                }
            }
        }
        $data['customer_id'] = $customerId;

        $batchId = (int)($data['batch_id'] ?? $existing['batch_id']);
        if ($batchId <= 0) {
            throw new Exception("Please select a valid broiler batch. Every reservation must belong to a batch.");
        }
        $data['batch_id'] = $batchId;

        $birds = isset($data['quantity_birds']) ? (int)$data['quantity_birds'] : (int)$existing['quantity_birds'];
        if ($birds <= 0) {
            throw new Exception("Reserved birds quantity must be greater than 0");
        }
        $data['quantity_birds'] = $birds;

        $this->resRepo->updateReservation($id, $data);

        $this->auditRepo->log($userId, 'update', 'reservations', $id, [
            'total_amount' => $existing['total_amount'],
            'advance_payment' => $existing['advance_payment'],
            'chick_cost' => $existing['chick_cost'] ?? 38.00
        ], [
            'customer_id' => $customerId,
            'batch_id' => $data['batch_id'] ?? null,
            'quantity_birds' => $birds,
            'chick_cost' => $data['chick_cost'] ?? ($existing['chick_cost'] ?? 38.00),
            'total_amount' => $data['total_amount'] ?? $existing['total_amount'],
            'advance_payment' => $data['advance_payment'] ?? $existing['advance_payment'],
            'pickup_date' => $data['pickup_date'] ?? $existing['pickup_date']
        ]);

        return $this->getReservation($id);
    }

    public function createReservation(array $data, ?int $userId = null): array {
        return $this->createBroilerReservation($data, $userId);
    }

    public function updateStatus(int $id, string $status, ?int $userId = null): array {
        $existing = $this->getReservation($id);
        $valid = ['active', 'fulfilled', 'cancelled', 'expired'];

        if (!in_array($status, $valid)) {
            throw new Exception("Invalid reservation status: {$status}");
        }

        $this->resRepo->updateStatus($id, $status);
        $this->auditRepo->log($userId, 'update_status', 'reservations', $id, ['status' => $existing['status']], ['status' => $status]);

        // If status changed away from active, release house if no other active reservation/flock exists
        if ($existing['status'] === 'active' && $status !== 'active' && !empty($existing['house_id'])) {
            try {
                $db = \Poultry\Core\Database::getInstance()->getConnection();
                $chkOther = $db->prepare("SELECT COUNT(*) FROM reservations WHERE house_id = ? AND status = 'active' AND reservation_id != ?");
                $chkOther->execute([(int)$existing['house_id'], $id]);
                if ((int)$chkOther->fetchColumn() === 0) {
                    $chkBatch = $db->prepare("SELECT COUNT(*) FROM batches WHERE (house_pen = (SELECT house_name FROM houses WHERE house_id = ?) OR batch_id = (SELECT current_batch_id FROM houses WHERE house_id = ?)) AND status = 'active' AND current_quantity > 0");
                    $chkBatch->execute([(int)$existing['house_id'], (int)$existing['house_id']]);
                    if ((int)$chkBatch->fetchColumn() === 0) {
                        $targetHouseStatus = ($status === 'fulfilled') ? 'cleaning' : 'available';
                        $db->prepare("UPDATE houses SET status = ? WHERE house_id = ? AND status = 'reserved'")->execute([$targetHouseStatus, (int)$existing['house_id']]);
                    }
                }
            } catch (\Exception $ex) {}
        }

        return $this->getReservation($id);
    }

    public function deleteReservation(int $id, ?int $userId = null): bool {
        $existing = $this->getReservation($id);
        
        // If this reservation was linked to a house, revert house back to 'available' if it was reserved
        if (!empty($existing['house_id'])) {
            try {
                $db = \Poultry\Core\Database::getInstance()->getConnection();
                $stmtH = $db->prepare("UPDATE houses SET status = 'available' WHERE house_id = ? AND status = 'reserved'");
                $stmtH->execute([$existing['house_id']]);
            } catch (Exception $ex) {}
        }

        $deleted = $this->resRepo->delete($id);
        if ($deleted) {
            $this->auditRepo->log($userId, 'delete', 'reservations', $id, [
                'reservation_number' => $existing['reservation_number'] ?? null,
                'customer_id' => $existing['customer_id'] ?? null,
                'batch_id' => $existing['batch_id'] ?? null
            ], null);
        }
        return $deleted;
    }

    public function dispatchFlock(int $id, ?string $dispatchNotes = null, ?int $userId = null): array {
        $reservation = $this->getReservation($id);
        if (!$reservation) {
            throw new Exception("Reservation not found");
        }
        if ($reservation['status'] !== 'active') {
            throw new Exception("Only active reservations can be dispatched");
        }

        $total = (float)$reservation['total_amount'];
        $advance = (float)$reservation['advance_payment'];
        $balance = round($total - $advance, 2);
        if ($balance > 0.01) {
            throw new Exception("Cannot dispatch flock: Integrator has an outstanding balance of ₱" . number_format($balance, 2) . ". Full payment must be settled before dispatch.");
        }

        // Strict Gate 2: Agreed pickup date must be reached
        $today = date('Y-m-d');
        if (!empty($reservation['pickup_date']) && $today < $reservation['pickup_date']) {
            throw new Exception("Cannot dispatch flock: Agreed pickup date (" . date('M d, Y', strtotime($reservation['pickup_date'])) . ") has not arrived yet. Strict agreement gate locks dispatch until the agreed harvest date.");
        }

        // Strict Gate 3: Agreed target weight must be reached
        $targetWeight = !empty($reservation['target_weight_per_bird']) ? (float)$reservation['target_weight_per_bird'] : 2.20;
        $batchId = (int)($reservation['batch_id'] ?? 0);
        $currentWeight = 0.0;
        if ($batchId > 0) {
            $dbConn = \Poultry\Core\Database::getInstance()->getConnection();
            $stmtB = $dbConn->prepare("SELECT current_weight_kg, arrival_date FROM batches WHERE batch_id = ?");
            $stmtB->execute([$batchId]);
            $bRow = $stmtB->fetch(\PDO::FETCH_ASSOC);
            if ($bRow) {
                $currentWeight = (float)($bRow['current_weight_kg'] ?? 0.0);
                if ($currentWeight <= 0.0 && !empty($bRow['arrival_date'])) {
                    $ageDays = max(0, (int)floor((strtotime($today) - strtotime($bRow['arrival_date'])) / 86400));
                    if ($ageDays <= 7) $currentWeight = round(0.04 + ($ageDays * (0.18 - 0.04) / 7), 2);
                    elseif ($ageDays <= 14) $currentWeight = round(0.18 + (($ageDays - 7) * (0.45 - 0.18) / 7), 2);
                    elseif ($ageDays <= 21) $currentWeight = round(0.45 + (($ageDays - 14) * (0.92 - 0.45) / 7), 2);
                    elseif ($ageDays <= 28) $currentWeight = round(0.92 + (($ageDays - 21) * (1.55 - 0.92) / 7), 2);
                    elseif ($ageDays <= 32) $currentWeight = round(1.55 + (($ageDays - 28) * (1.85 - 1.55) / 4), 2);
                    else $currentWeight = round(1.85 + (($ageDays - 32) * 0.06), 2);
                }
            }
        }
        if ($currentWeight > 0.0 && $currentWeight < $targetWeight) {
            throw new Exception("Cannot dispatch flock: Flock current weight (" . number_format($currentWeight, 2) . " kg) has not reached the agreed contract weight (" . number_format($targetWeight, 2) . " kg). Both agreed date and target weight must be completed.");
        }

        $notes = $reservation['notes'] ?? '';
        if (!empty($dispatchNotes)) {
            $notes = trim($notes . " [Admin Dispatched: " . $dispatchNotes . "]");
        }

        $this->resRepo->dispatchReservation($id, $notes, $userId);

        $this->auditRepo->log($userId, 'dispatch', 'reservations', $id, [
            'is_dispatched' => 1,
            'dispatched_at' => date('Y-m-d H:i:s'),
            'dispatched_by' => $userId,
            'reservation_number' => $reservation['reservation_number']
        ]);

        return $this->getReservation($id);
    }

    public function updateWorkflowStage(int $id, string $stage, ?int $userId = null): array {
        $existing = $this->getReservation($id);
        if (!$existing) {
            throw new Exception("Reservation not found");
        }
        $validStages = ['pending_approval', 'approved', 'receiving', 'receipt_confirmed', 'growing'];
        if (!in_array($stage, $validStages)) {
            throw new Exception("Invalid workflow stage: {$stage}");
        }

        $this->resRepo->updateWorkflowStage($id, $stage);
        $this->auditRepo->log($userId, 'update_workflow_stage', 'reservations', $id, [
            'workflow_stage' => $existing['workflow_stage'] ?? 'pending_approval'
        ], [
            'workflow_stage' => $stage
        ]);

        return $this->getReservation($id);
    }
}

