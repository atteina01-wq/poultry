<?php
/**
 * Test Suite: Feature 2 - Flock Health Records Management
 */

require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../poultrychat/helpers.php';
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Repositories/HealthRecordRepository.php';
require_once __DIR__ . '/../src/Services/HealthService.php';

echo "========================================================================\n";
echo "FEATURE 2: FLOCK HEALTH RECORDS MANAGEMENT VERIFICATION SUITE\n";
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
// 1. Schema & Column Checks
// ----------------------------------------------------
echo "--- 1. Database Schema & Columns Verification ---\n";
$cols = [];
foreach ($conn->query("DESCRIBE health_records") as $r) {
    $cols[$r['Field']] = $r;
}

assertTest(isset($cols['illness_observation']) && strpos(strtolower($cols['illness_observation']['Type']), 'varchar(255)') !== false, "illness_observation VARCHAR(255) exists");
assertTest(isset($cols['doctor_consultation']) && strpos(strtolower($cols['doctor_consultation']['Type']), 'varchar(150)') !== false, "doctor_consultation VARCHAR(150) exists");
assertTest(isset($cols['diagnosis']) && strpos(strtolower($cols['diagnosis']['Type']), 'varchar(255)') !== false, "diagnosis VARCHAR(255) exists");
assertTest(isset($cols['status']) && strpos(strtolower($cols['status']['Type']), 'enum') !== false, "status ENUM exists");
assertTest(isset($cols['quantity_used']) && strpos(strtolower($cols['quantity_used']['Type']), 'decimal(10,2)') !== false, "quantity_used is DECIMAL(10,2)");

// ----------------------------------------------------
// 2. Broiler Batch Linkage Enforcement
// ----------------------------------------------------
echo "\n--- 2. Broiler Batch Linkage Enforcement ---\n";
$healthRepo = new \Poultry\Repositories\HealthRecordRepository();
$healthService = new \Poultry\Services\HealthService();

