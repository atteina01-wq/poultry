<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

echo "=== RESERVATION 104 ===\n";
$stmtR = $conn->query("SELECT * FROM reservations WHERE reservation_id = 104");
print_r($stmtR->fetch(PDO::FETCH_ASSOC));

echo "=== BATCH 58 ===\n";
$stmtB = $conn->query("SELECT * FROM batches WHERE batch_id = 58");
print_r($stmtB->fetch(PDO::FETCH_ASSOC));

echo "=== HOUSE 1 ===\n";
$stmtH = $conn->query("SELECT * FROM houses WHERE house_id = 1");
print_r($stmtH->fetch(PDO::FETCH_ASSOC));
