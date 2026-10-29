<?php
namespace Poultry\Services;

use Exception;
use Poultry\Repositories\InventoryRepository;
use Poultry\Repositories\AuditLogRepository;

class InventoryService {
    private InventoryRepository $invRepo;
    private AuditLogRepository $auditRepo;

    public function __construct() {
        $this->invRepo = new InventoryRepository();
        $this->auditRepo = new AuditLogRepository();
    }

    public function listItems(?string $category = null, bool $onlyActive = true): array {
        return $this->invRepo->getAll($category, $onlyActive);
    }

    public function getItem(int $id): array {
        $item = $this->invRepo->findById($id);
        if (!$item) {
            throw new Exception("Inventory item not found");
        }
        $item['lots'] = $this->invRepo->getLots($id);
        return $item;
    }

    public function createItem(array $data, ?int $userId = null): array {
        $name = trim($data['item_name'] ?? '');
        $category = $data['category'] ?? 'feed';
        $quantity = (float)($data['quantity'] ?? 0);
        $cost = (float)($data['unit_cost'] ?? 0.00);

        if (empty($name)) {
            throw new Exception("Item name is required");
        }
        if (!in_array($category, ['feed', 'medicine', 'vitamins', 'supply', 'limestone'])) {
            throw new Exception("Invalid inventory category");
        }

        // Check if exists
        $existing = $this->invRepo->findByNameAndCategory($name, $category);
        if ($existing) {
            // Restock existing item
            $this->invRepo->restock((int)$existing['inventory_id'], $quantity, $cost);
            $itemId = (int)$existing['inventory_id'];
            $action = 'restock';
        } else {
            $itemId = $this->invRepo->create($data);
            $action = 'create';
        }

        // Record lot if lot number or supplier is given
        if (!empty($data['lot_number'])) {
            $this->invRepo->addLot([
                'inventory_id' => $itemId,
                'lot_number' => $data['lot_number'],
                'quantity_received' => $quantity,
                'unit_cost' => $cost,
                'received_date' => $data['received_date'] ?? date('Y-m-d'),
                'expiry_date' => $data['expiry_date'] ?? null,
                'supplier' => $data['supplier'] ?? null
            ]);
        }

        $this->auditRepo->log($userId, $action, 'inventory', $itemId, null, $data);
        return $this->getItem($itemId);
    }

    public function updateItem(int $id, array $data, ?int $userId = null): array {
        $existing = $this->getItem($id);
        $this->invRepo->update($id, $data);
        $this->auditRepo->log($userId, 'update', 'inventory', $id, $existing, $data);
        return $this->getItem($id);
    }

    public function getExpiringLots(?int $daysThreshold = null): array {
        return $this->invRepo->getExpiringLots($daysThreshold);
    }

    public function getKpis(): array {
        return $this->invRepo->getKpis();
    }
}
