<?php
require_once __DIR__ . '/../poultrychat/database.php';
$pdo = $conn;

$tables = [
    'batches',
    'mortality_records',
    'health_records',
    'feed_consumption',
    'inventory',
    'activities',
    'reservations',
    'orders',
    'order_items',
    'expenses'
];

foreach ($tables as $tbl) {
    echo "=== $tbl ===" . PHP_EOL;
    $stmt = $pdo->query("SHOW COLUMNS FROM `$tbl`");
    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  " . $col['Field'] . " (" . $col['Type'] . ") Null:" . $col['Null'] . " Key:" . $col['Key'] . " Def:" . ($col['Default'] ?? 'NULL') . PHP_EOL;
    }
}
