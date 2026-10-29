<?php
namespace Poultry\Services;

use Exception;
use Poultry\Repositories\WasteRepository;
use Poultry\Repositories\AuditLogRepository;

class WasteService {
    private WasteRepository $wasteRepo;
    private AuditLogRepository $auditRepo;

    public function __construct() {
        $this->wasteRepo = new WasteRepository();
        $this->auditRepo = new AuditLogRepository();
    }

    public function listWaste(array $filters = []): array {
        return $this->wasteRepo->getAll($filters);
    }

    public function recordWaste(array $data, ?int $userId = null): array {
        $wasteType = $data['waste_type'] ?? 'egg';
        $quantity = (float)($data['quantity'] ?? 0);
        $validTypes = ['egg', 'supply', 'medicine', 'other'];

        if (!in_array($wasteType, $validTypes)) {
            throw new Exception("Invalid waste type");
        }
        if ($quantity <= 0) {
            throw new Exception("Quantity must be greater than 0");
        }

        $data['recorded_by'] = $userId;
        $wasteId = $this->wasteRepo->recordWaste($data);
        $this->auditRepo->log($userId, 'record_waste', 'waste_records', $wasteId, null, $data);

        return [
            'waste_id' => $wasteId,
            'waste_type' => $wasteType,
            'quantity' => $quantity,
            'message' => 'Waste record logged and inventory updated successfully'
        ];
    }
}
