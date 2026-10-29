<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query('SHOW COLUMNS FROM mortality_records');
echo "mortality_records columns:\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo " - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

$stmt2 = $conn->query('SHOW COLUMNS FROM feed_consumption');
echo "\nfeed_consumption columns:\n";
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo " - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

$stmt3 = $conn->query('SHOW COLUMNS FROM health_records');
echo "\nhealth_records columns:\n";
foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo " - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}
