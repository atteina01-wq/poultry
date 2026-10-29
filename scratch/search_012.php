<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

echo "=== SEARCHING BATCHES FOR 0.12 ===\n";
$stmtB = $conn->query("SELECT * FROM batches WHERE current_weight_kg = 0.12 OR target_weight_kg = 0.12");
foreach ($stmtB->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "Batch: {$row['batch_id']} | {$row['batch_code']} | House: {$row['house_pen']} | Curr: {$row['current_weight_kg']} | Target: {$row['target_weight_kg']} | Status: {$row['status']}\n";
}

echo "=== SEARCHING RESERVATIONS FOR 0.12 ===\n";
$stmtR = $conn->query("SELECT * FROM reservations WHERE target_weight_per_bird = 0.12");
foreach ($stmtR->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "Res: {$row['reservation_id']} | #{$row['reservation_number']} | House: {$row['house_id']} | Batch: {$row['batch_id']}\n";
}

echo "=== ALL ACTIVE RESERVATIONS WITH BATCH DETAILS ===\n";
$stmtAll = $conn->query("SELECT r.reservation_id, r.reservation_number, r.customer_id, c.name as customer_name, r.house_id, h.house_name, r.batch_id, b.batch_code, b.current_weight_kg, b.target_weight_kg, r.target_weight_per_bird, r.quantity_birds, r.status 
FROM reservations r
JOIN customers c ON r.customer_id = c.customer_id
LEFT JOIN houses h ON r.house_id = h.house_id
LEFT JOIN batches b ON r.batch_id = b.batch_id
WHERE r.status = 'active'");
foreach ($stmtAll->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "Res #{$row['reservation_id']} ({$row['reservation_number']}): Cust: {$row['customer_name']} | House: {$row['house_name']} (ID: {$row['house_id']}) | Batch: {$row['batch_code']} (ID: {$row['batch_id']}) | Birds: {$row['quantity_birds']} | ResTargetWt: {$row['target_weight_per_bird']} | BatchCurrWt: {$row['current_weight_kg']} | BatchTgtWt: {$row['target_weight_kg']}\n";
}
