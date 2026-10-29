<?php
require __DIR__ . '/../poultrychat/database.php';

$tables = [
    'batches',
    'reservations',
    'customers',
    'inventory',
    'inventory_lots',
    'feed_consumption',
    'health_records',
    'mortality_records',
    'activities',
    'cleaning_records',
    'expenses',
    'tasks',
    'audit_logs',
    'notifications'
];

foreach ($tables as $t) {
    try {
        $count = $conn->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo str_pad($t, 20) . ": $count\n";
    } catch (Exception $e) {
        // table might not exist
    }
}
