<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

echo "=== CHECKING INVENTORY BEFORE RESTORE ===\n";
$inv = $conn->query("SELECT inventory_id, item_name, quantity, unit FROM inventory WHERE inventory_id IN (2, 28)")->fetchAll(PDO::FETCH_ASSOC);
print_r($inv);

echo "\n=== CHECKING RESERVATIONS ===\n";
$res = $conn->query("SELECT * FROM reservations WHERE notes LIKE '%BR-20261013%' OR customer_id = 1 OR customer_id = 7 ORDER BY reservation_id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);

echo "\n=== CHECKING HOUSES 2 AND 6 ===\n";
$houses = $conn->query("SELECT house_id, house_name, capacity, status, current_batch_id, vacant_date FROM houses WHERE house_name IN ('House 2', 'House 6')")->fetchAll(PDO::FETCH_ASSOC);
print_r($houses);
