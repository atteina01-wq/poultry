<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("
    SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_SCHEMA = 'poultry_db'
      AND REFERENCED_TABLE_NAME = 'reservations'
");
echo "TABLES REFERENCING reservations:\n";
$fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($fks);
