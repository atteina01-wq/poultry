<?php
require_once __DIR__ . '/../poultrychat/database.php';

$res = $conn->query("
    SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME IN ('orders', 'order_items', 'reservations', 'reservation_items')
      AND REFERENCED_TABLE_NAME IS NOT NULL
");

echo "Foreign Keys on Orders & Reservations:\n";
while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['TABLE_NAME']}.{$row['COLUMN_NAME']} -> {$row['REFERENCED_TABLE_NAME']}.{$row['REFERENCED_COLUMN_NAME']} (Constraint: {$row['CONSTRAINT_NAME']})\n";
}
