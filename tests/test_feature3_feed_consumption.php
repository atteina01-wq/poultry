<?php
/**
 * Test Suite: Feature 3 - Feed Consumption Management
 */

require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../poultrychat/helpers.php';
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Repositories/FeedConsumptionRepository.php';
require_once __DIR__ . '/../src/Services/FeedService.php';

echo "========================================================================\n";
echo "FEATURE 3: FEED CONSUMPTION MANAGEMENT VERIFICATION SUITE\n";
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

// ----------------------------------------------------
// 1. Database Schema Check
// ----------------------------------------------------
echo "--- 1. Schema Check: feed_stage Column ---\n";
$stmtCol = $conn->query("SHOW COLUMNS FROM feed_consumption LIKE 'feed_stage'");
$col = $stmtCol->fetch(PDO::FETCH_ASSOC);

assertTest($col !== false, "feed_stage column exists in feed_consumption");
assertTest(strpos(strtolower($col['Type']), "enum('pre-starter','starter','grower','finisher')") !== false, "feed_stage type is ENUM('Pre-Starter','Starter','Grower','Finisher')");
assertTest(strtoupper($col['Null']) === 'YES', "feed_stage allows NULL");

// ----------------------------------------------------
// 2. Setup Broiler Batch & Feed Inventory
// ----------------------------------------------------
echo "\n--- 2. Batch & Inventory Availability ---\n";
$feedRepo = new \Poultry\Repositories\FeedConsumptionRepository();
$feedService = new \Poultry\Services\FeedService();

// Find an active broiler batch
$stmtB = $conn->query("SELECT batch_id, batch_code FROM batches WHERE status='active' AND (flock_type='broiler' OR flock_type IS NULL) LIMIT 1");
$batch = $stmtB->fetch(PDO::FETCH_ASSOC);
assertTest(!empty($batch), "Active broiler batch found (ID: {$batch['batch_id']}, Code: {$batch['batch_code']})");
$batchId = (int)$batch['batch_id'];

// Find or replenish a feed inventory item
$stmtInv = $conn->query("SELECT inventory_id, item_name, quantity, unit FROM inventory WHERE category = 'feed' AND quantity > 20 LIMIT 1");
$feedItem = $stmtInv->fetch(PDO::FETCH_ASSOC);
if (!$feedItem) {
    // Top up inventory_id 1
    $conn->exec("UPDATE inventory SET quantity = 200.00 WHERE inventory_id = 1");
    $stmtInv = $conn->query("SELECT inventory_id, item_name, quantity, unit FROM inventory WHERE inventory_id = 1");
    $feedItem = $stmtInv->fetch(PDO::FETCH_ASSOC);
}
assertTest(!empty($feedItem) && (float)$feedItem['quantity'] > 20, "Feed item available: {$feedItem['item_name']} (Stock: {$feedItem['quantity']} {$feedItem['unit']})");
$invId = (int)$feedItem['inventory_id'];

// ----------------------------------------------------
// 3. Test Each Feed Stage
// ----------------------------------------------------
echo "\n--- 3. Record Feed Consumption Across Each Feed Stage ---\n";
$stages = ['Pre-Starter', 'Starter', 'Grower', 'Finisher'];
$createdIds = [];

foreach ($stages as $stage) {
    $qty = 1.25;
    $date = date('Y-m-d', strtotime('-' . rand(1, 10) . ' days'));
    
    $res = $feedService->recordConsumption([
        'batch_id' => $batchId,
        'inventory_id' => $invId,
        'feed_stage' => $stage,
        'quantity_used' => $qty,
        'date' => $date,
        'remarks' => "Stage test: {$stage}"
    ], 1);

    $id = $res['consumption_id'] ?? 0;
    assertTest($id > 0, "Recorded feed consumption for stage '{$stage}' (ID: {$id})");
    $createdIds[] = $id;

    // Verify row in database
    $stmtRow = $conn->prepare("SELECT * FROM feed_consumption WHERE consumption_id = ?");
    $stmtRow->execute([$id]);
    $row = $stmtRow->fetch(PDO::FETCH_ASSOC);
    assertTest($row && $row['feed_stage'] === $stage, "Database row has feed_stage = '{$stage}'");
    assertTest($row && $row['date'] === $date, "Selectable date preserved accurately: {$date}");
    assertTest($row && (float)$row['quantity_used'] === $qty, "Quantity recorded matches: {$qty}");
}

// ----------------------------------------------------
// 4. Test Selectable Date via record_feed_consumption helper
// ----------------------------------------------------
echo "\n--- 4. Selectable Consumption Date via Helper ---\n";
$customDate = '2026-09-18';
$helperQty = 2.50;
$helperSuccess = record_feed_consumption($conn, $batchId, $invId, $helperQty, $customDate, 'Grower');
assertTest($helperSuccess === true, "record_feed_consumption succeeded with custom date {$customDate}");

