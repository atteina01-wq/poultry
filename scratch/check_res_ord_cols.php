<?php
require_once __DIR__ . '/../poultrychat/database.php';
$cols = $conn->query("SHOW COLUMNS FROM reservations")->fetchAll(PDO::FETCH_COLUMN);
echo "reservations columns: " . implode(', ', $cols) . PHP_EOL;

$cols2 = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
echo "orders columns: " . implode(', ', $cols2) . PHP_EOL;
