<?php
require_once __DIR__ . '/../src/autoload.php';
use Poultry\Core\Database;
$conn = Database::getInstance()->getConnection();

$feedStmt = $conn->query("
    SELECT COALESCE(SUM(CASE 
        WHEN LOWER(unit) IN ('bags', 'sacks') THEN quantity * 50 
        ELSE quantity 
    END), 0) 
    FROM inventory 
    WHERE category = 'feed' 
      AND (LOWER(item_name) LIKE '%starter%' OR LOWER(item_name) LIKE '%chick%' OR LOWER(item_name) LIKE '%crumble%' OR LOWER(item_name) LIKE '%broiler%')
");
$feedStockKg = (float)($feedStmt->fetchColumn() ?: 0);
echo "feedStockKg: " . $feedStockKg . " kg\n";

// Let's also check individual items matching
$stmt2 = $conn->query("
    SELECT inventory_id, item_name, category, quantity, unit,
    (CASE WHEN LOWER(unit) IN ('bags', 'sacks') THEN quantity * 50 ELSE quantity END) as in_kg
    FROM inventory 
    WHERE category = 'feed' 
      AND (LOWER(item_name) LIKE '%starter%' OR LOWER(item_name) LIKE '%chick%' OR LOWER(item_name) LIKE '%crumble%' OR LOWER(item_name) LIKE '%broiler%')
");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
