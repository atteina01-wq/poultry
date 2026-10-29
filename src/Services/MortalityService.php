<?php
namespace Poultry\Services;

use Exception;
use Poultry\Repositories\MortalityRepository;
use Poultry\Repositories\BatchRepository;
use Poultry\Repositories\AuditLogRepository;

class MortalityService {
    private MortalityRepository $mortRepo;
    private BatchRepository $batchRepo;
    private AuditLogRepository $auditRepo;

    public function __construct() {
        $this->mortRepo = new MortalityRepository();
        $this->batchRepo = new BatchRepository();
        $this->auditRepo = new AuditLogRepository();
    }

    public function listMortality(array $filters = []): array {
        return $this->mortRepo->getAll($filters);
    }

    public function recordMortality(array $data, ?int $userId = null): array {
        $batchId = (int)($data['batch_id'] ?? 0);
        $qty = abs((int)($data['quantity'] ?? ($data['qty'] ?? 0)));
        $reason = trim($data['reason'] ?? 'Unknown');
        $date = $data['date'] ?? date('Y-m-d');
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);

        if ($batchId <= 0) {
            throw new Exception("Batch is required");
        }
        if ($qty <= 0) {
            throw new Exception("Mortality quantity must be greater than 0");
        }

        $batch = $this->batchRepo->findById($batchId);
        if (!$batch) {
            throw new Exception("Batch not found");
        }
        if ($batch['current_quantity'] < $qty) {
            throw new Exception("Mortality count cannot exceed current batch quantity ({$batch['current_quantity']})");
        }

        $mortalityId = $this->mortRepo->recordMortality($batchId, $qty, $reason, $date, $userId);
        $this->auditRepo->log($userId, 'record_mortality', 'mortality_records', $mortalityId, null, [
            'batch_id' => $batchId,
            'quantity_loss' => $qty,
            'reason' => $reason,
            'date' => $date
        ]);

        $updatedBatch = $this->batchRepo->findById($batchId);

        return [
            'mortality_id' => $mortalityId,
            'batch_code' => $batch['batch_code'],
            'quantity_lost' => $qty,
            'remaining_quantity' => $updatedBatch['current_quantity'],
            'reason' => $reason,
            'date' => $date,
            'message' => 'Mortality record saved and flock stock updated successfully'
        ];
    }

    public function getSummary(string $period = 'daily', ?int $batchId = null): array {
        return $this->mortRepo->getSummary($period, $batchId);
    }
}
