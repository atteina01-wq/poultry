<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

$ids = [48, 49, 50];

// Find all tables that have batch_id column
$tablesWithBatchId = $conn->query("
    SELECT TABLE_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE COLUMN_NAME = 'batch_id' AND TABLE_SCHEMA = 'poultry_db'
")->fetchAll(PDO::FETCH_COLUMN);

echo "Tables with batch_id: " . implode(', ', $tablesWithBatchId) . "\n\n";

foreach ($tablesWithBatchId as $table) {
    foreach ($ids as $id) {
        $count = $conn->query("SELECT COUNT(*) FROM `$table` WHERE batch_id = $id")->fetchColumn();
        if ($count > 0) {
            echo "Table `$table` has $count rows for batch_id $id\n";
            $rows = $conn->query("SELECT * FROM `$table` WHERE batch_id = $id")->fetchAll(PDO::FETCH_ASSOC);
            print_r($rows);
        }
    }
}

// Also check audit_logs
foreach ($ids as $id) {
    $audit = $conn->query("SELECT COUNT(*) FROM audit_logs WHERE table_name = 'batches' AND record_id = $id")->fetchColumn();
    echo "audit_logs for batches record_id $id: $audit\n";
}
