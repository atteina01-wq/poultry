<?php
require_once __DIR__ . '/../poultrychat/database.php';

$res = $conn->query("
    SELECT r.reservation_id, r.reservation_number, r.house_id, r.batch_id, r.quantity_birds, r.status,
           b.batch_code, b.house_pen, b.current_quantity, b.initial_quantity,
           h.house_name, h.capacity, h.status as house_status
    FROM reservations r
    LEFT JOIN batches b ON r.batch_id = b.batch_id
    LEFT JOIN houses h ON r.house_id = h.house_id OR b.house_pen = h.house_name
    WHERE r.status = 'active'
")->fetchAll(PDO::FETCH_ASSOC);

print_r($res);
