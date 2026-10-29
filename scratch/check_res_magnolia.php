<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("
    SELECT r.reservation_id, r.reservation_number, r.customer_id, c.name, r.breed, r.notes 
    FROM reservations r 
    LEFT JOIN customers c ON r.customer_id = c.customer_id
");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$cStmt = $conn->query("SELECT customer_id, name, customer_code FROM customers WHERE name LIKE '%magnolia%'");
print_r($cStmt->fetchAll(PDO::FETCH_ASSOC));
