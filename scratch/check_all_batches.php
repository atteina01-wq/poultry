<?php
require __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SELECT batch_id, batch_code, arrival_date, current_quantity, house_pen, status, target_weight_kg, current_weight_kg FROM batches");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total batches: " . count($rows) . "\n";
foreach ($rows as $r) {
    $age = floor((strtotime('2026-10-04') - strtotime($r['arrival_date'])) / 86400);
    echo "ID: {$r['batch_id']} | Code: {$r['batch_code']} | House: {$r['house_pen']} | Status: {$r['status']} | Birds: {$r['current_quantity']} | Arr: {$r['arrival_date']} (Age: {$age}d) | W: {$r['current_weight_kg']}kg / T: {$r['target_weight_kg']}kg\n";
}
