<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

$targetBatches = [46, 38, 6, 37, 35, 31];

echo "=== CHECKING AUDIT LOGS FOR HEALTH RECORDS OF TARGET BATCHES ===\n";
$stmt = $conn->query("SELECT record_id, new_values FROM audit_logs WHERE table_name = 'health_records'");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $vals = json_decode($r['new_values'], true);
    if ($vals && isset($vals['batch_id']) && in_array((int)$vals['batch_id'], $targetBatches)) {
        echo "Health Record ID: {$r['record_id']} | Batch: {$vals['batch_id']} | Values: {$r['new_values']}\n";
    }
}

echo "\n=== CHECKING AUDIT LOGS FOR FEED CONSUMPTION OF TARGET BATCHES ===\n";
$stmt2 = $conn->query("SELECT record_id, new_values FROM audit_logs WHERE table_name = 'feed_consumption'");
while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    $vals = json_decode($r['new_values'], true);
    if ($vals && isset($vals['batch_id']) && in_array((int)$vals['batch_id'], $targetBatches)) {
        echo "Feed Consumption ID: {$r['record_id']} | Batch: {$vals['batch_id']} | Values: {$r['new_values']}\n";
    }
}
