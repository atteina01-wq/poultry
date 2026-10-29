<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'poultry_db'
      AND COLUMN_NAME LIKE '%reservation%'
");
echo "COLUMNS WITH 'reservation':\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo "{$c['TABLE_NAME']}.{$c['COLUMN_NAME']}\n";
}
