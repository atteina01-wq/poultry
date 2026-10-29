<?php
require __DIR__ . '/../poultrychat/database.php';

$batches = $conn->query("SELECT batch_id, batch_code, status, current_quantity, initial_quantity FROM batches ORDER BY status DESC, batch_id ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($batches as $b) {
    echo "ID: {$b['batch_id']} | Code: {$b['batch_code']} | Status: {$b['status']} | Live: {$b['current_quantity']}\n";
}
