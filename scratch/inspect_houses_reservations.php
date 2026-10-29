<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "--- HOUSES ---\n";
$stmtH = $conn->query("SELECT house_id, house_name, capacity, status, current_batch_id FROM houses");
foreach ($stmtH->fetchAll(PDO::FETCH_ASSOC) as $h) {
    echo json_encode($h) . "\n";
}

echo "--- ACTIVE RESERVATIONS ---\n";
$stmtR = $conn->query("SELECT reservation_id, reservation_number, customer_id, batch_id, house_id, quantity_birds, status FROM reservations WHERE status='active'");
foreach ($stmtR->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo json_encode($r) . "\n";
}

echo "--- ACTIVE BATCHES IN HOUSES ---\n";
$stmtB = $conn->query("SELECT batch_id, batch_code, house_pen, initial_quantity, current_quantity, status FROM batches WHERE status='active'");
foreach ($stmtB->fetchAll(PDO::FETCH_ASSOC) as $b) {
    echo json_encode($b) . "\n";
}
