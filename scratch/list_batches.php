<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

$batches = $conn->query("SELECT batch_id, batch_code, status, house_pen FROM batches ORDER BY batch_id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
print_r($batches);
