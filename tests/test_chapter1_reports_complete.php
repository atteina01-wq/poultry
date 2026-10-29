<?php
require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Services/ReportService.php';
require_once __DIR__ . '/../src/Controllers/ReportController.php';

use Poultry\Services\ReportService;
use Poultry\Controllers\ReportController;

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
echo "CHAPTER 1: CENTRALIZED REPORT GENERATION VERIFICATION SUITE\n";
echo "========================================================================\n\n";

$reportService = new ReportService();

// 1. Broiler Batches Report
echo "--- 1. Broiler Batches Report ---\n";
$batches = $reportService->getProductionReport();
assertTest("Broiler Batches report returns active records", count($batches) >= 4);
assertTest("Broiler Batches contains required fields (batch_code, initial_quantity, current_quantity, mortality, survival_rate_pct)",
    isset($batches[0]['batch_code'], $batches[0]['initial_quantity'], $batches[0]['current_quantity'], $batches[0]['total_mortality'], $batches[0]['survival_rate_pct']));

// Batch filter
$batch4 = $reportService->getProductionReport(null, null, 4);
assertTest("Broiler Batches filter by batch_id = 4 returns exact batch", count($batch4) === 1 && (int)$batch4[0]['batch_id'] === 4);

// Status filter
$activeBatches = $reportService->getProductionReport(null, null, null, 'active');
assertTest("Broiler Batches filter by status = 'active' returns only active batches", count($activeBatches) >= 4 && $activeBatches[0]['status'] === 'active');

// 2. Mortality Report
echo "\n--- 2. Mortality Report ---\n";
$mort = $reportService->getMortalityReport();
assertTest("Mortality report returns records", count($mort) >= 7);
assertTest("Mortality record contains loss_count, reason, batch_code, date, recorded_by_user",
    isset($mort[0]['loss_count'], $mort[0]['reason'], $mort[0]['batch_code'], $mort[0]['date'], $mort[0]['recorded_by_user']));

// Filter mortality by batch
$batch4Mort = $reportService->getMortalityReport(null, null, 4);
assertTest("Mortality report filters correctly by batch_id = 4", count($batch4Mort) > 0 && (int)$batch4Mort[0]['batch_id'] === 4);

// 3. Health & Medication Report
echo "\n--- 3. Health & Medication Report ---\n";
$health = $reportService->getHealthRecordReport();
assertTest("Health & Medication report returns records", count($health) >= 7);
assertTest("Health record contains medicine_name, type, quantity_used, next_due_date, batch_code",
    isset($health[0]['medicine_name'], $health[0]['type'], $health[0]['quantity_used'], $health[0]['batch_code']));

// Filter health by type
$treatments = $reportService->getHealthRecordReport(null, null, null, 'treatment');
assertTest("Health report filters correctly by type = 'treatment'", count($treatments) > 0 && $treatments[0]['type'] === 'treatment');

// 4. Feed Consumption Report
echo "\n--- 4. Feed Consumption Report ---\n";
$feed = $reportService->getConsumptionReport();
assertTest("Feed consumption report returns records", count($feed) >= 14);
assertTest("Feed record contains feed_type, quantity_used, unit, unit_cost_snapshot, total_cost, batch_code",
    isset($feed[0]['feed_type'], $feed[0]['quantity_used'], $feed[0]['unit'], $feed[0]['unit_cost_snapshot'], $feed[0]['total_cost'], $feed[0]['batch_code']));

// 5. Stock Inventory Report
echo "\n--- 5. Stock Inventory Report ---\n";
$inv = $reportService->getInventoryReport();
assertTest("Inventory report returns active items", isset($inv['items']) && count($inv['items']) >= 16);
assertTest("Inventory items have stock_status (In Stock / Low Stock / Out of Stock) and valuation",
    isset($inv['items'][0]['stock_status'], $inv['items'][0]['total_value'], $inv['items'][0]['unit_cost']));

// Filter inventory by category
$feedInv = $reportService->getInventoryReport(null, null, 'feed');
assertTest("Inventory filter by category = 'feed' returns only feed items", count($feedInv['items']) > 0 && $feedInv['items'][0]['category'] === 'feed');

// 6. Worker Activities Report
echo "\n--- 6. Worker Activities & Audit Trail Report ---\n";
$activities = $reportService->getWorkerActivityReport();
assertTest("Worker activity report returns activity logs", count($activities) >= 10);
assertTest("Worker activity contains activity_type, item_name, quantity, batch_code, date",
    isset($activities[0]['activity_type'], $activities[0]['item_name'], $activities[0]['batch_code'], $activities[0]['date']));

