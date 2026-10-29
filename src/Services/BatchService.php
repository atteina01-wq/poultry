<?php
namespace Poultry\Services;

use Exception;
use Poultry\Repositories\BatchRepository;
use Poultry\Repositories\AuditLogRepository;

class BatchService {
    private BatchRepository $batchRepo;
    private AuditLogRepository $auditRepo;

    public function __construct() {
        $this->batchRepo = new BatchRepository();
        $this->auditRepo = new AuditLogRepository();
    }

    public function listBatches(?string $flockType = null, ?string $status = null): array {
        return $this->batchRepo->getAll($flockType, $status);
    }

    public function getBatch(int $id): array {
        $batch = $this->batchRepo->findById($id);
        if (!$batch) {
            throw new Exception("Batch not found");
        }
        return $batch;
    }

    public function createBatch(array $data, ?int $userId = null): array {
        $batchCode = trim($data['batch_code'] ?? '');
        $flockType = 'broiler'; // Enforce broiler-only scope
        $arrivalDate = $data['arrival_date'] ?? date('Y-m-d');
        $productionDate = $data['production_date'] ?? $arrivalDate;
        $initialQuantity = (int)($data['initial_quantity'] ?? 0);

        if (empty($batchCode)) {
            throw new Exception("Batch code is required");
        }
        if ($initialQuantity <= 0) {
            throw new Exception("Initial quantity must be greater than 0");
        }

        // Check duplicate code
        if ($this->batchRepo->findByCode($batchCode)) {
            throw new Exception("Batch code '{$batchCode}' already exists");
        }

        $data['flock_type'] = $flockType;
        $data['arrival_date'] = $arrivalDate;
        $data['production_date'] = $productionDate;
        $data['created_by'] = $userId;
        $batchId = $this->batchRepo->create($data);

        $this->auditRepo->log($userId, 'create', 'batches', $batchId, null, $data);

        return $this->getBatch($batchId);
    }

    public function updateBatch(int $id, array $data, ?int $userId = null): array {
        $existing = $this->getBatch($id);

        $batchCode = trim($data['batch_code'] ?? $existing['batch_code']);
        $flockType = 'broiler'; // Broiler-only scope
        $arrivalDate = $data['arrival_date'] ?? $existing['arrival_date'];
        $productionDate = $data['production_date'] ?? ($existing['production_date'] ?? $arrivalDate);

        // Check if code changed and collides
        if ($batchCode !== $existing['batch_code']) {
            $other = $this->batchRepo->findByCode($batchCode);
            if ($other && (int)$other['batch_id'] !== $id) {
                throw new Exception("Batch code '{$batchCode}' already exists on another flock");
            }
        }

        $data['batch_code'] = $batchCode;
        $data['flock_type'] = $flockType;
        $data['arrival_date'] = $arrivalDate;
        $data['production_date'] = $productionDate;

        $this->batchRepo->update($id, $data);
        $this->auditRepo->log($userId, 'update', 'batches', $id, $existing, $data);

        return $this->getBatch($id);
    }

    public function updateStatus(int $id, string $status, ?int $userId = null): array {
        $existing = $this->getBatch($id);
        if (!in_array($status, ['active', 'harvested', 'closed'])) {
            throw new Exception("Invalid batch status");
        }

        $this->batchRepo->updateStatus($id, $status);
        $this->auditRepo->log($userId, 'update_status', 'batches', $id, ['status' => $existing['status']], ['status' => $status]);

        return $this->getBatch($id);
    }

    public function syncHeadcount(int $id): array {
        $existing = $this->getBatch($id);
        $newHeadcount = $this->batchRepo->syncHeadcount($id);
        return [
            'batch_id' => $id,
            'initial_quantity' => (int)$existing['initial_quantity'],
            'current_quantity' => $newHeadcount
        ];
    }
}
