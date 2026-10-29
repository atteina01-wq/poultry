<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "=== AUDIT OF ALL TABLES IN POULTRY_DB ===\n\n";

$tables = ['batches', 'customers', 'activities', 'tasks', 'inventory', 'feed_inventory', 'health_records', 'mortality', 'users', 'houses', 'expenses'];

foreach ($tables as $t) {
    try {
        $count = $conn->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        echo "Table [{$t}]: {$count} rows\n";
    } catch (Exception $e) {
        echo "Table [{$t}]: " . $e->getMessage() . "\n";
    }
}
