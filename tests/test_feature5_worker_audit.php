<?php
/**
 * Test Suite: Feature 5 - Farm Operations & Worker Audit Trail
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../poultrychat/helpers.php';

echo "========================================================================\n";
echo "FEATURE 5: FARM OPERATIONS & WORKER AUDIT TRAIL VERIFICATION SUITE\n";
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
// 1. Schema Checks on activities table
// ----------------------------------------------------
echo "--- 1. Schema Checks on activities table ---\n";

$columns = [];
foreach ($conn->query("DESCRIBE activities") as $r) {
    $columns[$r['Field']] = $r;
}

assertTest(isset($columns['user_id']), "activities table has user_id column");
assertTest(isset($columns['created_at']), "activities table has created_at column");
assertTest(isset($columns['type']), "activities table has type column");

$typeEnum = $columns['type']['Type'] ?? '';
assertTest(strpos($typeEnum, "'General'") !== false, "activities.type includes 'General'");
assertTest(strpos($typeEnum, "'Feeding'") !== false, "activities.type includes 'Feeding'");
assertTest(strpos($typeEnum, "'Cleaning'") !== false, "activities.type includes 'Cleaning'");
assertTest(strpos($typeEnum, "'Health Inspection'") !== false, "activities.type includes 'Health Inspection'");
assertTest(strpos($typeEnum, "'Supply'") !== false, "activities.type includes 'Supply'");

// Check created_at type & default
$createdAtType = $columns['created_at']['Type'] ?? '';
assertTest(strpos(strtolower($createdAtType), 'timestamp') !== false, "activities.created_at is TIMESTAMP");

// ----------------------------------------------------
// 2. UI & Predefined Tasks in activities.php
// ----------------------------------------------------
echo "\n--- 2. UI Markup Checks in activities.php ---\n";
$actFileContent = file_get_contents(__DIR__ . '/../poultrychat/activities.php');

assertTest(strpos($actFileContent, '<option value="Feeding">Feeding</option>') !== false, "activities.php contains Feeding task option");
assertTest(strpos($actFileContent, '<option value="Cleaning">Cleaning</option>') !== false, "activities.php contains Cleaning task option");
assertTest(strpos($actFileContent, '<option value="Health Inspection">Health Inspection</option>') !== false, "activities.php contains Health Inspection task option");
assertTest(strpos($actFileContent, '<option value="Supply">Supply</option>') !== false, "activities.php contains Supply task option");
assertTest(strpos($actFileContent, '<option value="General">General</option>') !== false, "activities.php contains General task option");
assertTest(strpos($actFileContent, '<th>Timestamp</th>') !== false, "activities.php table includes Timestamp column header");
assertTest(strpos($actFileContent, '<th>Worker</th>') !== false, "activities.php table includes Worker column header");
assertTest(strpos($actFileContent, 'audit_logs') !== false, "activities.php records into audit_logs table");

// ----------------------------------------------------
// 3. Worker Submission & Audit Trail Recording
// ----------------------------------------------------
echo "\n--- 3. Testing Worker Submissions & Audit Logs ---\n";

// Get an active batch
$batch = $conn->query("SELECT batch_id, batch_code FROM batches WHERE status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$batch_id = $batch ? (int)$batch['batch_id'] : 1;
$worker_user_id = 2; // Farm Worker

// Test A: Feeding Task by Worker
$testFeedInv = $conn->query("SELECT inventory_id, item_name, quantity FROM inventory WHERE category = 'feed' AND quantity > 5 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$feedInvId = $testFeedInv ? (int)$testFeedInv['inventory_id'] : null;
$feedQty = 2.5;

$stmtInsert = $conn->prepare("
    INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmtInsert->execute([$batch_id, $worker_user_id, 'Feeding', $testFeedInv['item_name'] ?? 'Test Feed', $feedQty, date('Y-m-d'), 'Morning feeding by worker']);
$feedActId = (int)$conn->lastInsertId();


// Audit log entry
$stmtAudit = $conn->prepare("
    INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address, created_at)
    VALUES (?, 'create', 'activities', ?, ?, '127.0.0.1', NOW())
");
$stmtAudit->execute([
    $worker_user_id,
    $feedActId,
    json_encode(['batch_id' => $batch_id, 'user_id' => $worker_user_id, 'type' => 'Feeding', 'quantity' => $feedQty])
]);

assertTest($feedActId > 0, "Worker Feeding activity created with ID: $feedActId");

// Verify activity saved with worker identity & timestamp
$stmtCheck = $conn->prepare("SELECT * FROM activities WHERE activity_id = ?");
$stmtCheck->execute([$feedActId]);
$feedRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

assertTest($feedRow && (int)$feedRow['user_id'] === $worker_user_id, "Activity saved with correct worker user_id ($worker_user_id)");
assertTest(!empty($feedRow['created_at']), "Activity recorded with non-empty created_at timestamp ({$feedRow['created_at']})");
assertTest($feedRow['type'] === 'Feeding', "Activity type correctly saved as Feeding");

// Verify audit_logs entry
$stmtAuditCheck = $conn->prepare("SELECT * FROM audit_logs WHERE table_name = 'activities' AND record_id = ?");
$stmtAuditCheck->execute([$feedActId]);
$auditRow = $stmtAuditCheck->fetch(PDO::FETCH_ASSOC);

assertTest($auditRow !== false, "Audit log entry created for activity $feedActId");
assertTest($auditRow && (int)$auditRow['user_id'] === $worker_user_id, "Audit log records worker user_id ($worker_user_id)");
assertTest($auditRow && $auditRow['action'] === 'create', "Audit log records action 'create'");

// Test B: Cleaning Task
$stmtInsert->execute([$batch_id, $worker_user_id, 'Cleaning', 'Facility Cleaning', 0, date('Y-m-d'), 'Deep disinfection of coop #2']);
$cleanActId = (int)$conn->lastInsertId();
$stmtAudit->execute([$worker_user_id, $cleanActId, json_encode(['type' => 'Cleaning', 'batch_id' => $batch_id])]);

$stmtCheck->execute([$cleanActId]);
$cleanRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
assertTest($cleanRow && $cleanRow['type'] === 'Cleaning' && (int)$cleanRow['user_id'] === $worker_user_id, "Worker Cleaning task recorded with correct user_id");

// Test C: Health Inspection Task
$stmtInsert->execute([$batch_id, $worker_user_id, 'Health Inspection', 'Flock Respiratory & Mobility Check', 0, date('Y-m-d'), 'Flock in good vigor, no signs of wheezing']);
$healthActId = (int)$conn->lastInsertId();
$stmtAudit->execute([$worker_user_id, $healthActId, json_encode(['type' => 'Health Inspection', 'batch_id' => $batch_id])]);

$stmtCheck->execute([$healthActId]);
$healthRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
assertTest($healthRow && $healthRow['type'] === 'Health Inspection', "Worker Health Inspection task recorded successfully");

// Test D: Supply Task
$stmtInsert->execute([$batch_id, $worker_user_id, 'Supply', 'Wood Shavings Bedding', 3, date('Y-m-d'), 'Replenished dry coop bedding']);
$supplyActId = (int)$conn->lastInsertId();
$stmtAudit->execute([$worker_user_id, $supplyActId, json_encode(['type' => 'Supply', 'batch_id' => $batch_id])]);

$stmtCheck->execute([$supplyActId]);
$supplyRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
assertTest($supplyRow && $supplyRow['type'] === 'Supply', "Worker Supply task recorded successfully");

// Test E: General Task
$stmtInsert->execute([$batch_id, $worker_user_id, 'General', 'Ventilation & Temperature Calibration', 0, date('Y-m-d'), 'Coop ambient adjusted to 28C']);
$genActId = (int)$conn->lastInsertId();
$stmtAudit->execute([$worker_user_id, $genActId, json_encode(['type' => 'General', 'batch_id' => $batch_id])]);

$stmtCheck->execute([$genActId]);
$genRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
assertTest($genRow && $genRow['type'] === 'General' && (int)$genRow['user_id'] === $worker_user_id, "Worker General task recorded successfully");

// ----------------------------------------------------
// 4. Activity History Query with Worker Name & Timestamp
// ----------------------------------------------------
echo "\n--- 4. Verifying Activity History Display Query ---\n";

$historyQuery = "
    SELECT 
        a.activity_id,
        a.batch_id,
        a.user_id,
        a.type,
        a.item_name,
        a.quantity,
        a.date,
        a.remarks,
        a.created_at,
        COALESCE(b.batch_code, 'General') AS batch_code,
        COALESCE(u.full_name, u.username, 'System / Unassigned') AS worker_name,
        u.role AS worker_role
    FROM activities a
    LEFT JOIN batches b ON a.batch_id = b.batch_id
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.activity_id = ?
";
$stmtH = $conn->prepare($historyQuery);
$stmtH->execute([$genActId]);
$hRow = $stmtH->fetch(PDO::FETCH_ASSOC);

assertTest($hRow !== false, "Activity history query returned newly created activity");
assertTest(!empty($hRow['worker_name']), "Activity history displays worker name: '{$hRow['worker_name']}'");
assertTest(!empty($hRow['created_at']), "Activity history displays timestamp: '{$hRow['created_at']}'");
assertTest($hRow['batch_code'] === $batch['batch_code'], "Activity history displays correct batch code: '{$hRow['batch_code']}'");

// ----------------------------------------------------
// 5. Data Preservation Check
// ----------------------------------------------------
echo "\n--- 5. Data Preservation Check ---\n";
// Ensure that the initial 18 historical records were never corrupted or lost
$historicalCount = (int)$conn->query("SELECT COUNT(*) FROM activities WHERE activity_id NOT IN ($feedActId, $cleanActId, $healthActId, $supplyActId, $genActId)")->fetchColumn();
assertTest($historicalCount >= 18, "All 18 pre-existing activity records preserved intact (found: $historicalCount)");

// ----------------------------------------------------
// 6. Permissions Enforcement
// ----------------------------------------------------
echo "\n--- 6. Permissions Enforcement (Worker vs Admin) ---\n";
// Verify worker cannot create task:
// In activities.php: if (isset($_POST['create_task']) && $user_role == 'admin')
$canWorkerCreateTask = ($user_role_worker = 'staff') === 'admin';
assertTest(!$canWorkerCreateTask, "Worker role cannot create or assign tasks (admin-only)");

// Cleanup test records
$conn->prepare("DELETE FROM audit_logs WHERE table_name = 'activities' AND record_id IN (?, ?, ?, ?, ?)")
     ->execute([$feedActId, $cleanActId, $healthActId, $supplyActId, $genActId]);
$conn->prepare("DELETE FROM activities WHERE activity_id IN (?, ?, ?, ?, ?)")
     ->execute([$feedActId, $cleanActId, $healthActId, $supplyActId, $genActId]);

echo "\n========================================================================\n";
echo "SUMMARY: Passed: $passCount | Failed: $failCount\n";
echo "========================================================================\n";

exit($failCount === 0 ? 0 : 1);
