<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

$b = $conn->query("SELECT * FROM batches WHERE batch_id = 49 OR batch_code LIKE '%523%'")->fetchAll(PDO::FETCH_ASSOC);
echo "Batches matching 49 or 523:\n";
print_r($b);

// Check all tables for batch_id = 49
$tables = ['feed_consumption', 'activities', 'health_records', 'cleaning_records', 'mortality_records', 'expenses', 'tasks', 'waste_records', 'reservations'];
foreach ($tables as $t) {
    $c = $conn->query("SELECT COUNT(*) FROM `$t` WHERE batch_id = 49")->fetchColumn();
    echo "Table $t has $c rows for batch_id 49\n";
}

// Check House 1 status
$h1 = $conn->query("SELECT * FROM houses WHERE house_name = 'House 1'")->fetch(PDO::FETCH_ASSOC);
echo "\nHouse 1 status:\n";
print_r($h1);
