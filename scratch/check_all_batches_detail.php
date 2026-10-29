<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

echo "=== ALL BATCHES ===\n";
$stmt = $conn->query("SELECT * FROM batches");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($r) . "\n";
}

echo "\n=== ALL HOUSES ===\n";
$stmtH = $conn->query("SELECT * FROM houses");
while ($r = $stmtH->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($r) . "\n";
}

echo "\n=== RESERVATIONS ===\n";
$stmtR = $conn->query("SELECT * FROM reservations");
while ($r = $stmtR->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($r) . "\n";
}
