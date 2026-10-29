<?php
require_once __DIR__ . '/../poultrychat/database.php';

// Check if Broiler Starter Feed item exists
$chk = $conn->prepare("SELECT inventory_id, item_name, quantity, unit FROM inventory WHERE item_name LIKE '%Starter%' LIMIT 1");
$chk->execute();
$row = $chk->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $conn->prepare("UPDATE inventory SET quantity = 2500, unit = 'kg', updated_at = NOW() WHERE inventory_id = ?")->execute([$row['inventory_id']]);
    echo "Updated existing starter feed #{$row['inventory_id']} to 2500 kg.\n";
} else {
    $stmt = $conn->prepare("
        INSERT INTO inventory (item_name, category, quantity, unit, unit_cost, previous_quantity, reorder_level, is_active, sku, description, created_at, updated_at)
        VALUES ('Broiler Starter Crumble (Day 0-7)', 'feed', 2500.00, 'kg', 42.00, 2000.00, 500.00, 1, 'FEED-STR-01', 'High-protein pre-starter & starter crumble feed for day-old chicks', NOW(), NOW())
    ");
    $stmt->execute();
    echo "Inserted new Broiler Starter Crumble with 2500 kg.\n";
}

// Also ensure ChicksFeeds has healthy quantity
$conn->exec("UPDATE inventory SET quantity = 250 WHERE item_name = 'ChicksFeeds'");
echo "Updated ChicksFeeds to 250 Bags.\n";

// Test the query
$feedStmt = $conn->query("
    SELECT SUM(CASE 
        WHEN LOWER(unit) IN ('bags', 'sacks') THEN quantity * 50 
        ELSE quantity 
    END) 
    FROM inventory 
    WHERE category = 'feed' 
      AND (LOWER(item_name) LIKE '%starter%' OR LOWER(item_name) LIKE '%chick%' OR LOWER(item_name) LIKE '%broiler%')
");
$totalKg = (float)($feedStmt->fetchColumn() ?: 0);
echo "Total Starter & Chick Feed in stock: " . number_format($totalKg, 1) . " kg on hand.\n";
