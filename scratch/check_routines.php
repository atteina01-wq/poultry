<?php
require __DIR__ . '/../poultrychat/database.php';

echo "=== CHECKING VIEWS ===\n";
$views = $conn->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'VIEW'")->fetchAll(PDO::FETCH_ASSOC);
print_r($views);

echo "=== CHECKING PROCEDURES ===\n";
$procs = $conn->query("SHOW PROCEDURE STATUS WHERE Db = DATABASE()")->fetchAll(PDO::FETCH_ASSOC);
print_r($procs);

echo "=== CHECKING FUNCTIONS ===\n";
$funcs = $conn->query("SHOW FUNCTION STATUS WHERE Db = DATABASE()")->fetchAll(PDO::FETCH_ASSOC);
print_r($funcs);
