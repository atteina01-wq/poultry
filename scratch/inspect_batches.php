<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SELECT batch_id, batch_code, status, current_quantity, house_pen FROM batches");
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "CURRENT BATCHES IN SYSTEM:\n";
foreach ($batches as $b) {
    echo "ID: {$b['batch_id']} | Code: {$b['batch_code']} | Status: {$b['status']} | Qty: {$b['current_quantity']} | House: {$b['house_pen']}\n";
}
