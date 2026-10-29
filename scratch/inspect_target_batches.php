<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

$codes = ['BR-20261013-674', 'BR-20261013-523', 'BR-20261013-974'];
$in = "'" . implode("','", $codes) . "'";

$batches = $conn->query("SELECT * FROM batches WHERE batch_code IN ($in)")->fetchAll(PDO::FETCH_ASSOC);
echo "Batches found:\n";
print_r($batches);

foreach ($batches as $b) {
    $id = $b['batch_id'];
    echo "\n=== Data linked to batch {$b['batch_code']} (ID {$id}) ===\n";
    
    // feed_consumption
    $fc = $conn->query("SELECT COUNT(*) FROM feed_consumption WHERE batch_id = $id")->fetchColumn();
    echo "feed_consumption: $fc\n";
    
    // activities
    $act = $conn->query("SELECT COUNT(*) FROM activities WHERE batch_id = $id")->fetchColumn();
    echo "activities: $act\n";

    // health_records
    $hr = $conn->query("SELECT COUNT(*) FROM health_records WHERE batch_id = $id")->fetchColumn();
    echo "health_records: $hr\n";

    // mortality_records
    $mr = $conn->query("SELECT COUNT(*) FROM mortality_records WHERE batch_id = $id")->fetchColumn();
    echo "mortality_records: $mr\n";

    // expenses
    $exp = $conn->query("SELECT COUNT(*) FROM expenses WHERE batch_id = $id")->fetchColumn();
    echo "expenses: $exp\n";

    // reservations
    try {
        $res = $conn->query("SELECT COUNT(*) FROM reservations WHERE batch_id = $id")->fetchColumn();
        echo "reservations: $res\n";
    } catch (Exception $e) {}

    // orders
    try {
        $ord = $conn->query("SELECT COUNT(*) FROM orders WHERE batch_id = $id")->fetchColumn();
        echo "orders: $ord\n";
    } catch (Exception $e) {}

    // houses
    try {
        $h = $conn->query("SELECT house_name, status, current_batch_id FROM houses WHERE current_batch_id = $id")->fetchAll(PDO::FETCH_ASSOC);
        echo "houses referencing this batch: " . json_encode($h) . "\n";
    } catch (Exception $e) {}
}

// Let's also check all recent batches created today (2026-10-13)
echo "\n=== All batches with arrival_date or code from 2026-10-13 ===\n";
$allToday = $conn->query("SELECT batch_id, batch_code, house_pen, status, arrival_date, current_quantity, initial_quantity FROM batches WHERE batch_code LIKE 'BR-20261013%' OR arrival_date = '2026-10-13'")->fetchAll(PDO::FETCH_ASSOC);
print_r($allToday);