$stmtHelperRow = $conn->prepare("SELECT * FROM feed_consumption WHERE batch_id = ? AND date = ? AND feed_stage = 'Grower' ORDER BY consumption_id DESC LIMIT 1");
$stmtHelperRow->execute([$batchId, $customDate]);
$helperRow = $stmtHelperRow->fetch(PDO::FETCH_ASSOC);
assertTest($helperRow && $helperRow['date'] === $customDate, "Helper consumption date stored as {$customDate}");
assertTest($helperRow && $helperRow['feed_stage'] === 'Grower', "Helper saved feed_stage as Grower");
if ($helperRow) {
    $createdIds[] = (int)$helperRow['consumption_id'];
}

// ----------------------------------------------------
// 5. Verify Inventory Deduction
// ----------------------------------------------------
echo "\n--- 5. Verify Feed Inventory Deduction ---\n";
$initialStockStmt = $conn->prepare("SELECT quantity FROM inventory WHERE inventory_id = ?");
$initialStockStmt->execute([$invId]);
$stockBefore = (float)$initialStockStmt->fetchColumn();

$deductQty = 3.75;
$deductDate = date('Y-m-d');
$deductSuccess = record_feed_consumption($conn, $batchId, $invId, $deductQty, $deductDate, 'Finisher');
assertTest($deductSuccess === true, "Consumed {$deductQty} bags successfully");

$finalStockStmt = $conn->prepare("SELECT quantity FROM inventory WHERE inventory_id = ?");
$finalStockStmt->execute([$invId]);
$stockAfter = (float)$finalStockStmt->fetchColumn();

$expectedStock = round($stockBefore - $deductQty, 2);
assertTest(round($stockAfter, 2) === $expectedStock, "Inventory stock accurately deducted: before={$stockBefore}, after={$stockAfter}, diff={$deductQty}");

$stmtLastId = $conn->query("SELECT MAX(consumption_id) FROM feed_consumption");
$createdIds[] = (int)$stmtLastId->fetchColumn();

// ----------------------------------------------------
// 6. Try Excessive Consumption & Verify Rejection
// ----------------------------------------------------
echo "\n--- 6. Excessive Feed Consumption Protection ---\n";
$currentStock = (float)$conn->query("SELECT quantity FROM inventory WHERE inventory_id = {$invId}")->fetchColumn();
$excessiveQty = $currentStock + 50000.00;

// Test A: via Helper
$excessHelperFailed = false;
$helperResult = record_feed_consumption($conn, $batchId, $invId, $excessiveQty, date('Y-m-d'), 'Starter');
if (!$helperResult) {
    $excessHelperFailed = true;
}
assertTest($excessHelperFailed, "record_feed_consumption rejected excessive consumption ({$excessiveQty} vs {$currentStock} available)");

$stockAfterExcessHelper = (float)$conn->query("SELECT quantity FROM inventory WHERE inventory_id = {$invId}")->fetchColumn();
assertTest($stockAfterExcessHelper === $currentStock, "Inventory stock unchanged after rejected helper call");

// Test B: via FeedService
$serviceThrew = false;
try {
    $feedService->recordConsumption([
        'batch_id' => $batchId,
        'inventory_id' => $invId,
        'feed_stage' => 'Starter',
        'quantity_used' => $excessiveQty,
        'date' => date('Y-m-d')
    ], 1);
} catch (Exception $e) {
    $serviceThrew = true;
}
assertTest($serviceThrew, "FeedService threw exception on excessive consumption");

$stockAfterExcessService = (float)$conn->query("SELECT quantity FROM inventory WHERE inventory_id = {$invId}")->fetchColumn();
assertTest($stockAfterExcessService === $currentStock, "Inventory stock preserved and protected from negative balance");

// ----------------------------------------------------
// 7. Cleanup Created Test Records & Restore Stock
// ----------------------------------------------------
echo "\n--- 7. Cleanup & Stock Restoration ---\n";
if (!empty($createdIds)) {
    $inClause = implode(',', array_map('intval', $createdIds));
    $conn->exec("DELETE FROM feed_consumption WHERE consumption_id IN ({$inClause})");
    $conn->exec("DELETE FROM audit_logs WHERE table_name = 'feed_consumption' AND record_id IN ({$inClause})");
    // Restore deducted stock for test items
    $totalDeducted = (count($stages) * 1.25) + $helperQty + $deductQty;
    $conn->exec("UPDATE inventory SET quantity = quantity + {$totalDeducted} WHERE inventory_id = {$invId}");
}
echo "Test consumption records cleaned up and stock balanced.\n";

echo "\n------------------------------------------------------------------------\n";
echo "FEATURE 3 TEST RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "------------------------------------------------------------------------\n";

if ($failCount > 0) {
    exit(1);
}
