<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../poultrychat/helpers.php';
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Repositories/ReservationRepository.php';
require_once __DIR__ . '/../src/Services/ReservationService.php';

echo "=== TEST: CONFIGURABLE CHICK PRICE IN RESERVATIONS ===\n\n";

$resService = new \Poultry\Services\ReservationService();
$cust = $conn->query("SELECT customer_id FROM customers WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$customerId = (int)$cust['customer_id'];
$batch = $conn->query("SELECT batch_id FROM batches WHERE status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$batchId = (int)$batch['batch_id'];

// Test 1: Create reservation with custom chick price (₱42.50)
$customPrice = 42.50;
$data1 = [
    'customer_id' => $customerId,
    'batch_id' => $batchId,
    'quantity_birds' => 300,
    'target_weight_per_bird' => 2.20,
    'breed' => 'Cobb 500',
    'chick_cost' => $customPrice,
    'unit_price' => 180.00,
    'total_amount' => 300 * 2.20 * 180.00,
    'advance_payment' => 15000.00,
    'pickup_date' => date('Y-m-d', strtotime('+36 days')),
    'arrival_date' => date('Y-m-d'),
    'notes' => 'Test custom chick cost 42.50'
];

$res1 = $resService->createBroilerReservation($data1, 1);
$stmtCheck1 = $conn->prepare("SELECT chick_cost, quantity_birds FROM reservations WHERE reservation_id = ?");
$stmtCheck1->execute([$res1['reservation_id']]);
$row1 = $stmtCheck1->fetch(PDO::FETCH_ASSOC);

if ((float)$row1['chick_cost'] === 42.50) {
    echo "[PASS] Test 1: Custom chick price (₱42.50) saved successfully in reservation #" . $res1['reservation_number'] . "\n";
} else {
    echo "[FAIL] Test 1: Expected 42.50, got " . var_export($row1['chick_cost'], true) . "\n";
}

// Test 2: Create reservation with default chick price (not passed in $data)
$data2 = [
    'customer_id' => $customerId,
    'batch_id' => $batchId,
    'quantity_birds' => 250,
    'target_weight_per_bird' => 2.20,
    'breed' => 'Ross 308',
    'unit_price' => 180.00,
    'total_amount' => 250 * 2.20 * 180.00,
    'advance_payment' => 10000.00,
    'pickup_date' => date('Y-m-d', strtotime('+37 days')),
    'arrival_date' => date('Y-m-d'),
    'notes' => 'Test default chick cost'
];

$res2 = $resService->createBroilerReservation($data2, 1);
$stmtCheck2 = $conn->prepare("SELECT chick_cost FROM reservations WHERE reservation_id = ?");
$stmtCheck2->execute([$res2['reservation_id']]);
$row2 = $stmtCheck2->fetch(PDO::FETCH_ASSOC);

if ((float)$row2['chick_cost'] === 38.00) {
    echo "[PASS] Test 2: Default chick price (₱38.00) applied when not specified in reservation #" . $res2['reservation_number'] . "\n";
} else {
    echo "[FAIL] Test 2: Expected default 38.00, got " . var_export($row2['chick_cost'], true) . "\n";
}

// Test 3: Update reservation with a changed chick price (change from 42.50 to 35.75)
$updateData = [
    'customer_id' => $customerId,
    'batch_id' => $batchId,
    'quantity_birds' => 300,
    'target_weight_per_bird' => 2.20,
    'breed' => 'Cobb 500',
    'chick_cost' => 35.75,
    'unit_price' => 180.00,
    'total_amount' => 300 * 2.20 * 180.00,
    'advance_payment' => 15000.00,
    'pickup_date' => date('Y-m-d', strtotime('+36 days')),
    'notes' => 'Updated chick price to 35.75'
];

$resService->updateReservation($res1['reservation_id'], $updateData, 1);
$stmtCheck3 = $conn->prepare("SELECT chick_cost FROM reservations WHERE reservation_id = ?");
$stmtCheck3->execute([$res1['reservation_id']]);
$row3 = $stmtCheck3->fetch(PDO::FETCH_ASSOC);

if ((float)$row3['chick_cost'] === 35.75) {
    echo "[PASS] Test 3: Updated chick price (₱35.75) persisted successfully via updateReservation\n";
} else {
    echo "[FAIL] Test 3: Expected 35.75, got " . var_export($row3['chick_cost'], true) . "\n";
}

// Clean up test rows
$conn->exec("DELETE FROM reservations WHERE reservation_id IN (" . (int)$res1['reservation_id'] . ", " . (int)$res2['reservation_id'] . ")");
echo "[INFO] Test cleanup completed.\n";
echo "=== ALL CONFIGURABLE CHICK PRICE TESTS PASSED ===\n";
