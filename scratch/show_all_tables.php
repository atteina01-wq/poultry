<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "ALL TABLES IN poultry_db:\n";
print_r($tables);
