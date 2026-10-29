<?php
require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../poultrychat/helpers.php';
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Repositories/BatchRepository.php';
require_once __DIR__ . '/../src/Services/BatchService.php';

use Poultry\Repositories\BatchRepository;
use Poultry\Services\BatchService;

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] $name\n";
        $passed++;
    } else {
        echo "[FAIL] $name: $details\n";
        $failed++;
    }
}

echo "========================================================================\n";
echo "FEATURE 1: BROILER BATCH MANAGEMENT VERIFICATION SUITE\n";
echo "========================================================================\n\n";

$batchRepo = new BatchRepository();
$batchService = new BatchService();

$testCode = 'TEST-BR-' . time();

// --- 1. Create Batch ---
echo "--- 1. Create Broiler Batch ---\n";
$createdBatch = $batchService->createBatch([
    'batch_code'            => $testCode,
    'arrival_date'          => '2026-09-01',
    'production_date'       => '2026-09-02',
    'initial_quantity'      => 500,
    'breed'                 => 'Cobb 500',
    'house_pen'             => 'Pen Test-1',
    'supplier'              => 'B-Meg Supplier',
    'expected_harvest_date' => '2026-10-06',
    'notes'                 => 'Initial test batch creation'
], 1);

assertTest("Batch created successfully with assigned ID", !empty($createdBatch['batch_id']));
assertTest("Batch code matches", $createdBatch['batch_code'] === $testCode);
assertTest("Flock type is broiler", $createdBatch['flock_type'] === 'broiler');
assertTest("Arrival date saved correctly", $createdBatch['arrival_date'] === '2026-09-01');
assertTest("Production date saved correctly", $createdBatch['production_date'] === '2026-09-02');
assertTest("Initial and current headcount both start at 500", (int)$createdBatch['initial_quantity'] === 500 && (int)$createdBatch['current_quantity'] === 500);

$batchId = (int)$createdBatch['batch_id'];

// --- 2. View Batch Details ---
echo "\n--- 2. View Batch Details ---\n";
$fetched = $batchService->getBatch($batchId);
assertTest("Fetched batch details matches created record",
    $fetched['batch_code'] === $testCode &&
    $fetched['breed'] === 'Cobb 500' &&
    $fetched['house_pen'] === 'Pen Test-1' &&
    $fetched['supplier'] === 'B-Meg Supplier');

$batchPageHtml = file_get_contents(__DIR__ . '/../poultrychat/batch.php');
assertTest("batch.php includes #viewBatchModal markup", strpos($batchPageHtml, 'id="viewBatchModal"') !== false);
assertTest("batch.php includes openViewBatchModal() JS function", strpos($batchPageHtml, 'openViewBatchModal') !== false);
assertTest("batch.php includes #editBatchModal markup", strpos($batchPageHtml, 'id="editBatchModal"') !== false);
assertTest("batch.php removed layer wording from table title", strpos($batchPageHtml, 'Active and historical broiler flocks across all houses') !== false);

// --- 3. Edit Batch Details ---
echo "\n--- 3. Edit Batch Details ---\n";
$updatedBatch = $batchService->updateBatch($batchId, [
    'breed'                 => 'Ross 308',
    'house_pen'             => 'Pen Test-2',
    'supplier'              => 'San Miguel Feeds',
    'initial_quantity'      => 600,
    'expected_harvest_date' => '2026-10-10',
    'notes'                 => 'Updated batch notes via edit modal'
], 1);

assertTest("Batch breed updated to Ross 308", $updatedBatch['breed'] === 'Ross 308');
assertTest("Batch house_pen updated to Pen Test-2", $updatedBatch['house_pen'] === 'Pen Test-2');
assertTest("Batch initial_quantity updated to 600", (int)$updatedBatch['initial_quantity'] === 600);
assertTest("Batch current_quantity synchronized to 600", (int)$updatedBatch['current_quantity'] === 600);

// --- 4. Change Status ---
echo "\n--- 4. Change Status ---\n";
$harvested = $batchService->updateStatus($batchId, 'harvested', 1);
assertTest("Batch status updated to 'harvested'", $harvested['status'] === 'harvested');

$activeAgain = $batchService->updateStatus($batchId, 'active', 1);
assertTest("Batch status returned to 'active'", $activeAgain['status'] === 'active');

// --- 5. Record Mortality & Verify Current Headcount ---
echo "\n--- 5. Record Mortality & Verify Headcount ---\n";
$resMort1 = record_mortality($conn, $batchId, -25, 'Routine cull / heat stress', '2026-09-15');
assertTest("Normal mortality of 25 birds recorded", $resMort1 === true);

$afterMort1 = $batchService->getBatch($batchId);
assertTest("Current headcount immediately reduced from 600 to 575", (int)$afterMort1['current_quantity'] === 575);

// Safe syncHeadcount check
$synced = $batchService->syncHeadcount($batchId);
assertTest("syncHeadcount() calculates: current_quantity = initial_quantity - mortality (600 - 25 = 575)",
    $synced['current_quantity'] === 575);

// --- 6. Prevent Negative Current Quantity on Mortality ---
echo "\n--- 6. Prevent Negative Current Quantity on Mortality ---\n";
// Attempt to record 10,000 deaths on a flock with 575 birds
$resExcess = record_mortality($conn, $batchId, -10000, 'Impossible massive mortality', '2026-09-16');
assertTest("Excess mortality (-10,000 vs 575 headcount) rejected", $resExcess === false);

$afterExcess = $batchService->getBatch($batchId);
assertTest("Current headcount preserved at 575 and NOT negative", (int)$afterExcess['current_quantity'] === 575);

// Test adjustQuantity exception
$exceptionThrown = false;
try {
    $batchRepo->adjustQuantity($batchId, -2000);
} catch (Exception $e) {
    $exceptionThrown = true;
}
assertTest("BatchRepository::adjustQuantity throws exception on negative headcount", $exceptionThrown === true);

// --- Cleanup Test Batch ---
echo "\n--- Cleanup Test Batch ---\n";
$conn->exec("DELETE FROM mortality_records WHERE batch_id = {$batchId}");
$conn->exec("DELETE FROM audit_logs WHERE table_name = 'batches' AND record_id = {$batchId}");
$conn->exec("DELETE FROM batches WHERE batch_id = {$batchId}");
echo "Test batch cleaned up cleanly.\n";

echo "\n------------------------------------------------------------------------\n";
echo "FEATURE 1 TEST RESULTS: $passed PASSED, $failed FAILED\n";
echo "------------------------------------------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
