<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';
$stmt = $conn->query("SELECT batch_id, batch_code, current_weight_kg, target_weight_kg, house_pen, status FROM batches");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
    echo "Batch {$b['batch_id']}: Code={$b['batch_code']} | Current={$b['current_weight_kg']} | Target={$b['target_weight_kg']} | House={$b['house_pen']} | Status={$b['status']}\n";
}
