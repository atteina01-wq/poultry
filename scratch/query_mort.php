<?php
require __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SELECT m.*, b.batch_code FROM mortality_records m LEFT JOIN batches b ON m.batch_id = b.batch_id ORDER BY m.mortality_id DESC LIMIT 20");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total mortality records: " . count($rows) . "\n";
print_r($rows);

$batches = $conn->query("SELECT batch_id, batch_code FROM batches")->fetchAll(PDO::FETCH_ASSOC);
echo "Batches:\n";
print_r($batches);
