<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = 'reservations.php';
session_start();
$_SESSION['user_id'] = 12;
$_SESSION['role'] = 'integrator';
$_SESSION['customer_id'] = 13;

require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';
$where = ["1=1", "(r.customer_id = ? OR r.created_by = ?)"];
$params = [13, 12];
$limit = 10;
$offset = 0;

$sql = "
    SELECT r.*, 
           c.name as customer_name, c.customer_code, c.phone as customer_phone, c.address as customer_address,
           COALESCE(r.batch_id, h.current_batch_id) as effective_batch_id,
           COALESCE(b.batch_code, hb.batch_code) as batch_code,
           COALESCE(b.current_weight_kg, hb.current_weight_kg, 0.04) as batch_current_weight,
           COALESCE(b.target_weight_kg, hb.target_weight_kg, 2.20) as batch_target_weight,
           COALESCE(b.arrival_date, hb.arrival_date) as batch_arrival_date,
           h.house_name, h.capacity as house_capacity,
           COALESCE(r.quantity_birds, 0) AS display_quantity,
           COALESCE(r.total_amount, 0.00) AS total_amount,
           COALESCE(r.advance_payment, 0.00) AS advance_payment,
           u.full_name as created_by_name
    FROM reservations r
    JOIN customers c ON r.customer_id = c.customer_id
    LEFT JOIN houses h ON r.house_id = h.house_id
    LEFT JOIN batches b ON r.batch_id = b.batch_id
    LEFT JOIN batches hb ON h.current_batch_id = hb.batch_id
    LEFT JOIN users u ON r.created_by = u.user_id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY r.reservation_id DESC
    LIMIT $limit OFFSET $offset
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "SUCCESS: " . count($res) . " rows.\n";
    foreach ($res as $r) {
        echo "Res #{$r['reservation_id']} | #{$r['reservation_number']} | House: {$r['house_name']} | Batch: {$r['batch_code']} | W: {$r['batch_current_weight']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
