<?php
require_once __DIR__ . '/../poultrychat/database.php';
$batches = $conn->query("SELECT batch_id, batch_code, breed, house_pen, arrival_date, expected_harvest_date, initial_quantity, current_quantity, target_weight_kg, current_weight_kg, status FROM batches")->fetchAll(PDO::FETCH_ASSOC);
echo "CURRENT BATCHES DATA:\n";
foreach ($batches as $b) {
    echo json_encode($b) . "\n";
}
