<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

$rows = $conn->query("SELECT batch_id, batch_code, house_pen, arrival_date FROM batches WHERE arrival_date = '2026-10-13' OR batch_code LIKE '%20261013%'")->fetchAll(PDO::FETCH_ASSOC);
echo "Batches from 2026-10-13 remaining: " . count($rows) . "\n";
print_r($rows);
