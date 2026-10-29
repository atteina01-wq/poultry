<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("SELECT * FROM reservations WHERE customer_id IN (2, 6)");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
