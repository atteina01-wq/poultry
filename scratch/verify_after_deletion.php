<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

echo "=== VERIFYING AFTER DELETION ===\n\n";

// 1. Check batches
$bRemaining = $conn->query("SELECT batch_id, batch_code, house_pen, status FROM batches WHERE batch_code IN ('BR-20261013-674', 'BR-20261013-974', 'BR-20261013-523')")->fetchAll(PDO::FETCH_ASSOC);
echo "Batches present:\n";
print_r($bRemaining);

// 2. Check inventory
$inv = $conn->query("SELECT inventory_id, item_name, quantity, unit FROM inventory WHERE inventory_id IN (2, 28)")->fetchAll(PDO::FETCH_ASSOC);
echo "\nInventory quantities:\n";
print_r($inv);

// 3. Check houses
$h = $conn->query("SELECT house_id, house_name, status, current_batch_id FROM houses WHERE house_name IN ('House 1', 'House 2', 'House 6')")->fetchAll(PDO::FETCH_ASSOC);
echo "\nHouses status:\n";
print_r($h);

// 4. Check feed_consumption count
$fcCount = $conn->query("SELECT COUNT(*) FROM feed_consumption")->fetchColumn();
echo "\nTotal feed_consumption records: $fcCount\n";
