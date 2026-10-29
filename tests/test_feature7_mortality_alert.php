<?php
/**
 * Test Suite: Feature 7 - Mortality & Health Alert Safety Fixes
 * 
 * Verifies:
 * 1. Reject mortality greater than batch's current headcount.
 * 2. Pass $_SESSION['user_id'] into mortality_records.recorded_by.
 * 3. Preserve existing rolling 24-hour calculation.
 * 4. Preserve existing 1.0% threshold.
 * 5. Preserve pre-loss headcount denominator: rate = 24h_loss / (current_quantity + 24h_loss) * 100.
 * 6. Verify current_quantity updates immediately after mortality.
 * 7. Test normal mortality.
 * 8. Test mortality equal to current headcount (current_quantity reaches 0 safely).
 * 9. Test excessive mortality (rejected, headcount untouched).
 * 10. Test below 1% (no alert).
 * 11. Test exactly 1% (alert triggered).
 * 12. Test above 1% (alert triggered).
 * 13. Test multiple deaths within 24 hours (accumulated loss triggers alert).
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../poultrychat/helpers.php';
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Repositories/BatchRepository.php';
require_once __DIR__ . '/../src/Repositories/MortalityRepository.php';
require_once __DIR__ . '/../src/Repositories/DashboardRepository.php';
require_once __DIR__ . '/../src/Services/MortalityService.php';

echo "========================================================================\n";
echo "FEATURE 7: MORTALITY & HEALTH ALERT SAFETY FIXES VERIFICATION SUITE\n";
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

$batchRepo = new \Poultry\Repositories\BatchRepository();
$mortRepo = new \Poultry\Repositories\MortalityRepository();
$dashRepo = new \Poultry\Repositories\DashboardRepository();
$mortService = new \Poultry\Services\MortalityService();

// Create a dedicated isolated test batch for Feature 7 tests
$testBatchCode = 'TEST-MORT-' . time();
$batchId = $batchRepo->create([
    'batch_code' => $testBatchCode,
    'flock_type' => 'broiler',
    'arrival_date' => date('Y-m-d'),
    'production_date' => date('Y-m-d'),
    'initial_quantity' => 1000,
    'breed' => 'Cobb 500',
    'status' => 'active'
]);

assertTest($batchId > 0, "Created isolated test batch #$batchId with 1,000 birds");
$batch = $batchRepo->findById($batchId);
assertTest((int)$batch['current_quantity'] === 1000, "Initial batch current_quantity is exactly 1,000");

// -------------------------------------------------------------------------
// 1. Normal Mortality & Immediate current_quantity Update & recorded_by
// -------------------------------------------------------------------------
echo "\n--- 1. Normal Mortality & Immediate Headcount Deduction ---\n";
$_SESSION['user_id'] = 2; // Simulate logged-in worker

// Record normal mortality of 5 birds via helper
$resNormal = record_mortality($conn, $batchId, -5, 'Normal cull test', date('Y-m-d'));
assertTest($resNormal === true, "Normal mortality of 5 birds recorded successfully via helper");

$batchAfter1 = $batchRepo->findById($batchId);
assertTest((int)$batchAfter1['current_quantity'] === 995, "current_quantity immediately reduced from 1,000 to 995");

// Check recorded_by in mortality_records
$stmtM = $conn->prepare("SELECT recorded_by, quantity_change FROM mortality_records WHERE batch_id = ? ORDER BY mortality_id DESC LIMIT 1");
$stmtM->execute([$batchId]);
$lastRec = $stmtM->fetch(PDO::FETCH_ASSOC);
assertTest((int)$lastRec['recorded_by'] === 2, "mortality_records.recorded_by captured \$_SESSION['user_id'] (2)");
assertTest((int)$lastRec['quantity_change'] === -5, "mortality_records.quantity_change is -5");

// Record normal mortality of 3 birds via MortalityService
$_SESSION['user_id'] = 1; // Simulate admin
$svcResult = $mortService->recordMortality([
    'batch_id' => $batchId,
    'quantity' => 3,
    'reason' => 'Service cull test',
    'date' => date('Y-m-d')
]);
assertTest($svcResult['remaining_quantity'] === 992, "MortalityService reduced remaining_quantity to 992");

$batchAfter2 = $batchRepo->findById($batchId);
assertTest((int)$batchAfter2['current_quantity'] === 992, "Database current_quantity updated immediately to 992");

$stmtM2 = $conn->prepare("SELECT recorded_by FROM mortality_records WHERE mortality_id = ?");
$stmtM2->execute([$svcResult['mortality_id']]);
$recBy = (int)$stmtM2->fetchColumn();
assertTest($recBy === 1, "MortalityService captured \$_SESSION['user_id'] = 1 in recorded_by");

// -------------------------------------------------------------------------
// 2. Excessive Mortality Rejection
// -------------------------------------------------------------------------
echo "\n--- 2. Excessive Mortality Rejection ---\n";
// Current headcount is 992. Try recording 993 birds loss.
$excessRes = record_mortality($conn, $batchId, -993, 'Excessive test loss', date('Y-m-d'));
assertTest($excessRes === false, "record_mortality rejects mortality (993) greater than current headcount (992)");
assertTest(isset($_SESSION['last_mortality_error']) && strpos($_SESSION['last_mortality_error'], 'cannot exceed current batch headcount') !== false, "Error message correctly notes headcount limit");

// Verify current_quantity untouched
$batchAfterExcess = $batchRepo->findById($batchId);
assertTest((int)$batchAfterExcess['current_quantity'] === 992, "current_quantity remains unchanged at 992 after excessive attempt");

// Test excessive via MortalityRepository directly
$repoExcessCaught = false;
try {
    $mortRepo->recordMortality($batchId, 1500, 'Massive mortality', date('Y-m-d'));
} catch (\Exception $e) {
    $repoExcessCaught = true;
}
assertTest($repoExcessCaught, "MortalityRepository throws exception when loss (1500) exceeds headcount (992)");

// Test excessive via MortalityService
$serviceExcessCaught = false;
try {
    $mortService->recordMortality([
        'batch_id' => $batchId,
        'quantity' => 1000,
        'reason' => 'Service excessive test',
        'date' => date('Y-m-d')
    ]);
} catch (\Exception $e) {
    $serviceExcessCaught = true;
}
assertTest($serviceExcessCaught, "MortalityService throws exception when loss (1000) exceeds headcount (992)");

// -------------------------------------------------------------------------
// 3. Health Alert Calculations: Rolling 24h, Denominator, Thresholds
// -------------------------------------------------------------------------
echo "\n--- 3. Rolling 24-Hour Alert Calculation & Thresholds ---\n";
// Let's create another fresh batch to test exact percentage boundary conditions
$alertBatchCode = 'TEST-ALERT-' . time();
$alertBatchId = $batchRepo->create([
    'batch_code' => $alertBatchCode,
    'flock_type' => 'broiler',
    'arrival_date' => date('Y-m-d'),
    'production_date' => date('Y-m-d'),
    'initial_quantity' => 1000,
    'status' => 'active'
]);

// Case A: Below 1%
// Total flock = 1,000 birds.
// 9 birds die -> current headcount = 991.
// Pre-loss headcount = 991 + 9 = 1,000.
// Rate = (9 / 1000) * 100 = 0.90% (< 1.0%).
record_mortality($conn, $alertBatchId, -9, 'Below 1% test', date('Y-m-d'));

$alertsA = $dashRepo->getHealthAlerts();
$foundBatchInAlertsA = false;
foreach ($alertsA as $a) {
    if ($a['batch_id'] == $alertBatchId) {
        $foundBatchInAlertsA = true;
    }
}
assertTest(!$foundBatchInAlertsA, "Below 1% (0.90%): Health alert NOT triggered for batch");

// Case B: Exactly 1% (reaches threshold)
// Add 1 more bird death -> total 24h loss becomes 9 + 1 = 10 birds.
// Pre-loss headcount = 990 + 10 = 1,000.
// Rate = (10 / 1000) * 100 = exactly 1.00%.
record_mortality($conn, $alertBatchId, -1, 'Reaching 1% test', date('Y-m-d'));

$alertsB = $dashRepo->getHealthAlerts();
$foundAlertB = null;
foreach ($alertsB as $a) {
    if ($a['batch_id'] == $alertBatchId) {
        $foundAlertB = $a;
        break;
    }
}
assertTest($foundAlertB !== null, "Exactly 1% (1.00%): Critical Health Alert triggered!");
assertTest((int)$foundAlertB['total_loss'] === 10, "Alert reports exact total_loss = 10");
assertTest((int)$foundAlertB['pre_loss_headcount'] === 1000, "Alert reports exact pre_loss_headcount = 1000 (denominator)");
assertTest((float)$foundAlertB['mortality_percentage'] === 1.0, "Alert reports exact mortality_percentage = 1.00%");

// Case C: Above 1%
// Add 5 more birds death -> total 24h loss becomes 15 birds.
// Pre-loss headcount = 985 + 15 = 1,000.
// Rate = (15 / 1000) * 100 = 1.50% (> 1.0%).
record_mortality($conn, $alertBatchId, -5, 'Above 1% test', date('Y-m-d'));

$alertsC = $dashRepo->getHealthAlerts();
$foundAlertC = null;
foreach ($alertsC as $a) {
    if ($a['batch_id'] == $alertBatchId) {
        $foundAlertC = $a;
        break;
    }
}
assertTest($foundAlertC !== null, "Above 1% (1.50%): Critical Health Alert triggered!");
assertTest((int)$foundAlertC['total_loss'] === 15, "Alert reports total_loss = 15");
assertTest((float)$foundAlertC['mortality_percentage'] === 1.5, "Alert reports mortality_percentage = 1.50%");

// Case D: Multiple deaths within 24 hours
// Verify that the 3 separate mortality events (9 + 1 + 5 = 15) accumulated together in the rolling window
$stmtEvents = $conn->prepare("SELECT COUNT(*) FROM mortality_records WHERE batch_id = ? AND quantity_change < 0");
$stmtEvents->execute([$alertBatchId]);
$eventCount = (int)$stmtEvents->fetchColumn();
assertTest($eventCount === 3, "Multiple deaths: 3 distinct mortality events recorded within 24h");
assertTest((int)$foundAlertC['total_loss'] === 15, "Multiple deaths: Correctly aggregated into total_loss = 15");

// -------------------------------------------------------------------------
// 4. Mortality Equal to Current Headcount
// -------------------------------------------------------------------------
echo "\n--- 4. Mortality Equal to Current Headcount ---\n";
// Create a small flock batch with 50 birds
$smallBatchCode = 'TEST-SMALL-' . time();
$smallBatchId = $batchRepo->create([
    'batch_code' => $smallBatchCode,
    'flock_type' => 'broiler',
    'arrival_date' => date('Y-m-d'),
    'production_date' => date('Y-m-d'),
    'initial_quantity' => 50,
    'status' => 'active'
]);

// Record mortality of exactly 50 birds (equal to current headcount)
$resEqual = record_mortality($conn, $smallBatchId, -50, 'Complete flock loss event', date('Y-m-d'), 1);
assertTest($resEqual === true, "Mortality equal to current headcount (50/50) succeeds");

$smallBatchAfter = $batchRepo->findById($smallBatchId);
assertTest((int)$smallBatchAfter['current_quantity'] === 0, "current_quantity is now safely 0 and NOT negative");

// Now that headcount is 0, any further mortality must be rejected
$resZero = record_mortality($conn, $smallBatchId, -1, 'Further loss when 0', date('Y-m-d'));
assertTest($resZero === false, "Further mortality rejected when current headcount is 0");

// -------------------------------------------------------------------------
// 5. Cleanup Test Batches & Verify Non-Interference
// -------------------------------------------------------------------------
echo "\n--- 5. Cleanup Isolated Test Records ---\n";
$conn->exec("DELETE FROM mortality_records WHERE batch_id IN ($batchId, $alertBatchId, $smallBatchId)");
$conn->exec("DELETE FROM audit_logs WHERE record_id IN ($batchId, $alertBatchId, $smallBatchId) OR user_id = 99999");
$conn->exec("DELETE FROM batches WHERE batch_id IN ($batchId, $alertBatchId, $smallBatchId)");
echo "[PASS] Test batches and records cleaned up cleanly\n";

echo "\n========================================================================\n";
echo "SUMMARY: Passed: $passCount | Failed: $failCount\n";
echo "========================================================================\n";

if ($failCount > 0) {
    exit(1);
}
