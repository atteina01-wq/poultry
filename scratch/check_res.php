<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SELECT reservation_id, reservation_number, customer_id, house_id, batch_id, status, workflow_stage, quantity_birds, accepted_quantity, receipt_number, created_at FROM reservations ORDER BY reservation_id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
