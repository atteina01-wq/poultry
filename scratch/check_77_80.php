<?php
require_once __DIR__ . '/../poultrychat/database.php';

$stmt = $conn->query("SELECT reservation_id, reservation_number, status, total_amount, advance_payment, actual_price, receipt_number, notes FROM reservations WHERE reservation_id IN (77, 78, 79, 80)");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
