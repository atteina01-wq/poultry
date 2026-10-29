<?php
require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Repositories/ReservationRepository.php';
require_once __DIR__ . '/../src/Repositories/CustomerRepository.php';
require_once __DIR__ . '/../src/Repositories/AuditLogRepository.php';
require_once __DIR__ . '/../src/Repositories/BatchRepository.php';
require_once __DIR__ . '/../src/Services/ReservationService.php';

$resService = new \Poultry\Services\ReservationService();

echo "=== TEST 1: Double Reservation Prevention on Same House ===\n";
// Let's test house 2 (available)
$h2 = $conn->query("SELECT house_id, house_name, status FROM houses WHERE house_id = 2")->fetch(PDO::FETCH_ASSOC);
echo "House 2 initial status: {$h2['status']}\n";

// Create first reservation on House 2
$data1 = [
    'customer_id' => 11,
    'house_id' => 2,
    'batch_id' => null,
    'quantity_birds' => 2000,
    'weight_kg' => 4000.00,
    'target_weight_per_bird' => 2.00,
    'unit_price' => 180.00,
    'total_amount' => 360000.00,
    'advance_payment' => 180000.00,
    'pickup_date' => date('Y-m-d', strtotime('+7 days')),
    'notes' => 'Test Unit 1'
];
$res1 = $resService->createBroilerReservation($data1, 8);
echo "Created Res 1: #{$res1['reservation_number']} (ID: {$res1['reservation_id']})\n";

// House 2 should now be 'reserved'
$h2After = $conn->query("SELECT status FROM houses WHERE house_id = 2")->fetchColumn();
echo "House 2 status after Res 1: {$h2After}\n";

// Attempt second reservation on House 2 (MUST BE BLOCKED)
$blocked = false;
try {
    $data2 = [
        'customer_id' => 11,
        'house_id' => 2,
        'batch_id' => null,
        'quantity_birds' => 3000,
        'weight_kg' => 6000.00,
        'target_weight_per_bird' => 2.00,
        'unit_price' => 180.00,
        'total_amount' => 540000.00,
        'advance_payment' => 200000.00,
        'pickup_date' => date('Y-m-d', strtotime('+7 days')),
        'notes' => 'Test Unit 2 (Attempt duplicate)'
    ];
    $res2 = $resService->createBroilerReservation($data2, 8);
    echo "ERROR: Second reservation on House 2 was NOT blocked!\n";
} catch (\Exception $e) {
    $blocked = true;
    echo "SUCCESS: Second reservation on House 2 was blocked: " . $e->getMessage() . "\n";
}

echo "\n=== TEST 2: Occupied House Booking Prevention ===\n";
// House 5 is occupied by Batch 46
$occBlocked = false;
try {
    $dataOcc = [
        'customer_id' => 11,
        'house_id' => 5,
        'batch_id' => null, // Trying to book vacant pen while occupied
        'quantity_birds' => 3000,
        'total_amount' => 540000.00,
        'advance_payment' => 200000.00,
        'pickup_date' => date('Y-m-d', strtotime('+7 days'))
    ];
    $resService->createBroilerReservation($dataOcc, 8);
    echo "ERROR: Occupied house booking was NOT blocked!\n";
} catch (\Exception $e) {
    $occBlocked = true;
    echo "SUCCESS: Occupied house booking blocked: " . $e->getMessage() . "\n";
}

echo "\n=== TEST 3: Cleanup Test Reservation & House Revert ===\n";
$resService->deleteReservation((int)$res1['reservation_id'], 8);
$h2Revert = $conn->query("SELECT status FROM houses WHERE house_id = 2")->fetchColumn();
echo "House 2 status after deleting Res 1: {$h2Revert}\n";

echo "\n=== TEST RESULTS SUMMARY ===\n";
echo "Double Reservation Blocked: " . ($blocked ? "PASSED" : "FAILED") . "\n";
echo "Occupied Booking Blocked: " . ($occBlocked ? "PASSED" : "FAILED") . "\n";
echo "House Status Auto-Revert: " . ($h2Revert === 'available' ? "PASSED" : "FAILED") . "\n";
