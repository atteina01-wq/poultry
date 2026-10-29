<?php
require_once __DIR__ . '/../poultrychat/database.php';

$tables = ['orders', 'order_items', 'reservations', 'reservation_items', 'expenses', 'feed_consumption', 'health_records', 'mortality_records', 'batches', 'inventory', 'activities', 'audit_logs', 'customers'];

foreach ($tables as $t) {
    echo "=== Table: $t ===\n";
    $stmt = $conn->query("SHOW COLUMNS FROM `$t`");
    if ($stmt) {
        while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  {$col['Field']} - {$col['Type']} - Null:{$col['Null']} - Key:{$col['Key']} - Default:{$col['Default']}\n";
        }
    } else {
        echo "  [TABLE NOT FOUND]\n";
    }
    echo "\n";
}
