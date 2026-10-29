<?php
require_once __DIR__ . '/../src/autoload.php';
use Poultry\Core\Database;
$conn = Database::getInstance()->getConnection();
$stmt = $conn->query("SELECT reservation_id, reservation_number, customer_id, house_id, status, workflow_stage, quantity_birds, pickup_date, created_by FROM reservations WHERE reservation_number LIKE '%20261203-001%' OR house_id = 6");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
