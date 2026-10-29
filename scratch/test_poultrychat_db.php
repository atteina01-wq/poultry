<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
include __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("
    SELECT COALESCE(SUM(CASE 
        WHEN LOWER(unit) IN ('bags', 'sacks') THEN quantity * 50 
        ELSE quantity 
    END), 0) 
    FROM inventory 
    WHERE category = 'feed' 
      AND (LOWER(item_name) LIKE '%starter%' OR LOWER(item_name) LIKE '%chick%' OR LOWER(item_name) LIKE '%crumble%' OR LOWER(item_name) LIKE '%broiler%')
");
echo "Feed stock kg via poultrychat/database.php: " . $stmt->fetchColumn() . "\n";

$rows = $conn->query("SELECT inventory_id, item_name, quantity, unit, category FROM inventory WHERE category = 'feed'")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
