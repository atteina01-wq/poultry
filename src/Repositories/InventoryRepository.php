<?php
namespace Poultry\Repositories;

use PDO;
use Exception;
use Poultry\Core\Database;

class InventoryRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(?string $category = null, bool $onlyActive = true): array {
        $where = [];
        $params = [];

        if ($category !== null && $category !== '') {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        if ($onlyActive) {
            $where[] = 'is_active = 1';
        }

        $sql = "SELECT * FROM inventory";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY category ASC, item_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM inventory WHERE inventory_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByNameAndCategory(string $itemName, string $category): ?array {
        $stmt = $this->db->prepare("SELECT * FROM inventory WHERE item_name = ? AND category = ? LIMIT 1");
        $stmt->execute([$itemName, $category]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int {
        $sql = "INSERT INTO inventory (
                    item_name, category, quantity, previous_quantity,
                    unit, unit_cost, reorder_level, is_active, sku, description
                ) VALUES (?, ?, ?, 0, ?, ?, ?, 1, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['item_name'],
            $data['category'],
            $data['quantity'],
            $data['unit'] ?? null,
            $data['unit_cost'] ?? 0.00,
            $data['reorder_level'] ?? null,
            $data['sku'] ?? null,
            $data['description'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $sql = "UPDATE inventory SET
                    item_name = ?, category = ?, unit = ?, unit_cost = ?,
                    reorder_level = ?, sku = ?, description = ?
                WHERE inventory_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['item_name'],
            $data['category'],
            $data['unit'] ?? null,
            $data['unit_cost'] ?? 0.00,
            $data['reorder_level'] ?? null,
            $data['sku'] ?? null,
            $data['description'] ?? null,
            $id
        ]);
    }

    public function restock(int $id, float $addedQty, float $unitCost): bool {
        $stmt = $this->db->prepare("
            UPDATE inventory SET
                previous_quantity = quantity,
                quantity = quantity + ?,
                unit_cost = ?
            WHERE inventory_id = ?
        ");
        return $stmt->execute([$addedQty, $unitCost, $id]);
    }

    public function deductStock(int $id, float $deductQty): bool {
        $stmtCheck = $this->db->prepare("SELECT quantity, item_name, unit FROM inventory WHERE inventory_id = ? FOR UPDATE");
        $stmtCheck->execute([$id]);
        $row = $stmtCheck->fetch();
        if (!$row) {
            throw new Exception("Inventory item #{$id} not found");
        }
        $avail = (float)$row['quantity'];
        if ($deductQty > $avail) {
            throw new Exception("Insufficient stock for {$row['item_name']}! Available: " . number_format($avail, 2) . " {$row['unit']}, Requested: " . number_format($deductQty, 2) . " {$row['unit']}.");
        }
        $stmt = $this->db->prepare("
            UPDATE inventory SET
                previous_quantity = quantity,
                quantity = quantity - ?
            WHERE inventory_id = ?
        ");
        return $stmt->execute([$deductQty, $id]);
    }

    public function getKpis(): array {
        $totalValuation = (float)$this->db->query("SELECT SUM(GREATEST(0, quantity) * unit_cost) FROM inventory WHERE is_active = 1")->fetchColumn();
        $lowStockCount = (int)$this->db->query("SELECT COUNT(*) FROM inventory WHERE is_active = 1 AND quantity > 0 AND quantity <= COALESCE(reorder_level, 10)")->fetchColumn();
        $outOfStockCount = (int)$this->db->query("SELECT COUNT(*) FROM inventory WHERE is_active = 1 AND quantity <= 0")->fetchColumn();
        $totalItems = (int)$this->db->query("SELECT COUNT(*) FROM inventory WHERE is_active = 1")->fetchColumn();

        return [
            'total_valuation' => $totalValuation,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'total_items' => $totalItems
        ];
    }

    // Lots & Expiry
    public function getLots(int $inventoryId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM inventory_lots
            WHERE inventory_id = ?
            ORDER BY received_date DESC, expiry_date ASC
        ");
        $stmt->execute([$inventoryId]);
        return $stmt->fetchAll();
    }

    public function addLot(array $lotData): int {
        $sql = "INSERT INTO inventory_lots (
                    inventory_id, lot_number, quantity_received, quantity_remaining,
                    unit_cost, received_date, expiry_date, supplier
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $lotData['inventory_id'],
            $lotData['lot_number'],
            $lotData['quantity_received'],
            $lotData['quantity_received'], // remaining starts at received
            $lotData['unit_cost'] ?? null,
            $lotData['received_date'],
            $lotData['expiry_date'] ?? null,
            $lotData['supplier'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getExpiringLots(?int $daysThreshold = null): array {
        $sql = "SELECT vel.*, i.unit, i.unit_cost 
                FROM v_expiring_lots vel 
                JOIN inventory i ON vel.inventory_id = i.inventory_id";
        if ($daysThreshold !== null) {
            $sql .= " WHERE vel.days_until_expiry <= " . (int)$daysThreshold;
        }
        $sql .= " ORDER BY vel.days_until_expiry ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}