// 7. Reservations & Pickups Report
echo "\n--- 7. Reservations & Pickups Report ---\n";
$res = $reportService->getReservationReport();
assertTest("Reservations report returns all reservations", count($res) >= 38);
assertTest("Reservation contains reservation_number, customer_name, pickup_date, reserved_quantity, status",
    isset($res[0]['reservation_number'], $res[0]['customer_name'], $res[0]['pickup_date'], $res[0]['reserved_quantity'], $res[0]['status']));

// Payments, advance, and remaining balance where supported
assertTest("Reservation supports payment fields: total_amount, advance_payment, remaining_balance",
    isset($res[0]['total_amount'], $res[0]['advance_payment'], $res[0]['remaining_balance']));

// Filter reservations by status
$cancelledRes = $reportService->getReservationReport(null, null, 'cancelled');
assertTest("Reservations filter by status = 'cancelled' returns cancelled records", count($cancelledRes) > 0 && $cancelledRes[0]['status'] === 'cancelled');

// 8. Accepted, Returned & Rejected Chickens
echo "\n--- 8. Accepted, Returned & Rejected Chickens Report ---\n";
$returns = $reportService->getReturnReport();
assertTest("Returns & Rejections report returns records", count($returns) >= 24);
assertTest("Return record contains return_number, order_number, customer_name, total_returned_qty, condition_status, reason",
    isset($returns[0]['return_number'], $returns[0]['order_number'], $returns[0]['customer_name'], $returns[0]['total_returned_qty'], $returns[0]['condition_status'], $returns[0]['reason']));

// 9. Operational Expenses Report
echo "\n--- 9. Operational Expenses Report ---\n";
$expenses = $reportService->getExpenseReport();
assertTest("Expenses report returns records", count($expenses) >= 12);
assertTest("Expense record contains category, amount, description, date, source_type, recorded_by_user",
    isset($expenses[0]['category'], $expenses[0]['amount'], $expenses[0]['description'], $expenses[0]['date'], $expenses[0]['recorded_by_user']));

// Filter expenses by category
$wasteExp = $reportService->getExpenseReport(null, null, 'waste');
assertTest("Expense filter by category = 'waste' returns waste expenses", count($wasteExp) > 0 && $wasteExp[0]['category'] === 'waste');

// 10. Financials & Profitability Report
echo "\n--- 10. Financials & Profitability Report ---\n";
$fin = $reportService->getFinancialReport();
assertTest("Financial report returns net_sales, advance_payments, total_expenses, net_profit, expenses_by_category",
    isset($fin['net_sales'], $fin['advance_payments'], $fin['total_expenses'], $fin['net_profit'], $fin['expenses_by_category']));
assertTest("Financial net profit equals net sales minus total expenses",
    abs($fin['net_profit'] - ($fin['net_sales'] - $fin['total_expenses'])) < 0.01);

// 11. Printable Page Markup & Verification
echo "\n--- 11. Printable Output & UI Structure Verification ---\n";
$reportsPageContent = file_get_contents(__DIR__ . '/../poultrychat/reports.php');
assertTest("reports.php contains print-header with ALQUIZAR POULTRY FARM", strpos($reportsPageContent, 'ALQUIZAR POULTRY FARM') !== false);
assertTest("reports.php includes window.print() trigger", strpos($reportsPageContent, 'window.print()') !== false);
assertTest("reports.php has @media print stylesheet", strpos($reportsPageContent, '@media print') !== false);
assertTest("reports.php hides UI elements during print (.sidebar, .navbar, .btn, .filter-card)",
    strpos($reportsPageContent, '.no-print') !== false && strpos($reportsPageContent, 'display: none !important') !== false);
assertTest("reports.php covers all required tabs (production, mortality, health, feed, inventory, reservations, returns, expenses, financial, audit)",
    strpos($reportsPageContent, 'production-tab') !== false &&
    strpos($reportsPageContent, 'mortality-tab') !== false &&
    strpos($reportsPageContent, 'health-tab') !== false &&
    strpos($reportsPageContent, 'feed-tab') !== false &&
    strpos($reportsPageContent, 'inventory-tab') !== false &&
    strpos($reportsPageContent, 'reservations-tab') !== false &&
    strpos($reportsPageContent, 'returns-tab') !== false &&
    strpos($reportsPageContent, 'expenses-tab') !== false &&
    strpos($reportsPageContent, 'financial-tab') !== false &&
    strpos($reportsPageContent, 'audit-tab') !== false);

echo "\n------------------------------------------------------------------------\n";
echo "REPORT TEST SUITE RESULTS: $passed PASSED, $failed FAILED\n";
echo "------------------------------------------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
