<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query('SHOW COLUMNS FROM inventory_lots');
echo "inventory_lots columns:\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo " - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}
