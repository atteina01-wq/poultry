<?php
require_once __DIR__ . '/../poultrychat/database.php';

$batchIds = [51, 52, 53];
$tables = ['feed_consumption', 'health_records', 'mortality_records', 'cleaning_records', 'activities', 'tasks', 'audit_logs', 'expenses'];
foreach ($tables as $t) {
    try {
        $cols = $conn->query("DESCRIBE $t")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('batch_id', $cols)) {
            $cnt = $conn->query("SELECT COUNT(*) FROM `$t` WHERE batch_id IN (" . implode(',', $batchIds) . ")")->fetchColumn();
            echo "$t has $cnt records with batch_ids 51, 52, 53\n";
        }
    } catch (Exception $e) {
        echo "$t error: " . $e->getMessage() . "\n";
    }
}
