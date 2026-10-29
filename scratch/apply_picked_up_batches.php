<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

echo "=== APPLYING BATCH STATUS UPDATE ===\n";
// Set old / picked up batches to 'harvested'
$stmt = $conn->prepare("UPDATE batches SET status = 'harvested', current_quantity = 0 WHERE batch_id IN (6, 31, 35, 37, 38)");
$stmt->execute();
echo "Updated batches 6, 31, 35, 37, 38 to status = 'harvested'\n";

// Update House 4 to available since its old batch is now marked harvested
$conn->exec("UPDATE houses SET status = 'available', current_batch_id = NULL WHERE house_id = 4");
echo "House 4 status set to 'available'\n";

echo "\n=== ACTIVE BATCHES NOW ===\n";
$activeBatches = $conn->query("SELECT batch_id, batch_code, house_pen, status, flock_type, breed FROM batches WHERE status='active'")->fetchAll(PDO::FETCH_ASSOC);
print_r($activeBatches);

echo "\n=== HISTORY HEALTH RECORDS CHECK ===\n";
$hr = $conn->query("SELECT hr.health_id, b.batch_code, b.house_pen, hr.medicine_name FROM health_records hr JOIN batches b ON hr.batch_id = b.batch_id ORDER BY hr.date DESC")->fetchAll(PDO::FETCH_ASSOC);
print_r($hr);

echo "\n=== HISTORY FEED CONSUMPTION CHECK ===\n";
$fc = $conn->query("SELECT fc.consumption_id, b.batch_code, b.house_pen, i.item_name FROM feed_consumption fc JOIN batches b ON fc.batch_id = b.batch_id JOIN inventory i ON fc.inventory_id = i.inventory_id ORDER BY fc.date DESC")->fetchAll(PDO::FETCH_ASSOC);
print_r($fc);
