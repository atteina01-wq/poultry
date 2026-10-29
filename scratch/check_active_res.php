<?php
require_once __DIR__ . '/../poultrychat/database.php';

$res = $conn->query("SELECT reservation_id, reservation_number, customer_id, house_id, batch_id, status, advance_payment, total_amount, pickup_date FROM reservations WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
echo "=== ACTIVE RESERVATIONS ===\n";
print_r($res);
