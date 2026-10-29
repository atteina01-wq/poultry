<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "=== HOUSES ===\n";
$houses = $conn->query("SELECT house_id, house_name, capacity, status, current_batch_id, notes FROM houses")->fetchAll(PDO::FETCH_ASSOC);
print_r($houses);

echo "\n=== BATCHES (columns & recent rows) ===\n";
$bCols = $conn->query("DESCRIBE batches")->fetchAll(PDO::FETCH_COLUMN);
echo "Columns: " . implode(', ', $bCols) . "\n";
$batches = $conn->query("SELECT * FROM batches ORDER BY batch_id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($batches);

echo "\n=== ALL RESERVATIONS FOR HOUSE 6 ===\n";
$h6Res = $conn->query("SELECT * FROM reservations WHERE house_id = 6 ORDER BY reservation_id DESC")->fetchAll(PDO::FETCH_ASSOC);
print_r($h6Res);
