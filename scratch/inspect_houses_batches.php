<?php
require_once __DIR__ . '/../poultrychat/database.php';

$houses = $conn->query("SELECT * FROM houses")->fetchAll(PDO::FETCH_ASSOC);
echo "HOUSES:\n";
foreach ($houses as $h) {
    echo "ID: {$h['house_id']} | Name: {$h['house_name']} | Status: {$h['status']} | BatchID: {$h['current_batch_id']} | Cap: {$h['capacity']} | VacantDate: {$h['vacant_date']}\n";
}

$batches = $conn->query("SELECT batch_id, batch_code, breed, house_pen, arrival_date, current_quantity, target_weight_kg, current_weight_kg FROM batches")->fetchAll(PDO::FETCH_ASSOC);
echo "\nBATCHES:\n";
foreach ($batches as $b) {
    echo "ID: {$b['batch_id']} | Code: {$b['batch_code']} | House: {$b['house_pen']} | Arrival: {$b['arrival_date']} | Qty: {$b['current_quantity']} | Target: {$b['target_weight_kg']} | Current: {$b['current_weight_kg']}\n";
}
