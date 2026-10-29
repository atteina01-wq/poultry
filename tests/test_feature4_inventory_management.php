<?php
/**
 * Test Suite: Feature 4 - Inventory Management
 */

require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../poultrychat/helpers.php';
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Repositories/InventoryRepository.php';
require_once __DIR__ . '/../src/Services/InventoryService.php';

echo "========================================================================\n";
echo "FEATURE 4: INVENTORY MANAGEMENT VERIFICATION SUITE\n";
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

$invRepo = new \Poultry\Repositories\InventoryRepository();
$invService = new \Poultry\Services\InventoryService();

// ----------------------------------------------------
// 1. Schema & Category Checks
// ----------------------------------------------------
echo "--- 1. Schema Check: 'vitamins' in inventory.category ---\n";
$stmtCol = $conn->query("SHOW COLUMNS FROM inventory LIKE 'category'");
$col = $stmtCol->fetch(PDO::FETCH_ASSOC);

assertTest($col !== false, "category column exists in inventory");
assertTest(strpos(strtolower($col['Type']), "'vitamins'") !== false, "inventory.category includes 'vitamins'");

// Check Vitamins item migration
$stmtVit = $conn->query("SELECT * FROM inventory WHERE item_name = 'Vitamins'");
$vitItem = $stmtVit->fetch(PDO::FETCH_ASSOC);
assertTest($vitItem && $vitItem['category'] === 'vitamins', "Existing 'Vitamins' item migrated to category='vitamins'");

// ----------------------------------------------------
// 2. UI & Renaming Checks
// ----------------------------------------------------
echo "\n--- 2. UI Markup Checks in inventory.php ---\n";
$invFileContent = file_get_contents(__DIR__ . '/../poultrychat/inventory.php');

assertTest(strpos($invFileContent, 'value="vitamins"') !== false, "inventory.php includes vitamins option in category select");
assertTest(strpos($invFileContent, 'Other Farm Supplies') !== false, "inventory.php renames supply to Other Farm Supplies");
assertTest(strpos($invFileContent, 'Overall Valuation') !== false, "inventory.php contains Overall Valuation KPI card");
assertTest(strpos($invFileContent, 'Low Stock Count') !== false, "inventory.php contains Low Stock Count KPI card");
assertTest(strpos($invFileContent, 'Out of Stock Count') !== false, "inventory.php contains Out of Stock Count KPI card");
assertTest(strpos($invFileContent, 'Remaining Stock') !== false, "inventory.php contains Remaining Stock column in usage history");

// ----------------------------------------------------
// 3. Receiving Test (createItem with Lot)
// ----------------------------------------------------
echo "\n--- 3. Receiving Test: Item & Lot Creation ---\n";
$testItemName = "Test Broiler Multivitamins " . uniqid();
$lotNumber = "LOT-VIT-" . rand(1000, 9999);

$created = $invService->createItem([
    'item_name' => $testItemName,
    'category' => 'vitamins',
    'quantity' => 100.00,
    'unit' => 'Bottles',
    'unit_cost' => 250.00,
    'lot_number' => $lotNumber,
    'supplier' => 'AgriVet Solutions',
    'received_date' => date('Y-m-d'),
    'expiry_date' => date('Y-m-d', strtotime('+365 days'))
], 1);

$testInvId = (int)$created['inventory_id'];
assertTest($testInvId > 0, "Received item created with ID #{$testInvId}");
assertTest($created['category'] === 'vitamins', "Item category is 'vitamins'");
assertTest((float)$created['quantity'] === 100.00, "Initial quantity is 100.00 Bottles");
assertTest(!empty($created['lots']), "Inventory lot created and linked");
assertTest($created['lots'][0]['lot_number'] === $lotNumber, "Lot number matches {$lotNumber}");

// ----------------------------------------------------
// 4. Usage & Stock Balance Test
// ----------------------------------------------------
echo "\n--- 4. Usage & Stock Balance Deduction ---\n";
$deductQty = 15.00;
$invRepo->deductStock($testInvId, $deductQty);

$afterDeduct = $invRepo->findById($testInvId);
assertTest((float)$afterDeduct['quantity'] === 85.00, "Quantity deducted from 100.00 to 85.00 (difference: 15.00)");
assertTest((float)$afterDeduct['previous_quantity'] === 100.00, "previous_quantity stored as 100.00");

// ----------------------------------------------------
// 5. Negative Stock Protection Test
// ----------------------------------------------------
echo "\n--- 5. Negative Stock Prevention ---\n";
// Attempt to deduct 500 when only 85 available
$negativeDeductCaught = false;
try {
    $invRepo->deductStock($testInvId, 500.00);
} catch (Exception $e) {
    $negativeDeductCaught = true;
}
assertTest($negativeDeductCaught, "deductStock rejected excessive deduction (+500 vs 85 available)");

$afterFailedDeduct = $invRepo->findById($testInvId);
assertTest((float)$afterFailedDeduct['quantity'] === 85.00, "Stock preserved at 85.00 and protected from negative balance");

// ----------------------------------------------------
// 6. Low Stock & Out-of-Stock Condition Checks
// ----------------------------------------------------
echo "\n--- 6. Low Stock & Out-of-Stock KPI Calculations ---\n";
// Set test item quantity to 5 (low stock: <= 10)
$conn->prepare("UPDATE inventory SET quantity = 5.00 WHERE inventory_id = ?")->execute([$testInvId]);
$kpisLow = $invService->getKpis();
assertTest($kpisLow['low_stock_count'] >= 1, "Low stock count reflects items <= 10 units");

// Set test item quantity to 0 (out of stock: <= 0)
$conn->prepare("UPDATE inventory SET quantity = 0.00 WHERE inventory_id = ?")->execute([$testInvId]);
$kpisOut = $invService->getKpis();
assertTest($kpisOut['out_of_stock_count'] >= 1, "Out of stock count reflects items <= 0 units");
assertTest($kpisOut['total_valuation'] > 0, "Overall valuation calculated (> ₱0.00)");

// ----------------------------------------------------
// 7. Cleanup & Data Preservation
// ----------------------------------------------------
echo "\n--- 7. Cleanup & Verification ---\n";
$conn->prepare("DELETE FROM inventory_lots WHERE inventory_id = ?")->execute([$testInvId]);
$conn->prepare("DELETE FROM inventory WHERE inventory_id = ?")->execute([$testInvId]);
$conn->prepare("DELETE FROM audit_logs WHERE table_name = 'inventory' AND record_id = ?")->execute([$testInvId]);

$totalItems = (int)$conn->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
assertTest($totalItems >= 15, "All legitimate inventory items preserved (Total: {$totalItems})");

echo "\n------------------------------------------------------------------------\n";
echo "FEATURE 4 TEST RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "------------------------------------------------------------------------\n";

if ($failCount > 0) {
    exit(1);
}
