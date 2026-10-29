<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';
$stmt = $conn->query("SELECT r.reservation_id, r.reservation_number, r.customer_id, r.house_id, r.batch_id, r.quantity_birds, r.target_weight_per_bird, r.status, b.batch_code, b.current_weight_kg, b.target_weight_kg, h.house_name 
FROM reservations r 
LEFT JOIN batches b ON r.batch_id = b.batch_id 
LEFT JOIN houses h ON r.house_id = h.house_id 
WHERE r.customer_id = 13");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    print_r($row);
}
