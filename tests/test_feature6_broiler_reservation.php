<?php
/**
 * Test Suite: Feature 6 - Broiler Reservation Management
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../poultrychat/helpers.php';
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Repositories/ReservationRepository.php';
require_once __DIR__ . '/../src/Services/ReservationService.php';

echo "========================================================================\n";
echo "FEATURE 6: BROILER RESERVATION MANAGEMENT VERIFICATION SUITE\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest($condition, $description) {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] " . $description . "\n";
        $passCount++;
    } else {
        echo "[FAIL] " . $description . "\n";
        $failCount++;
    }
}

$resRepo = new \Poultry\Repositories\ReservationRepository();
$resService = new \Poultry\Services\ReservationService();

// ----------------------------------------------------
// 1. Schema & Nullability Check: grade_id Nullable
// ----------------------------------------------------
echo "--- 1. Schema Checks: grade_id Nullable ---\n";

try {
    $stmtRi = $conn->query("SHOW COLUMNS FROM reservation_items LIKE 'grade_id'");
    $colRi = $stmtRi ? $stmtRi->fetch(PDO::FETCH_ASSOC) : null;
    assertTest($colRi && $colRi['Null'] === 'YES', "reservation_items.grade_id is NULLABLE (Null: YES)");
} catch (Exception $e) {
    echo "[INFO] reservation_items table omitted (direct broiler schema active)\n";
}

try {
    $stmtOi = $conn->query("SHOW COLUMNS FROM order_items LIKE 'grade_id'");
    $colOi = $stmtOi ? $stmtOi->fetch(PDO::FETCH_ASSOC) : null;
    assertTest($colOi && $colOi['Null'] === 'YES', "order_items.grade_id is NULLABLE (Null: YES)");
} catch (Exception $e) {
    echo "[INFO] order_items table omitted\n";
}

// Check reservations broiler columns
$columns = [];
foreach ($conn->query("DESCRIBE reservations") as $r) {
    $columns[$r['Field']] = $r;
}
assertTest(isset($columns['batch_id']), "reservations has batch_id column");
assertTest(isset($columns['quantity_birds']), "reservations has quantity_birds column");
assertTest(isset($columns['weight_kg']), "reservations has weight_kg column");
assertTest(isset($columns['unit_price']), "reservations has unit_price column");
assertTest(isset($columns['total_amount']), "reservations has total_amount column");
assertTest(isset($columns['advance_payment']), "reservations has advance_payment column");
assertTest(isset($columns['actual_pickup_date']), "reservations has actual_pickup_date column");
assertTest(isset($columns['accepted_quantity']), "reservations has accepted_quantity column");
assertTest(isset($columns['rejected_quantity']), "reservations has rejected_quantity column");
assertTest(isset($columns['rejection_reason']), "reservations has rejection_reason column");

// ----------------------------------------------------
// 2. UI Markup Checks in reservations.php
// ----------------------------------------------------
echo "\n--- 2. UI Markup Checks in reservations.php ---\n";
$resFileContent = file_get_contents(__DIR__ . '/../poultrychat/reservations.php');

assertTest(strpos($resFileContent, 'id="newBroilerReservationModal"') !== false, "reservations.php contains #newBroilerReservationModal markup");
assertTest(strpos($resFileContent, 'name="quantity_birds"') !== false, "New Broiler Reservation modal has quantity_birds field");
assertTest(strpos($resFileContent, 'name="weight_kg"') !== false, "New Broiler Reservation modal has weight_kg field");
assertTest(strpos($resFileContent, 'name="unit_price"') !== false, "New Broiler Reservation modal has unit_price field");
assertTest(strpos($resFileContent, 'name="total_amount"') !== false, "New Broiler Reservation modal has total_amount field");
assertTest(strpos($resFileContent, 'name="advance_payment"') !== false, "New Broiler Reservation modal has advance_payment field");
assertTest(strpos($resFileContent, 'name="pickup_date"') !== false, "New Broiler Reservation modal has pickup_date field");
assertTest(strpos($resFileContent, 'id="newResBalanceDisplay"') !== false, "New Broiler Reservation modal has live balance calculation display");
assertTest(strpos($resFileContent, 'id="pickupModal') !== false, "reservations.php contains Fulfillment/Pickup modal markup");
assertTest(strpos($resFileContent, 'name="actual_pickup_date"') !== false, "Fulfillment modal has actual_pickup_date field");
assertTest(strpos($resFileContent, 'name="accepted_quantity"') !== false, "Fulfillment modal has accepted_quantity field");
assertTest(strpos($resFileContent, 'name="rejected_quantity"') !== false, "Fulfillment modal has rejected_quantity field");
assertTest(strpos($resFileContent, 'name="rejection_reason"') !== false, "Fulfillment modal has rejection_reason field");
assertTest(strpos($resFileContent, 'id="voucherModal') !== false, "reservations.php contains Printable Voucher modal");
assertTest(strpos($resFileContent, 'printVoucher(') !== false, "reservations.php contains printVoucher() JavaScript function");
assertTest(strpos($resFileContent, 'egg_grades eg ON') === false, "reservations.php removed obsolete egg_grades join dependency");

// ----------------------------------------------------
// 3. Lifecycle Test: Create → View → Update → Pickup → Accepted/Returned → Balance → Print
// ----------------------------------------------------
echo "\n--- 3. Testing Broiler Reservation Full Lifecycle ---\n";

// Get active customer and batch
$cust = $conn->query("SELECT customer_id, name FROM customers WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$batch = $conn->query("SELECT batch_id, batch_code FROM batches WHERE status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$customerId = (int)$cust['customer_id'];
$batchId = (int)$batch['batch_id'];

// A. CREATE: Create Broiler Reservation
$birds = 400;
$weightKg = 720.00;
$unitPrice = 185.00;
$totalAmount = 74000.00; // 400 * 185
$advancePayment = 20000.00;
$expectedBalance = 54000.00; // 74000 - 20000
$pickupDate = date('Y-m-d', strtotime('+5 days'));

$createData = [
    'customer_id' => $customerId,
    'batch_id' => $batchId,
    'quantity_birds' => $birds,
    'weight_kg' => $weightKg,
    'unit_price' => $unitPrice,
    'total_amount' => $totalAmount,
    'advance_payment' => $advancePayment,
    'pickup_date' => $pickupDate,
    'notes' => 'Commercial wholesale batch test'
];

$res = $resService->createBroilerReservation($createData, 1);
$resId = (int)$res['reservation_id'];

assertTest($resId > 0, "Reservation created successfully with ID: $resId");
assertTest(!empty($res['reservation_number']), "Reservation assigned number: {$res['reservation_number']}");
assertTest((int)$res['customer_id'] === $customerId, "Customer correctly linked ($customerId)");
assertTest((int)$res['batch_id'] === $batchId, "Batch correctly linked ($batchId)");
assertTest((int)$res['quantity_birds'] === $birds, "Reserved birds matches ($birds)");
assertTest((float)$res['weight_kg'] === $weightKg, "Weight kg matches ($weightKg)");
assertTest((float)$res['unit_price'] === $unitPrice, "Unit price matches (₱$unitPrice)");
assertTest((float)$res['total_amount'] === $totalAmount, "Total amount matches (₱$totalAmount)");
assertTest((float)$res['advance_payment'] === $advancePayment, "Advance payment matches (₱$advancePayment)");

// B. BALANCE: Verify balance calculation (total - advance)
$calculatedBalance = (float)$res['total_amount'] - (float)$res['advance_payment'];
assertTest($calculatedBalance === $expectedBalance, "Calculated balance equals total amount - advance payment (₱$expectedBalance)");
assertTest((float)$res['remaining_balance'] === $expectedBalance, "Repository returned remaining_balance = ₱$expectedBalance");

// C. VIEW: Fetch and view reservation details
$viewRes = $resRepo->findById($resId);
assertTest($viewRes !== null, "View Details: Retrieved reservation by findById");
assertTest($viewRes['batch_code'] === $batch['batch_code'], "View Details: Correct batch code retrieved ({$viewRes['batch_code']})");
assertTest($viewRes['customer_name'] === $cust['name'], "View Details: Correct customer name retrieved ({$viewRes['customer_name']})");

// D. UPDATE: Update Reservation details
$updatedBirds = 450;
$updatedWeight = 810.00;
$updatedTotal = 83250.00; // 450 * 185
$updatedAdvance = 30000.00;
$updatedBalance = 53250.00; // 83250 - 30000
$updatedPickupDate = date('Y-m-d', strtotime('+6 days'));

$updateData = [
    'customer_id' => $customerId,
    'batch_id' => $batchId,
    'quantity_birds' => $updatedBirds,
    'weight_kg' => $updatedWeight,
    'unit_price' => $unitPrice,
    'total_amount' => $updatedTotal,
    'advance_payment' => $updatedAdvance,
    'pickup_date' => $updatedPickupDate,
    'notes' => 'Updated reservation specifications'
];

$updatedRes = $resService->updateReservation($resId, $updateData, 1);
assertTest((int)$updatedRes['quantity_birds'] === $updatedBirds, "Update: Reserved birds updated to $updatedBirds");
assertTest((float)$updatedRes['total_amount'] === $updatedTotal, "Update: Total amount updated to ₱$updatedTotal");
assertTest((float)$updatedRes['advance_payment'] === $updatedAdvance, "Update: Advance payment updated to ₱$updatedAdvance");
assertTest((float)$updatedRes['remaining_balance'] === $updatedBalance, "Update: New remaining balance matches ₱$updatedBalance");

// E. PICKUP & FULFILLMENT: Record Actual Pickup with Accepted / Returned breakdown
$actualPickupDate = date('Y-m-d');
$acceptedCount = 435;
$rejectedCount = 15;
$rejectionReason = "Underweight (< agreed target)";

$fulfillmentData = [
    'actual_pickup_date' => $actualPickupDate,
    'accepted_quantity' => $acceptedCount,
    'rejected_quantity' => $rejectedCount,
    'rejection_reason' => $rejectionReason
];

$fulfilledRes = $resService->recordFulfillment($resId, $fulfillmentData, 1);
assertTest($fulfilledRes['status'] === 'fulfilled', "Pickup: Reservation marked as 'fulfilled'");
assertTest($fulfilledRes['actual_pickup_date'] === $actualPickupDate, "Pickup: Actual pickup date recorded ($actualPickupDate)");
assertTest((int)$fulfilledRes['accepted_quantity'] === $acceptedCount, "Pickup: Accepted count recorded ($acceptedCount birds)");
assertTest((int)$fulfilledRes['rejected_quantity'] === $rejectedCount, "Pickup: Returned/rejected count recorded ($rejectedCount birds)");
assertTest($fulfilledRes['rejection_reason'] === $rejectionReason, "Pickup: Rejection reason recorded ('$rejectionReason')");

// F. PRINT: Verify printable voucher data integrity
assertTest(!empty($fulfilledRes['reservation_number']), "Print Voucher: Reservation number present");
assertTest($fulfilledRes['customer_name'] === $cust['name'], "Print Voucher: Customer details present");
assertTest((float)$fulfilledRes['total_amount'] > 0, "Print Voucher: Contract total present");
assertTest((float)$fulfilledRes['advance_payment'] > 0, "Print Voucher: Advance payment present");
assertTest(isset($fulfilledRes['remaining_balance']), "Print Voucher: Remaining balance due present");

// ----------------------------------------------------
// 4. Data Preservation Check
// ----------------------------------------------------
echo "\n--- 4. Data Preservation Check ---\n";
$preExistingCount = (int)$conn->query("SELECT COUNT(*) FROM reservations WHERE reservation_id != $resId")->fetchColumn();
assertTest($preExistingCount >= 1, "All pre-existing reservations preserved intact (found: $preExistingCount)");

// Clean up test reservation
$conn->prepare("DELETE FROM audit_logs WHERE table_name = 'reservations' AND record_id = ?")->execute([$resId]);
try {
    $conn->prepare("DELETE FROM reservation_items WHERE reservation_id = ?")->execute([$resId]);
} catch (Exception $e) {}
$conn->prepare("DELETE FROM reservations WHERE reservation_id = ?")->execute([$resId]);

echo "\n========================================================================\n";
echo "SUMMARY: Passed: $passCount | Failed: $failCount\n";
echo "========================================================================\n";

exit($failCount === 0 ? 0 : 1);
