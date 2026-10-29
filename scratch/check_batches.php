<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SELECT batch_id, batch_code, house_pen, supplier, initial_quantity, status, created_at FROM batches ORDER BY batch_id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
