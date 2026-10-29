<?php
require_once __DIR__ . '/../src/autoload.php';
$conn = Poultry\Core\Database::getInstance()->getConnection();

echo "=== INSPECTING HOUSES 1, 3, 6 ===\n";
foreach ([1, 3, 6] as $hid) {
    echo "\n--- HOUSE ID: $hid ---\n";
    $h = $conn->query("SELECT * FROM houses WHERE house_id = $hid")->fetch(PDO::FETCH_ASSOC);
    print_r($h);

    $houseName = $h['house_name'] ?? "House $hid";
    echo "Reservations for House $hid:\n";
    $res = $conn->query("SELECT reservation_id, reservation_number, customer_id, house_id, batch_id, quantity_birds, status, workflow_stage FROM reservations WHERE house_id = $hid")->fetchAll(PDO::FETCH_ASSOC);
    print_r($res);

    echo "Batches in $houseName:\n";
    $batches = $conn->query("SELECT batch_id, batch_code, status, current_quantity, house_pen FROM batches WHERE house_pen = '$houseName' OR house_pen = 'House $hid' OR batch_id = " . intval($h['current_batch_id'] ?? 0))->fetchAll(PDO::FETCH_ASSOC);
    print_r($batches);
}
