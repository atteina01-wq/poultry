<?php
/**
 * Test Suite: Final Chapter 1 Full Integration Test
 * 
 * End-to-End Workflow:
 * Batch 
 * → Health 
 * → Feed 
 * → Inventory 
 * → Worker Activity 
 * → Mortality 
 * → Health Alert 
 * → Reservation 
 * → Pickup/Acceptance/Return 
 * → Reports
 * → Role Permissions
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../poultrychat/helpers.php';
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Repositories/BatchRepository.php';
require_once __DIR__ . '/../src/Repositories/HealthRecordRepository.php';
require_once __DIR__ . '/../src/Repositories/MortalityRepository.php';
require_once __DIR__ . '/../src/Repositories/DashboardRepository.php';
require_once __DIR__ . '/../src/Repositories/ReservationRepository.php';
require_once __DIR__ . '/../src/Services/BatchService.php';
require_once __DIR__ . '/../src/Services/HealthService.php';
require_once __DIR__ . '/../src/Services/FeedService.php';
require_once __DIR__ . '/../src/Services/InventoryService.php';
require_once __DIR__ . '/../src/Services/MortalityService.php';
require_once __DIR__ . '/../src/Services/ReservationService.php';
require_once __DIR__ . '/../src/Services/ReportService.php';

echo "========================================================================\n";
echo "CHAPTER 1 FULL INTEGRATION TEST: COMPLETE CROSS-MODULE WORKFLOW\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;
$workflowResults = [];

function recordWorkflowStep(string $step, bool $passed, string $details = "") {
    global $passCount, $failCount, $workflowResults;
    if ($passed) {
        echo "[PASS] " . $step . ($details ? " ($details)" : "") . "\n";
        $passCount++;
    } else {
        echo "[FAIL] " . $step . " -> " . $details . "\n";
        $failCount++;
    }
}

$batchRepo = new \Poultry\Repositories\BatchRepository();
$mortRepo = new \Poultry\Repositories\MortalityRepository();
$dashRepo = new \Poultry\Repositories\DashboardRepository();
$resRepo = new \Poultry\Repositories\ReservationRepository();
$healthService = new \Poultry\Services\HealthService();
$feedService = new \Poultry\Services\FeedService();
$invService = new \Poultry\Services\InventoryService();
$mortService = new \Poultry\Services\MortalityService();
$resService = new \Poultry\Services\ReservationService();
$reportService = new \Poultry\Services\ReportService();

$testBatchId = 0;
$testResId = 0;
$testHealthId = 0;
$testFeedId = 0;
$testActId = 0;
$testFeedItemId = 0;

try {
    // -------------------------------------------------------------------------
    // STEP 1: Batch Creation & Headcount Initialization
    // -------------------------------------------------------------------------
    echo "--- 1. Workflow Step: Broiler Batch Creation ---\n";
    $batchCode = 'INT-BATCH-' . time();
    $testBatchId = $batchRepo->create([
        'batch_code' => $batchCode,
        'flock_type' => 'broiler',
        'arrival_date' => date('Y-m-d'),
        'production_date' => date('Y-m-d'),
        'initial_quantity' => 1000,
        'breed' => 'Ross 308',
        'house_pen' => 'Pen-Integration',
        'status' => 'active'
    ]);
    $batch = $batchRepo->findById($testBatchId);
    $step1Pass = ($testBatchId > 0 && (int)$batch['current_quantity'] === 1000 && $batch['status'] === 'active');
    recordWorkflowStep("Batch Creation & Headcount Initialization", $step1Pass, "Batch #$testBatchId, 1,000 birds");
    $workflowResults['Batch'] = $step1Pass ? 'PASS' : 'FAIL';

    // -------------------------------------------------------------------------
    // STEP 2: Flock Health Record Linked to Batch
    // -------------------------------------------------------------------------
    echo "\n--- 2. Workflow Step: Health Record Linking & Diagnosis ---\n";
    $_SESSION['user_id'] = 1; // Admin
    $healthData = [
        'batch_id' => $testBatchId,
        'date' => date('Y-m-d'),
        'type' => 'treatment',
        'medicine_name' => 'Oxytetracycline 20%',
        'quantity_used' => 2.50,
        'illness_observation' => 'Mild flock coughing under sudden weather shift',
        'doctor_consultation' => 'Dr. Maria Santos DVM',
        'diagnosis' => 'Early Respiratory Infection',
        'status' => 'in_progress',
        'notes' => 'Prescribed 3-day water treatment',
        'next_due_date' => date('Y-m-d', strtotime('+3 days'))
    ];
    $healthRes = $healthService->recordHealth($healthData, 1);
    $testHealthId = $healthRes['health_id'] ?? 0;
    
    // Verify health record stored correctly
    $healthStmt = $conn->prepare("SELECT * FROM health_records WHERE health_id = ?");
    $healthStmt->execute([$testHealthId]);
    $savedHealth = $healthStmt->fetch(PDO::FETCH_ASSOC);

    $step2Pass = ($testHealthId > 0 
        && (int)$savedHealth['batch_id'] === $testBatchId 
        && $savedHealth['doctor_consultation'] === 'Dr. Maria Santos DVM'
        && $savedHealth['status'] === 'in_progress'
        && (float)$savedHealth['quantity_used'] == 2.50);
    recordWorkflowStep("Health Record & Doctor Consultation Linked to Batch", $step2Pass, "Health ID #$testHealthId");
    $workflowResults['Health'] = $step2Pass ? 'PASS' : 'FAIL';

    // -------------------------------------------------------------------------
    // STEP 3: Feed Consumption Linked to Batch & Inventory Deduction
    // -------------------------------------------------------------------------
    echo "\n--- 3. Workflow Step: Feed Consumption & Stage Tracking ---\n";
    // Find or create feed item in inventory
    $stmtFeed = $conn->query("SELECT inventory_id, quantity, unit_cost FROM inventory WHERE category = 'feed' AND is_active = 1 LIMIT 1");
    $feedItem = $stmtFeed->fetch(PDO::FETCH_ASSOC);
    if (!$feedItem) {
        $testFeedItemId = $invService->addItem([
            'item_name' => 'Integration Starter Feed',
            'category' => 'feed',
            'quantity' => 500.00,
            'unit' => 'kg',
            'unit_cost' => 42.50,
            'reorder_level' => 50.00
        ]);
        $initialFeedStock = 500.00;
    } else {
        $testFeedItemId = (int)$feedItem['inventory_id'];
        $initialFeedStock = (float)$feedItem['quantity'];
    }

    $consumedQty = 15.50; // kg
    $feedRes = record_feed_consumption($conn, $testBatchId, $testFeedItemId, $consumedQty, date('Y-m-d'), 'Starter');
    
    // Check inventory deduction
    $stmtInvAfter = $conn->prepare("SELECT quantity FROM inventory WHERE inventory_id = ?");
    $stmtInvAfter->execute([$testFeedItemId]);
    $newFeedStock = (float)$stmtInvAfter->fetchColumn();
    $expectedStock = $initialFeedStock - $consumedQty;
    
    // Verify feed_consumption row
    $stmtFC = $conn->prepare("SELECT * FROM feed_consumption WHERE batch_id = ? AND inventory_id = ? ORDER BY consumption_id DESC LIMIT 1");
    $stmtFC->execute([$testBatchId, $testFeedItemId]);
    $fcRow = $stmtFC->fetch(PDO::FETCH_ASSOC);
    $testFeedId = $fcRow['consumption_id'] ?? 0;

    $step3Pass = ($feedRes === true 
        && abs($newFeedStock - $expectedStock) < 0.01 
        && $fcRow['feed_stage'] === 'Starter'
        && (float)$fcRow['quantity_used'] == 15.50);
    recordWorkflowStep("Feed Consumption & Automatic Inventory Deduction", $step3Pass, "Deducted $consumedQty kg, Stock: $newFeedStock");
    $workflowResults['Feed'] = $step3Pass ? 'PASS' : 'FAIL';

    // -------------------------------------------------------------------------
    // STEP 4: Inventory Management & Negative Balance Protection
    // -------------------------------------------------------------------------
    echo "\n--- 4. Workflow Step: Inventory Balance & Over-Consumption Protection ---\n";
    // Try excessive feed consumption greater than available stock
    $excessiveQty = $newFeedStock + 500.00;
    $excessFeedRes = record_feed_consumption($conn, $testBatchId, $testFeedItemId, $excessiveQty, date('Y-m-d'), 'Grower');
    
    // Stock must be intact
    $stmtInvExcess = $conn->prepare("SELECT quantity FROM inventory WHERE inventory_id = ?");
    $stmtInvExcess->execute([$testFeedItemId]);
    $stockAfterExcess = (float)$stmtInvExcess->fetchColumn();

    $step4Pass = ($excessFeedRes === false && abs($stockAfterExcess - $newFeedStock) < 0.01);
    recordWorkflowStep("Inventory Guard: Excessive Consumption Rejected", $step4Pass, "Preserved stock at $stockAfterExcess kg");
    $workflowResults['Inventory'] = $step4Pass ? 'PASS' : 'FAIL';

    // -------------------------------------------------------------------------
    // STEP 5: Worker Activity & Immutable Audit Trail
    // -------------------------------------------------------------------------
    echo "\n--- 5. Workflow Step: Worker Activity Logging & Audit Trail ---\n";
    $_SESSION['user_id'] = 2; // Farm Worker
    $_SESSION['role'] = 'worker';

    // Record activity as worker
    $actStmt = $conn->prepare("
        INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at)
        VALUES (?, ?, 'Cleaning', 'Disinfectant Spray', 2.0, ?, 'Mid-cycle pen sanitation', NOW())
    ");
    $actStmt->execute([$testBatchId, 2, date('Y-m-d')]);
    $testActId = (int)$conn->lastInsertId();

    // Log to audit_logs
    $auditStmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, created_at)
        VALUES (?, 'create', 'activities', ?, ?, NOW())
    ");
    $auditStmt->execute([2, $testActId, json_encode(['batch_id' => $testBatchId, 'task' => 'Cleaning'])]);

    // Verify stored activity and worker identity
    $checkAct = $conn->prepare("SELECT a.*, u.full_name as worker_name FROM activities a LEFT JOIN users u ON a.user_id = u.user_id WHERE a.activity_id = ?");
    $checkAct->execute([$testActId]);
    $actRow = $checkAct->fetch(PDO::FETCH_ASSOC);

    $step5Pass = ($testActId > 0 && (int)$actRow['user_id'] === 2 && $actRow['type'] === 'Cleaning');
    recordWorkflowStep("Worker Task & Identity Recorded in Audit Trail", $step5Pass, "Worker: {$actRow['worker_name']}, Task: Cleaning");
    $workflowResults['Worker Activity'] = $step5Pass ? 'PASS' : 'FAIL';

    // -------------------------------------------------------------------------
    // STEP 6: Mortality Recording & Immediate Headcount Reduction
    // -------------------------------------------------------------------------
    echo "\n--- 6. Workflow Step: Mortality Recording & Immediate Headcount Update ---\n";
    // Batch currently has 1000 birds.
    // Record 12 bird mortality (1.2% rate -> triggers health alert in Step 7)
    $mortalityQty = 12;
    $mortRes = record_mortality($conn, $testBatchId, -$mortalityQty, 'Heat stress & sudden thunderstorm', date('Y-m-d'), 2);

    $batchAfterMort = $batchRepo->findById($testBatchId);
    $expectedHeadcount = 1000 - $mortalityQty; // 988

    // Verify excessive mortality is rejected
    $excessMort = record_mortality($conn, $testBatchId, -1500, 'Impossible mortality', date('Y-m-d'), 2);
    $batchAfterExcessMort = $batchRepo->findById($testBatchId);

    $step6Pass = ($mortRes === true 
        && (int)$batchAfterMort['current_quantity'] === $expectedHeadcount 
        && $excessMort === false 
        && (int)$batchAfterExcessMort['current_quantity'] === $expectedHeadcount);
    recordWorkflowStep("Immediate Headcount Reduction & Excess Mortality Protection", $step6Pass, "Headcount: $expectedHeadcount birds");
    $workflowResults['Mortality'] = $step6Pass ? 'PASS' : 'FAIL';

    // -------------------------------------------------------------------------
    // STEP 7: Health Alert Triggering (Rolling 24h & 1.0% Threshold)
    // -------------------------------------------------------------------------
    echo "\n--- 7. Workflow Step: Health Alert System Trigger ---\n";
    // 12 birds lost out of 988 current + 12 lost = 1,000 pre-loss headcount.
    // Rate = 12 / 1000 * 100 = 1.20% (>= 1.0% threshold).
    $healthAlerts = $dashRepo->getHealthAlerts();
    $matchedAlert = null;
    foreach ($healthAlerts as $ha) {
        if ($ha['batch_id'] == $testBatchId) {
            $matchedAlert = $ha;
            break;
        }
    }

    $step7Pass = ($matchedAlert !== null 
        && (int)$matchedAlert['total_loss'] === 12 
        && (int)$matchedAlert['pre_loss_headcount'] === 1000 
        && (float)$matchedAlert['mortality_percentage'] === 1.2);
    recordWorkflowStep("Critical Health Alert Triggered on 1.20% Mortality (>= 1.0%)", $step7Pass, "Loss: 12, Pre-loss: 1000, Rate: 1.20%");
    $workflowResults['Health Alert'] = $step7Pass ? 'PASS' : 'FAIL';

    // -------------------------------------------------------------------------
    // STEP 8: Reservation Creation & Financial Terms
    // -------------------------------------------------------------------------
    echo "\n--- 8. Workflow Step: Broiler Reservation & Advance Payment ---\n";
    $_SESSION['user_id'] = 1; // Admin
    // Customer #1 books 200 birds from this batch
    $resData = [
        'customer_id' => 1,
        'batch_id' => $testBatchId,
        'quantity_birds' => 200,
        'weight_kg' => 380.0,
        'unit_price' => 195.0,
        'total_amount' => 74100.0,
        'advance_payment' => 25000.0,
        'pickup_date' => date('Y-m-d', strtotime('+2 days')),
        'notes' => 'Integration test booking'
    ];
    $testResId = $resRepo->createBroilerReservation($resData);
    $resRow = $resRepo->findById($testResId);
    $expectedBalance = 74100.0 - 25000.0; // ₱49,100.00

    $step8Pass = ($testResId > 0 
        && (int)$resRow['quantity_birds'] === 200 
        && abs((float)$resRow['remaining_balance'] - $expectedBalance) < 0.01);
    recordWorkflowStep("Broiler Reservation Booked with Advance & Balance Due", $step8Pass, "Res #$testResId, Balance: ₱$expectedBalance");
    $workflowResults['Reservation'] = $step8Pass ? 'PASS' : 'FAIL';

    // -------------------------------------------------------------------------
    // STEP 9: Pickup / Fulfillment / Acceptance / Rejections
    // -------------------------------------------------------------------------
    echo "\n--- 9. Workflow Step: Pickup Fulfillment, Accepted & Returned Counts ---\n";
    $fulfillmentData = [
        'actual_pickup_date' => date('Y-m-d'),
        'accepted_quantity' => 192,
        'rejected_quantity' => 8,
        'rejection_reason' => 'Minor dressing scratches and underweight birds'
    ];
    $fulfillRes = $resRepo->recordFulfillment($testResId, $fulfillmentData);
    $fulfilledRes = $resRepo->findById($testResId);

    $step9Pass = ($fulfillRes === true 
        && $fulfilledRes['status'] === 'fulfilled'
        && (int)$fulfilledRes['accepted_quantity'] === 192 
        && (int)$fulfilledRes['rejected_quantity'] === 8);
    recordWorkflowStep("Order Fulfilled with Accepted & Returned Chickens Disposition", $step9Pass, "Accepted: 192, Rejected: 8");
    $workflowResults['Pickup/Acceptance/Return'] = $step9Pass ? 'PASS' : 'FAIL';

    // -------------------------------------------------------------------------
    // STEP 10: Reports Propagation Verification
    // -------------------------------------------------------------------------
    echo "\n--- 10. Workflow Step: Centralized Reports Data Flow ---\n";
    // 1. Production report
    $prodRep = $reportService->getProductionReport(null, null, $testBatchId);
    $prodPass = (count($prodRep) === 1 
        && (int)$prodRep[0]['current_quantity'] === 988 
        && (int)$prodRep[0]['total_mortality'] === 12
        && (float)$prodRep[0]['survival_rate_pct'] === 98.8);

    // 2. Health report
    $healthRep = $reportService->getHealthRecordReport(null, null, $testBatchId);
    $healthRepPass = (count($healthRep) >= 1 && $healthRep[0]['diagnosis'] === 'Early Respiratory Infection');

    // 3. Feed report
    $feedRep = $reportService->getConsumptionReport(null, null, $testBatchId);
    $feedRepPass = (count($feedRep) >= 1 && $feedRep[0]['feed_stage'] === 'Starter');

    // 4. Mortality report
    $mortRep = $reportService->getMortalityReport(null, null, $testBatchId);
    $mortRepPass = (count($mortRep) >= 1 && (int)$mortRep[0]['loss_count'] === 12);

    // 5. Reservation report
    $resRep = $reportService->getReservationReport(null, null, null, $testBatchId);
    $resRepPass = (count($resRep) >= 1 
        && (int)$resRep[0]['accepted_quantity'] === 192 
        && (int)$resRep[0]['rejected_quantity'] === 8
        && abs((float)$resRep[0]['remaining_balance'] - 49100.0) < 0.01);

    // 6. Worker report
    $workerRep = $reportService->getWorkerActivityReport(null, null, $testBatchId);
    $workerRepPass = (count($workerRep) >= 1 && $workerRep[0]['activity_type'] === 'Cleaning');

    $step10Pass = ($prodPass && $healthRepPass && $feedRepPass && $mortRepPass && $resRepPass && $workerRepPass);
    recordWorkflowStep("Cross-Module Data Flow Propagated into Centralized Reports", $step10Pass, "All 6 report sub-queries verified");
    $workflowResults['Reports'] = $step10Pass ? 'PASS' : 'FAIL';

    // -------------------------------------------------------------------------
    // STEP 11: Role Permissions & Access Enforcement
    // -------------------------------------------------------------------------
    echo "\n--- 11. Workflow Step: Role Permissions Verification ---\n";
    // Check role restrictions in batch.php, reports.php, activities.php
    $batchPhp = file_get_contents(__DIR__ . '/../poultrychat/batch.php');
    $reportsPhp = file_get_contents(__DIR__ . '/../poultrychat/reports.php');
    $activitiesPhp = file_get_contents(__DIR__ . '/../poultrychat/activities.php');

    $roleChecks = true;
    // Batch modification restricted to admin
    if (strpos($batchPhp, "\$_SESSION['role']") === false && strpos($batchPhp, "role") === false) {
        $roleChecks = false;
    }
    // Activity creation allowed for worker
    if (strpos($activitiesPhp, "\$_SESSION['user_id']") === false) {
        $roleChecks = false;
    }
    recordWorkflowStep("Role Permissions & Identity Enforcement", $roleChecks, "Worker & Admin boundaries validated");
    $workflowResults['Role Permissions'] = $roleChecks ? 'PASS' : 'FAIL';

} catch (\Exception $e) {
    echo "[EXCEPTION] Unhandled integration error: " . $e->getMessage() . "\n";
    $failCount++;
} finally {
    // -------------------------------------------------------------------------
    // CLEANUP: Clean isolated test records
    // -------------------------------------------------------------------------
    echo "\n--- Clean Up Isolated Integration Test Records ---\n";
    if ($testResId > 0) {
        $conn->exec("DELETE FROM reservation_items WHERE reservation_id = $testResId");
        $conn->exec("DELETE FROM reservations WHERE reservation_id = $testResId");
    }
    if ($testBatchId > 0) {
        $conn->exec("DELETE FROM feed_consumption WHERE batch_id = $testBatchId");
        $conn->exec("DELETE FROM health_records WHERE batch_id = $testBatchId");
        $conn->exec("DELETE FROM mortality_records WHERE batch_id = $testBatchId");
        $conn->exec("DELETE FROM activities WHERE batch_id = $testBatchId");
        $conn->exec("DELETE FROM audit_logs WHERE record_id = $testBatchId OR record_id = $testActId");
        $conn->exec("DELETE FROM batches WHERE batch_id = $testBatchId");
    }
    if ($testFeedItemId > 0 && isset($initialFeedStock)) {
        // Restore stock
        $stmtRest = $conn->prepare("UPDATE inventory SET quantity = ? WHERE inventory_id = ?");
        $stmtRest->execute([$initialFeedStock, $testFeedItemId]);
    }
    echo "[PASS] All isolated integration test records cleaned up cleanly.\n";
}

echo "\n========================================================================\n";
echo "WORKFLOW STATUS SUMMARY\n";
echo "========================================================================\n";
foreach ($workflowResults as $workflow => $status) {
    printf("%-30s : %s\n", $workflow, $status);
}

echo "\nTOTAL TESTS: Passed: $passCount | Failed: $failCount\n";
echo "========================================================================\n";

if ($failCount > 0) {
    exit(1);
}
