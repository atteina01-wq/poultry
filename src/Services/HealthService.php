<?php
namespace Poultry\Services;

use Exception;
use Poultry\Repositories\HealthRecordRepository;
use Poultry\Repositories\BatchRepository;
use Poultry\Repositories\AuditLogRepository;

class HealthService {
    private HealthRecordRepository $healthRepo;
    private BatchRepository $batchRepo;
    private AuditLogRepository $auditRepo;

    public function __construct() {
        $this->healthRepo = new HealthRecordRepository();
        $this->batchRepo = new BatchRepository();
        $this->auditRepo = new AuditLogRepository();
    }

    public function listHealthRecords(array $filters = []): array {
        return $this->healthRepo->getAll($filters);
    }

    public function getBatchHealthHistory(int $batchId): array {
        return $this->healthRepo->getByBatch($batchId);
    }

    public function updateStatus(int $healthId, string $status, ?int $userId = null): bool {
        $record = $this->healthRepo->findById($healthId);
        if (!$record) {
            throw new Exception("Health record #{$healthId} not found");
        }
        $res = $this->healthRepo->updateStatus($healthId, $status);
        if ($res) {
            $this->auditRepo->log($userId, 'update_status', 'health_records', $healthId, ['status' => $record['status']], ['status' => $status]);
        }
        return $res;
    }

    public function recordHealth(array $data, ?int $userId = null): array {
        $batchId = (int)($data['batch_id'] ?? 0);
        $medicine = trim($data['medicine_name'] ?? ($data['medicine'] ?? ''));
        $qty = (float)($data['quantity_used'] ?? ($data['qty'] ?? 0));

        if ($batchId <= 0) {
            throw new Exception("Batch is required");
        }
        if (empty($medicine)) {
            throw new Exception("Medicine/Treatment name is required");
        }
        if ($qty <= 0) {
            throw new Exception("Quantity must be greater than 0");
        }

        $batch = $this->batchRepo->findById($batchId);
        if (!$batch) {
            throw new Exception("Batch not found");
        }
        if (!empty($batch['flock_type']) && strtolower($batch['flock_type']) !== 'broiler') {
            throw new Exception("Health records can only be recorded for broiler batches");
        }

        $data['batch_id'] = $batchId;
        $data['medicine_name'] = $medicine;
        $data['quantity_used'] = $qty;
        $data['recorded_by'] = $userId;
        $data['status'] = !empty($data['status']) ? strtolower($data['status']) : 'completed';

        $healthId = $this->healthRepo->recordHealth($data);
        $this->auditRepo->log($userId, 'record_health', 'health_records', $healthId, null, $data);

        return [
            'health_id' => $healthId,
            'batch_code' => $batch['batch_code'],
            'medicine_name' => $medicine,
            'quantity_used' => $qty,
            'status' => $data['status'],
            'doctor_consultation' => $data['doctor_consultation'] ?? null,
            'illness_observation' => $data['illness_observation'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'message' => 'Health record logged and inventory updated successfully'
        ];
    }
}
