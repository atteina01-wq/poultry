<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

echo "=== HEALTH RECORD 58 AUDIT LOG ===\n";
$stmtH = $conn->query("SELECT * FROM audit_logs WHERE table_name = 'health_records' AND record_id = 58");
print_r($stmtH->fetchAll(PDO::FETCH_ASSOC));

echo "=== FEED CONSUMPTION AUDIT LOGS (69, 70, 85, 86) ===\n";
$stmtF = $conn->query("SELECT * FROM audit_logs WHERE table_name = 'feed_consumption' AND record_id IN (69, 70, 85, 86)");
print_r($stmtF->fetchAll(PDO::FETCH_ASSOC));

echo "=== INVENTORY ITEMS ===\n";
$stmtInv = $conn->query("SELECT inventory_id, item_name, category FROM inventory WHERE inventory_id IN (1, 2, 8)");
print_r($stmtInv->fetchAll(PDO::FETCH_ASSOC));

echo "=== BATCHES 6 AND 31 ===\n";
$stmtB = $conn->query("SELECT batch_id, batch_code, flock_type, status FROM batches WHERE batch_id IN (6, 31)");
print_r($stmtB->fetchAll(PDO::FETCH_ASSOC));
