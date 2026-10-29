<?php
require_once __DIR__ . '/../poultrychat/database.php';
$queries = [
    'batches' => 'SELECT COUNT(*) FROM batches',
    'mortality_records' => 'SELECT COUNT(*) FROM mortality_records',
    'health_records' => 'SELECT COUNT(*) FROM health_records',
    'feed_consumption' => 'SELECT COUNT(*) FROM feed_consumption',
    'inventory' => 'SELECT COUNT(*) FROM inventory',
    'activities' => 'SELECT COUNT(*) FROM activities',
    'reservations' => 'SELECT COUNT(*) FROM reservations',
    'orders' => 'SELECT COUNT(*) FROM orders',
    'order_returns' => 'SELECT COUNT(*) FROM order_returns',
    'expenses' => 'SELECT COUNT(*) FROM expenses'
];

foreach ($queries as $tbl => $q) {
    echo "$tbl count: " . $conn->query($q)->fetchColumn() . PHP_EOL;
}
