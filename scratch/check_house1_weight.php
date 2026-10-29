<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

echo "=== HOUSE 1 INFO ===\n";
$stmtH = $conn->query("SELECT * FROM houses WHERE house_id = 1");
print_r($stmtH->fetch(PDO::FETCH_ASSOC));

echo "\n=== RESERVATIONS FOR HOUSE 1 ===\n";
$stmtR = $conn->query("SELECT * FROM reservations WHERE house_id = 1 ORDER BY reservation_id DESC");
$res = $stmtR->fetchAll(PDO::FETCH_ASSOC);
foreach ($res as $r) {
    echo "Res ID: {$r['reservation_id']} | #{$r['reservation_number']} | Birds: {$r['quantity_birds']} | Weight: {$r['weight_kg']} | TargetWt: {$r['target_weight_per_bird']} | BatchID: {$r['batch_id']} | Status: {$r['status']} | Cust: {$r['customer_id']}\n";
}

echo "\n=== ALL RECENT RESERVATIONS (TOP 5) ===\n";
$stmtRAll = $conn->query("SELECT * FROM reservations ORDER BY reservation_id DESC LIMIT 5");
foreach ($stmtRAll->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "Res ID: {$r['reservation_id']} | House: {$r['house_id']} | #{$r['reservation_number']} | Birds: {$r['quantity_birds']} | Weight: {$r['weight_kg']} | TargetWt: {$r['target_weight_per_bird']} | BatchID: {$r['batch_id']} | Status: {$r['status']}\n";
}

echo "\n=== BATCHES FOR HOUSE 1 OR PEN 1 ===\n";
$stmtB = $conn->query("SELECT * FROM batches WHERE house_pen LIKE '%1%' OR batch_id IN (SELECT current_batch_id FROM houses WHERE house_id = 1)");
foreach ($stmtB->fetchAll(PDO::FETCH_ASSOC) as $b) {
    echo "Batch ID: {$b['batch_id']} | Code: {$b['batch_code']} | House: {$b['house_pen']} | Qty: {$b['current_quantity']} | TargetWt: {$b['target_weight_kg']} | CurrentWt: {$b['current_weight_kg']} | Status: {$b['status']}\n";
}

echo "\n=== WEIGHT SAMPLES (if table exists) ===\n";
try {
    $stmtW = $conn->query("SELECT * FROM weight_samplings ORDER BY sample_id DESC LIMIT 10");
    foreach ($stmtW->fetchAll(PDO::FETCH_ASSOC) as $w) {
        print_r($w);
    }
} catch (Exception $e) {
    echo "No weight_samplings table: " . $e->getMessage() . "\n";
}
