<?php
require __DIR__ . '/../poultrychat/database.php';
$rows = $conn->query("
    SELECT r.reservation_id, r.reservation_number, r.customer_id, r.batch_id, r.house_id, r.quantity_birds, r.status, b.batch_code, c.name as customer_name
    FROM reservations r
    LEFT JOIN batches b ON r.batch_id = b.batch_id
    LEFT JOIN customers c ON r.customer_id = c.customer_id
")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
