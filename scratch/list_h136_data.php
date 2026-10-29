<?php
require_once __DIR__ . '/../src/autoload.php';
$conn = Poultry\Core\Database::getInstance()->getConnection();

$stmt = $conn->query("SELECT reservation_id, reservation_number, customer_id, house_id, batch_id, status, workflow_stage, created_at FROM reservations WHERE house_id IN (1, 3, 6)");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmtB = $conn->query("SELECT batch_id, batch_code, status, house_pen, created_at FROM batches WHERE house_pen IN ('House 1', 'House 3', 'House 6') OR batch_id IN (58, 59, 61)");
print_r($stmtB->fetchAll(PDO::FETCH_ASSOC));
