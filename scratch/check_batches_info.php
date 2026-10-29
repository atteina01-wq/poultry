<?php
require __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query('SELECT batch_id, batch_code, arrival_date, current_quantity, house_pen, status FROM batches');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
