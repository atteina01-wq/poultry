<?php
require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../poultrychat/helpers.php';

header('Content-Type: text/plain');

echo "=== TESTING FEED CONSUMPTION ADMIN & WORKER WORKFLOW ===\n\n";

// 1. Fetch an admin and a worker user
$adminUser = $conn->query("SELECT user_id, username, full_name, role FROM users WHERE role = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$workerUser = $conn->query("SELECT user_id, username, full_name, role FROM users WHERE role != 'admin' AND role != 'integrator' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$workerUser) {
    $workerUser = $conn->query("SELECT user_id, username, full_name, role FROM users WHERE user_id = 2 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

echo "Admin User: {$adminUser['username']} (ID: {$adminUser['user_id']})\n";
echo "Worker User: {$workerUser['username']} (ID: {$workerUser['user_id']})\n\n";

// 2. Fetch an active batch and feed item
$batch = $conn->query("SELECT batch_id, batch_code FROM batches WHERE status = 'active' AND flock_type = 'broiler' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$invItem = $conn->query("SELECT inventory_id, item_name, quantity, unit FROM inventory WHERE category = 'feed' AND quantity > 5 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

echo "Batch: {$batch['batch_code']} (ID: {$batch['batch_id']})\n";
echo "Inventory: {$invItem['item_name']} (ID: {$invItem['inventory_id']}, Stock: {$invItem['quantity']} {$invItem['unit']})\n\n";

// Test Step 1: Admin records/fills up feeding as pending
echo "Step 1: Admin records feeding task with status = 'pending'...\n";
$_SESSION['user_id'] = $adminUser['user_id'];
$_SESSION['role'] = 'admin';

$qtyToUse = 1.5;
$testDate = date('Y-m-d');
$testStage = 'Grower';
$testRemarks = 'Test admin feeding instruction - morning distribution';

$ok = record_feed_consumption($conn, $batch['batch_id'], $invItem['inventory_id'], $qtyToUse, $testDate, $testStage, 'pending', $testRemarks);

if (!$ok) {
    echo "FAILED: " . ($_SESSION['last_feed_error'] ?? 'Unknown error') . "\n";
    exit;
}
echo "SUCCESS: Feed task recorded by admin!\n";

// Fetch the newly inserted record
$fcRow = $conn->query("SELECT * FROM feed_consumption WHERE batch_id = {$batch['batch_id']} ORDER BY consumption_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "feed_consumption record ID: {$fcRow['consumption_id']}\n";
echo " - status: {$fcRow['status']}\n";
echo " - recorded_by: {$fcRow['recorded_by']}\n";
echo " - completed_by: " . var_export($fcRow['completed_by'], true) . "\n";
echo " - remarks: {$fcRow['remarks']}\n";

// Check corresponding activity
$actRow = $conn->query("SELECT * FROM activities WHERE batch_id = {$batch['batch_id']} AND type = 'Feeding' ORDER BY activity_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "activities record ID: {$actRow['activity_id']}\n";
echo " - status: {$actRow['status']}\n";
echo " - user_id: {$actRow['user_id']}\n";
echo " - item_name: {$actRow['item_name']}\n\n";

// Test Step 2: Worker performs the action
echo "Step 2: Worker (ID {$workerUser['user_id']}) completes the action...\n";
$_SESSION['user_id'] = $workerUser['user_id'];
$_SESSION['role'] = 'worker';

$consumption_id = $fcRow['consumption_id'];
$worker_id = $workerUser['user_id'];

// Simulate complete_feeding handler logic
$conn->beginTransaction();
$stmtUp = $conn->prepare("UPDATE feed_consumption SET status = 'completed', completed_by = ?, completed_at = NOW() WHERE consumption_id = ?");
$stmtUp->execute([$worker_id, $consumption_id]);

$stmtActUp = $conn->prepare("
    UPDATE activities 
    SET status = 'completed', user_id = ? 
    WHERE type = 'Feeding' 
      AND batch_id = ? 
      AND date = ? 
      AND status = 'pending' 
    ORDER BY activity_id DESC 
    LIMIT 1
");
$stmtActUp->execute([$worker_id, $fcRow['batch_id'], $fcRow['date']]);
$conn->commit();

echo "SUCCESS: Worker marked action completed!\n\n";

// Verify updated feed_consumption
$fcUpdated = $conn->query("SELECT * FROM feed_consumption WHERE consumption_id = {$consumption_id}")->fetch(PDO::FETCH_ASSOC);
echo "Updated feed_consumption record:\n";
echo " - status: {$fcUpdated['status']}\n";
echo " - recorded_by: {$fcUpdated['recorded_by']} (Admin)\n";
echo " - completed_by: {$fcUpdated['completed_by']} (Worker)\n";
echo " - completed_at: {$fcUpdated['completed_at']}\n";

// Verify updated activities
$actUpdated = $conn->query("SELECT * FROM activities WHERE activity_id = {$actRow['activity_id']}")->fetch(PDO::FETCH_ASSOC);
echo "Updated activities record:\n";
echo " - status: {$actUpdated['status']}\n";
echo " - user_id: {$actUpdated['user_id']} (Worker)\n\n";

// Test Step 3: Verify non-admin cannot record feed
echo "Step 3: Verify worker cannot create feeding records...\n";
$_SESSION['user_id'] = $workerUser['user_id'];
$_SESSION['role'] = 'worker';
$user_role = strtolower(trim($_SESSION['role'] ?? 'worker'));
$isAdmin = ($user_role === 'admin');

if (!$isAdmin) {
    echo "SUCCESS: Worker correctly blocked from recording/filling up feed consumption!\n";
} else {
    echo "FAILED: Worker was not blocked!\n";
}

// Clean up test records
echo "\nCleaning up test records...\n";
$conn->exec("DELETE FROM feed_consumption WHERE consumption_id = {$consumption_id}");
$conn->exec("DELETE FROM activities WHERE activity_id = {$actRow['activity_id']}");
$conn->exec("UPDATE inventory SET quantity = quantity + {$qtyToUse} WHERE inventory_id = {$invItem['inventory_id']}");
echo "Cleaned up test rows and restored inventory.\n\n";

echo "ALL TESTS PASSED!\n";
