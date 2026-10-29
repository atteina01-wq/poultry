<?php
require_once __DIR__ . '/../src/autoload.php';
use Poultry\Core\Database;
$conn = Database::getInstance()->getConnection();

// Test House 6 reservation
$res = $conn->query("
    SELECT r.*, c.name as customer_name 
    FROM reservations r 
    JOIN customers c ON r.customer_id = c.customer_id 
    WHERE r.house_id = 6 AND r.status = 'active'
")->fetch(PDO::FETCH_ASSOC);

echo "=== HOUSE 6 ACTIVE RESERVATION ===\n";
echo "ID: " . $res['reservation_id'] . "\n";
echo "Number: " . $res['reservation_number'] . "\n";
echo "Customer: " . $res['customer_name'] . "\n";
echo "Workflow Stage: " . $res['workflow_stage'] . "\n";
echo "Birds: " . $res['quantity_birds'] . "\n";

// Test feed check calculation for this reservation
$reqBirds = (int)$res['quantity_birds'];
$starterFeedNeededKg = round($reqBirds * 0.15);

$feedRows = $conn->query("
    SELECT item_name, quantity, unit,
    (CASE WHEN LOWER(unit) IN ('bags', 'sacks') THEN quantity * 50 ELSE quantity END) as in_kg
    FROM inventory 
    WHERE (LOWER(category) LIKE '%feed%' OR LOWER(item_name) LIKE '%feed%')
      AND (LOWER(item_name) LIKE '%starter%' OR LOWER(item_name) LIKE '%crumble%' OR LOWER(item_name) LIKE '%chick%')
")->fetchAll(PDO::FETCH_ASSOC);

$starterSum = 0;
foreach ($feedRows as $frow) {
    $starterSum += (float)$frow['in_kg'];
}

echo "\n=== FEED CHECK CALCULATION ===\n";
echo "Starter Needed (Day 0-7): ~" . $starterFeedNeededKg . " kg\n";
echo "Starter In Stock: " . $starterSum . " kg\n";
echo "Pass: " . ($starterSum >= $starterFeedNeededKg ? 'YES (PASS)' : 'NO (RE-ORDER DUE)') . "\n";
echo "Inventory items matched:\n";
foreach ($feedRows as $frow) {
    echo " - " . $frow['item_name'] . ": " . $frow['quantity'] . " " . $frow['unit'] . " (" . $frow['in_kg'] . " kg)\n";
}
