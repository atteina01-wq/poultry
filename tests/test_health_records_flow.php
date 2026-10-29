<?php
/**
 * Test: Health Records Complete Flow & Dashboard Feed Integration
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/poultrychat/healthrecords.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['full_name'] = 'Farm Admin';
$_SESSION['role'] = 'admin';
$_SESSION['csrf_token'] = 'health-test-token';

require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../poultrychat/helpers.php';
require_once __DIR__ . '/../src/Services/DashboardService.php';
require_once __DIR__ . '/../src/Repositories/DashboardRepository.php';

echo "========================================================================\n";
echo "HEALTH RECORDS END-TO-END FLOW VERIFICATION SUITE\n";
echo "========================================================================\n\n";

$results = [];

// 1. Get an active batch
$batch = $conn->query("SELECT batch_id, batch_code FROM batches WHERE status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$batch) {
    die("No active batches found in database for testing.");
}

$batchId = (int)$batch['batch_id'];
$batchCode = $batch['batch_code'];
$testMedicine = 'Test Vitamin B-Complex ' . uniqid();
$testQty = 5;
$testDate = date('Y-m-d');
$testNotes = 'Routine flock supplementation test';

// ----------------------------------------------------
// TEST 1: Direct helper call record_health_activity
// ----------------------------------------------------
$helperSuccess = record_health_activity($conn, $batchId, 'Treatment', $testMedicine, $testQty, $testDate, $testNotes);

if ($helperSuccess) {
    // Check health_records table
    $stmtHr = $conn->prepare("SELECT * FROM health_records WHERE medicine_name = ? ORDER BY health_id DESC LIMIT 1");
    $stmtHr->execute([$testMedicine]);
    $hrRow = $stmtHr->fetch(PDO::FETCH_ASSOC);

    if ($hrRow && (int)$hrRow['quantity_used'] == $testQty && (int)$hrRow['batch_id'] === $batchId) {
        $results[] = "[PASS] TEST 1: record_health_activity successfully committed to health_records (health_id #{$hrRow['health_id']})";
    } else {
        $results[] = "[FAIL] TEST 1: Row missing or mismatched in health_records table";
    }

    // Check audit_logs table
    $stmtAudit = $conn->prepare("SELECT * FROM audit_logs WHERE table_name = 'health_records' AND record_id = ? LIMIT 1");
    $stmtAudit->execute([$hrRow['health_id'] ?? 0]);
    $auditRow = $stmtAudit->fetch(PDO::FETCH_ASSOC);

    if ($auditRow) {
        $results[] = "[PASS] TEST 2: Audit log record written to audit_logs for health_id #{$hrRow['health_id']}";
    } else {
        $results[] = "[FAIL] TEST 2: Audit log entry not found in audit_logs";
    }
} else {
    $results[] = "[FAIL] TEST 1: record_health_activity returned false";
}

// ----------------------------------------------------
// TEST 3: Dashboard Live Operations Feed Integration
// ----------------------------------------------------
$dashService = new \Poultry\Services\DashboardService();
$activities = $dashService->getRecentActivities(20);

$foundInFeed = false;
foreach ($activities as $act) {
    if ($act['event_type'] === 'health' && $act['ref_code'] === $batchCode && strpos($act['party_name'], $testMedicine) !== false) {
        $foundInFeed = true;
        break;
    }
}

if ($foundInFeed) {
    $results[] = "[PASS] TEST 3: Health record immediately visible in Dashboard Live Operations Feed";
} else {
    $results[] = "[FAIL] TEST 3: Health record did not appear in Dashboard Live Operations Feed";
}

// ----------------------------------------------------
// TEST 4: healthrecords.php Render Simulation on POST
// ----------------------------------------------------
$pageMedicine = 'Vaccine Gumboro ' . uniqid();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['csrf_token'] = 'health-test-token';
$_POST['save'] = '1';
$_POST['batch_id'] = $batchId;
$_POST['type'] = 'Vaccination';
$_POST['medicine'] = $pageMedicine;
$_POST['qty'] = '3';
$_POST['date'] = date('Y-m-d');
$_POST['remarks'] = 'Immunity test booster';

ob_start();
include __DIR__ . '/../poultrychat/healthrecords.php';
$htmlOutput = ob_get_clean();

$hasSuccessMsg = strpos($htmlOutput, 'Health &amp; medication record saved successfully!') !== false || strpos($htmlOutput, 'Health & medication record saved successfully!') !== false;
$hasTableEntry = strpos($htmlOutput, $pageMedicine) !== false;
$hasBatchBadge = strpos($htmlOutput, $batchCode) !== false;

if ($hasSuccessMsg && $hasTableEntry) {
    $results[] = "[PASS] TEST 4: healthrecords.php POST immediately renders success message AND new row in Health & Treatment Logs table without manual reload";
} else {
    $results[] = "[FAIL] TEST 4: healthrecords.php POST did not display row in table (Success: " . ($hasSuccessMsg ? 'YES' : 'NO') . ", TableEntry: " . ($hasTableEntry ? 'YES' : 'NO') . ")";
}

// Clean up scratch check script
if (file_exists(__DIR__ . '/scratch_check_schema.php')) {
    unlink(__DIR__ . '/scratch_check_schema.php');
}

// Print results
$allPassed = true;
foreach ($results as $r) {
    echo $r . "\n";
    if (strpos($r, '[FAIL]') !== false) {
        $allPassed = false;
    }
}

echo "\n------------------------------------------------------------------------\n";
if ($allPassed) {
    echo "SUMMARY: ALL " . count($results) . " HEALTH RECORD TESTS PASSED (100%)\n";
} else {
    echo "SUMMARY: SOME TESTS FAILED\n";
}
echo "------------------------------------------------------------------------\n";