// Get active broiler batch
$broilerBatch = $conn->query("SELECT batch_id, batch_code FROM batches WHERE status='active' AND (flock_type='broiler' OR flock_type IS NULL) LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$broilerBatchId = (int)$broilerBatch['batch_id'];

// Attempt invalid batch ID (999999)
$caughtNonExistent = false;
try {
    $healthService->recordHealth([
        'batch_id' => 999999,
        'medicine_name' => 'Should Fail',
        'quantity_used' => 1.0
    ]);
} catch (Exception $e) {
    $caughtNonExistent = true;
}
assertTest($caughtNonExistent, "Rejects health record for non-existent batch");

// ----------------------------------------------------
// 3. Add Health Record & Doctor Consultation
// ----------------------------------------------------
echo "\n--- 3. Add Health Record with Doctor Consultation ---\n";
$docName = "Dr. Maria Santos DVM";
$illness = "Flock sneezing and mild depression";
$diag = "Early Stage Broiler Bronchitis";
$medName = "Vet-Tylosin Oral " . uniqid();

$result = $healthService->recordHealth([
    'batch_id' => $broilerBatchId,
    'type' => 'treatment',
    'medicine_name' => $medName,
    'quantity_used' => 2.50,
    'date' => date('Y-m-d'),
    'illness_observation' => $illness,
    'doctor_consultation' => $docName,
    'diagnosis' => $diag,
    'status' => 'scheduled',
    'notes' => 'Administer via drinking water for 3 consecutive days.'
], 1);

$testHealthId = $result['health_id'] ?? 0;
assertTest($testHealthId > 0, "Health record added with generated ID #{$testHealthId}");

$fetched = $healthRepo->findById($testHealthId);
assertTest($fetched !== null, "Record retrieved by findById");
assertTest($fetched['doctor_consultation'] === $docName, "Doctor consultation matches: '{$docName}'");
assertTest($fetched['illness_observation'] === $illness, "Illness observation matches: '{$illness}'");
assertTest($fetched['diagnosis'] === $diag, "Diagnosis matches: '{$diag}'");
assertTest((float)$fetched['quantity_used'] === 2.50, "Decimal quantity matches 2.50");
assertTest($fetched['status'] === 'scheduled', "Initial status is 'scheduled'");

// ----------------------------------------------------
// 4. Add Treatment via helper & check inventory integration
// ----------------------------------------------------
echo "\n--- 4. Add Treatment via record_health_activity ---\n";
$medName2 = "Amoxicillin Trihydrate " . uniqid();
$successHelper = record_health_activity(
    $conn,
    $broilerBatchId,
    'Treatment',
    $medName2,
    4.75,
    date('Y-m-d'),
    'Post-consultation flock treatment',
    'Lethargy and wet droppings',
    'Dr. Rodrigo Perez (Farm Vet)',
    'Colibacillosis',
    'in_progress'
);
assertTest($successHelper === true, "record_health_activity executed successfully");

$stmtCheckHelper = $conn->prepare("SELECT * FROM health_records WHERE medicine_name = ? LIMIT 1");
$stmtCheckHelper->execute([$medName2]);
$helperRow = $stmtCheckHelper->fetch(PDO::FETCH_ASSOC);

assertTest($helperRow && (float)$helperRow['quantity_used'] === 4.75, "Helper recorded exact decimal quantity (4.75)");
assertTest($helperRow && $helperRow['status'] === 'in_progress', "Helper recorded status 'in_progress'");
assertTest($helperRow && $helperRow['doctor_consultation'] === 'Dr. Rodrigo Perez (Farm Vet)', "Helper saved doctor consultation");
assertTest($helperRow && $helperRow['diagnosis'] === 'Colibacillosis', "Helper saved diagnosis");

// ----------------------------------------------------
// 5. Change Treatment Status
// ----------------------------------------------------
echo "\n--- 5. Change Treatment Status ---\n";
// Transition 1: scheduled -> in_progress
$up1 = $healthService->updateStatus($testHealthId, 'in_progress', 1);
assertTest($up1 === true, "Status transition to 'in_progress' succeeded");
$fetchedUp1 = $healthRepo->findById($testHealthId);
assertTest($fetchedUp1['status'] === 'in_progress', "Verified database status is 'in_progress'");

// Transition 2: in_progress -> completed
$up2 = $healthService->updateStatus($testHealthId, 'completed', 1);
assertTest($up2 === true, "Status transition to 'completed' succeeded");
$fetchedUp2 = $healthRepo->findById($testHealthId);
assertTest($fetchedUp2['status'] === 'completed', "Verified database status is 'completed'");

// Transition 3: reject invalid status
$invalidStatusCaught = false;
try {
    $healthService->updateStatus($testHealthId, 'unknown_status', 1);
} catch (Exception $e) {
    $invalidStatusCaught = true;
}
assertTest($invalidStatusCaught, "Rejects invalid treatment status");

// ----------------------------------------------------
// 6. Filter Records
// ----------------------------------------------------
echo "\n--- 6. Filter Records Verification ---\n";
// Filter by batch
$batchFiltered = $healthRepo->getAll(['batch_id' => $broilerBatchId]);
$allBelongToBatch = true;
foreach ($batchFiltered as $bf) {
    if ((int)$bf['batch_id'] !== $broilerBatchId) {
        $allBelongToBatch = false;
        break;
    }
}
assertTest(!empty($batchFiltered) && $allBelongToBatch, "Filter by batch returns records belonging strictly to Batch #{$broilerBatchId}");

// Filter by status
$statusFiltered = $healthRepo->getAll(['status' => 'completed']);
$allCompleted = true;
foreach ($statusFiltered as $sf) {
    if ($sf['status'] !== 'completed') {
        $allCompleted = false;
        break;
    }
}
assertTest(!empty($statusFiltered) && $allCompleted, "Filter by status='completed' returns strictly completed records");

// Filter by type
$typeFiltered = $healthRepo->getAll(['type' => 'treatment']);
$allTreatment = true;
foreach ($typeFiltered as $tf) {
    if ($tf['type'] !== 'treatment') {
        $allTreatment = false;
        break;
    }
}
assertTest(!empty($typeFiltered) && $allTreatment, "Filter by type='treatment' returns strictly treatment records");

// Filter by date range
$today = date('Y-m-d');
$dateFiltered = $healthRepo->getAll(['date_from' => $today, 'date_to' => $today]);
$allToday = true;
foreach ($dateFiltered as $df) {
    if ($df['date'] !== $today) {
        $allToday = false;
        break;
    }
}
assertTest(!empty($dateFiltered) && $allToday, "Filter by date range ({$today}) returns records within date window");

// ----------------------------------------------------
// 7. View Batch Health History
// ----------------------------------------------------
echo "\n--- 7. View Batch Health History ---\n";
$history = $healthService->getBatchHealthHistory($broilerBatchId);
assertTest(!empty($history), "Batch health history retrieved with count " . count($history));
$historyCheck = true;
foreach ($history as $h) {
    if ((int)$h['batch_id'] !== $broilerBatchId) {
        $historyCheck = false;
        break;
    }
}
assertTest($historyCheck, "All records in batch health history strictly linked to Batch #{$broilerBatchId}");

// ----------------------------------------------------
// 8. Clean up created test records
// ----------------------------------------------------
$conn->prepare("DELETE FROM health_records WHERE health_id IN (?, ?)")->execute([$testHealthId, $helperRow['health_id'] ?? 0]);
$conn->prepare("DELETE FROM activities WHERE item_name IN (?, ?)")->execute([$medName, $medName2]);

echo "\n------------------------------------------------------------------------\n";
echo "FEATURE 2 TEST RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "------------------------------------------------------------------------\n";

if ($failCount > 0) {
    exit(1);
}
