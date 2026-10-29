<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_SCHEMA = 'poultry_db'
      AND REFERENCED_TABLE_NAME = 'customers'
");
echo "TABLES REFERENCING customers:\n";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
