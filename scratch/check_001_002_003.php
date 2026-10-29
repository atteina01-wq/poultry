<?php
require_once __DIR__ . '/../poultrychat/database.php';

$res = $conn->query("SELECT reservation_id, reservation_number, customer_id, batch_id, house_id, quantity_birds, weight_kg, unit_price, total_amount, advance_payment, pickup_date, actual_pickup_date, status, created_at FROM reservations WHERE reservation_number IN ('RES-20261013-001', 'RES-20261013-002', 'RES-20261013-003') ORDER BY reservation_id ASC")->fetchAll(PDO::FETCH_ASSOC);

print_r($res);
