<?php
namespace Poultry\Services;

use Exception;
use Poultry\Repositories\FeedConsumptionRepository;
use Poultry\Repositories\BatchRepository;
use Poultry\Repositories\InventoryRepository;
use Poultry\Repositories\AuditLogRepository;

class FeedService {
    private FeedConsumptionRepository $feedRepo;
    private BatchRepository $batchRepo;
    private InventoryRepository $invRepo;
    private AuditLogRepository $auditRepo;

    public function __construct() {
        $this->feedRepo = new FeedConsumptionRepository();
        $this->batchRepo = new BatchRepository();
        $this->invRepo = new InventoryRepository();
        $this->auditRepo = new AuditLogRepository();
    }

    public function listFeedConsumption(array $filters = []): array {
        return $this->feedRepo->getAll($filters);
    }

    public function recordConsumption(array $data, ?int $userId = null): array {
        $batchId = (int)($data['batch_id'] ?? 0);
        $inventoryId = (int)($data['inventory_id'] ?? 0);
        $qty = (float)($data['quantity_used'] ?? ($data['qty'] ?? 0));
        $date = $data['date'] ?? date('Y-m-d');
        $remarks = $data['remarks'] ?? null;
        $feedStage = !empty($data['feed_stage']) ? trim($data['feed_stage']) : null;

        if ($batchId <= 0) {
            throw new Exception("Flock/Batch is required");
        }
        if ($inventoryId <= 0) {
            throw new Exception("Feed/Inventory item is required");
        }
        if ($qty <= 0) {
            throw new Exception("Quantity used must be greater than 0");
        }

        $batch = $this->batchRepo->findById($batchId);
        if (!$batch) {
            throw new Exception("Batch not found");
        }
        if (!empty($batch['flock_type']) && strtolower($batch['flock_type']) !== 'broiler') {
            throw new Exception("Feed consumption can only be recorded for broiler batches");
        }

        $item = $this->invRepo->findById($inventoryId);
        if (!$item) {
            throw new Exception("Feed item not found");
        }

        $consumptionId = $this->feedRepo->recordConsumption($batchId, $inventoryId, $qty, $date, $userId, $remarks, $feedStage);
        $this->auditRepo->log($userId, 'record_feed', 'feed_consumption', $consumptionId, null, $data);

        return [
            'consumption_id' => $consumptionId,
            'batch_code' => $batch['batch_code'],
            'item_name' => $item['item_name'],
            'feed_stage' => $feedStage,
            'quantity_used' => $qty,
            'unit' => $item['unit'],
            'date' => $date,
            'message' => 'Feed consumption recorded and inventory deducted successfully'
        ];
    }

    public function getAnalyticsByBatch(int $batchId): array {
        return $this->feedRepo->getAnalyticsByBatch($batchId);
    }
}
