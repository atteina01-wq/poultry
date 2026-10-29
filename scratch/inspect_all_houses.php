<?php
require_once __DIR__ . '/../src/autoload.php';
$conn = Poultry\Core\Database::getInstance()->getConnection();

$houses = $conn->query("SELECT house_id, house_name, capacity, status, current_batch_id FROM houses ORDER BY house_id")->fetchAll(PDO::FETCH_ASSOC);
echo "ALL HOUSES:\n";
print_r($houses);

$activeBatches = $conn->query("SELECT batch_id, batch_code, status, current_quantity, house_pen FROM batches WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
echo "ACTIVE BATCHES:\n";
print_r($activeBatches);

$activeRes = $conn->query("SELECT reservation_id, reservation_number, house_id, batch_id, quantity_birds, status, workflow_stage FROM reservations WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
echo "ACTIVE RESERVATIONS:\n";
print_r($activeRes);
