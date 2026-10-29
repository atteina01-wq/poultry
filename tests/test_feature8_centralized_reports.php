<?php
/**
 * Test Suite: Feature 8 - Centralized Report Generation Verification
 * 
 * Verifies that reports correctly display:
 * 1. Broiler batches (production, headcount, mortality %, survival %)
 * 2. Mortality (losses, reasons, dates, user)
 * 3. Health/medication (treatments, doctor consultation, diagnosis, status, next due)
 * 4. Feed consumption (quantity, stage, cost snapshot, total cost)
 * 5. Stock inventory (categories, valuation, low/out-of-stock status)
 * 6. Worker activities (operations, worker identity, timestamps, tasks)
 * 7. Reservations/pickups (broiler counts, pickup dates, contract amounts)
 * 8. Accepted/returned/rejected chickens (disposition, condition, reasons)
 * 9. Payments & balance (advance paid, total amount, balance due, net profit)
 * 10. Expenses (categories, amounts, related batches/items)
 * 
 * Also verifies:
 * - Filters (date, batch, status, category)
 * - Calculations (survival rate, balance, valuation, profit)
 * - Print layout & CSS (@media print, window.print, ALQUIZAR POULTRY FARM header)
 * - Broiler-only content (no egg/layer residue)
 * - Live database compatibility
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Services/ReportService.php';

echo "========================================================================\n";
echo "FEATURE 8: CENTRALIZED REPORT GENERATION VERIFICATION SUITE\n";
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

$reportService = new \Poultry\Services\ReportService();

// -------------------------------------------------------------------------
// 1. Broiler Batches Report
// -------------------------------------------------------------------------
echo "--- 1. Broiler Batches Production Report ---\n";
$batches = $reportService->getProductionReport();
assertTest(is_array($batches) && count($batches) > 0, "Production report returns active broiler batches");

$firstB = $batches[0];
assertTest(isset($firstB['batch_code']), "Report includes batch_code");
assertTest(isset($firstB['initial_quantity']), "Report includes initial_quantity");
assertTest(isset($firstB['current_quantity']), "Report includes current_quantity");
assertTest(isset($firstB['total_mortality']), "Report includes total_mortality");
assertTest(isset($firstB['survival_rate_pct']), "Report includes survival_rate_pct");

// Calculation test: survival rate
if ($firstB['initial_quantity'] > 0) {
    $expectedSurvival = round((($firstB['initial_quantity'] - $firstB['total_mortality']) / $firstB['initial_quantity']) * 100, 2);
    assertTest((float)$firstB['survival_rate_pct'] === (float)$expectedSurvival, "survival_rate_pct accurately calculated: {$firstB['survival_rate_pct']}%");
}

// Filter test: status filter
$activeBatches = $reportService->getProductionReport(null, null, null, 'active');
$allActive = true;
foreach ($activeBatches as $ab) {
    if ($ab['status'] !== 'active') { $allActive = false; break; }
}
assertTest($allActive && count($activeBatches) > 0, "Batch status filter ('active') strictly enforced");

// -------------------------------------------------------------------------
// 2. Mortality Report
// -------------------------------------------------------------------------
echo "\n--- 2. Mortality Log Report ---\n";
$mortRecords = $reportService->getMortalityReport();
assertTest(is_array($mortRecords) && count($mortRecords) > 0, "Mortality report returns logged mortality records");
$firstM = $mortRecords[0];
assertTest(isset($firstM['loss_count']) && $firstM['loss_count'] > 0, "Mortality records display positive loss_count");
assertTest(isset($firstM['reason']), "Mortality records display reason");
assertTest(isset($firstM['batch_code']), "Mortality records linked to batch_code");
assertTest(isset($firstM['recorded_by_user']), "Mortality records show recorded_by_user");

// -------------------------------------------------------------------------
// 3. Health & Medication Report
// -------------------------------------------------------------------------
echo "\n--- 3. Flock Health & Medication Report ---\n";
$healthRecords = $reportService->getHealthRecordReport();
assertTest(is_array($healthRecords) && count($healthRecords) > 0, "Health report returns flock health events");
$firstH = $healthRecords[0];
assertTest(isset($firstH['medicine_name']), "Health record contains medicine_name");
assertTest(array_key_exists('doctor_consultation', $firstH), "Health record contains doctor_consultation column");
assertTest(array_key_exists('diagnosis', $firstH), "Health record contains diagnosis column");
assertTest(array_key_exists('status', $firstH), "Health record contains status column");

// Filter test: type
$treatments = $reportService->getHealthRecordReport(null, null, null, 'treatment');
$allTreatments = true;
foreach ($treatments as $t) {
    if ($t['type'] !== 'treatment') { $allTreatments = false; break; }
}
assertTest($allTreatments, "Health record type filter ('treatment') strictly filtered");

// -------------------------------------------------------------------------
// 4. Feed Consumption Report
// -------------------------------------------------------------------------
echo "\n--- 4. Feed Consumption Report ---\n";
$feedRecords = $reportService->getConsumptionReport();
assertTest(is_array($feedRecords) && count($feedRecords) > 0, "Feed consumption report returns consumption rows");
$firstF = $feedRecords[0];
assertTest(isset($firstF['feed_type']), "Feed report displays feed_type");
assertTest(array_key_exists('feed_stage', $firstF), "Feed report includes feed_stage column");
assertTest(isset($firstF['quantity_used']), "Feed report displays quantity_used");
assertTest(isset($firstF['total_cost']), "Feed report displays total_cost");
$expectedCost = round($firstF['quantity_used'] * $firstF['unit_cost_snapshot'], 2);
assertTest((float)$firstF['total_cost'] === (float)$expectedCost, "Feed total_cost matches quantity * unit_cost_snapshot");

// -------------------------------------------------------------------------
// 5. Stock Inventory Report
// -------------------------------------------------------------------------
echo "\n--- 5. Stock Inventory Report ---\n";
$invReport = $reportService->getInventoryReport();
assertTest(is_array($invReport['items']) && count($invReport['items']) > 0, "Inventory report returns active inventory items");
$firstI = $invReport['items'][0];
assertTest(isset($firstI['item_name']), "Inventory item displays item_name");
assertTest(isset($firstI['category']), "Inventory item displays category");
assertTest(isset($firstI['stock_status']), "Inventory item displays stock_status");
assertTest(isset($firstI['total_value']), "Inventory item displays total_value");

// Filter test: category = 'vitamins'
$vitaminItems = $reportService->getInventoryReport(null, null, 'vitamins');
$allVitamins = true;
foreach ($vitaminItems['items'] as $vi) {
    if ($vi['category'] !== 'vitamins') { $allVitamins = false; break; }
}
assertTest($allVitamins && count($vitaminItems['items']) > 0, "Inventory category filter ('vitamins') strictly enforced");

// -------------------------------------------------------------------------
// 6. Worker Activities Report
// -------------------------------------------------------------------------
echo "\n--- 6. Worker Activities & Audit Trail Report ---\n";
$workerActs = $reportService->getWorkerActivityReport();
assertTest(is_array($workerActs) && count($workerActs) > 0, "Worker activity report returns activity rows");
$firstW = $workerActs[0];
assertTest(isset($firstW['activity_type']), "Worker activity displays activity_type");
assertTest(isset($firstW['batch_code']), "Worker activity displays batch_code");
assertTest(array_key_exists('worker_name', $firstW), "Worker activity includes worker_name column");

// -------------------------------------------------------------------------
// 7. Reservations, Pickups & Payments Report
// -------------------------------------------------------------------------
echo "\n--- 7. Reservations, Pickups & Balances Report ---\n";
$reservations = $reportService->getReservationReport();
assertTest(is_array($reservations) && count($reservations) > 0, "Reservation report returns broiler reservation rows");
$firstR = $reservations[0];
assertTest(isset($firstR['reservation_number']), "Reservation displays reservation_number");
assertTest(isset($firstR['customer_name']), "Reservation displays customer_name");
assertTest(isset($firstR['reserved_quantity']), "Reservation displays reserved_quantity");
assertTest(isset($firstR['total_amount']), "Reservation displays total_amount");
assertTest(isset($firstR['advance_payment']), "Reservation displays advance_payment");
assertTest(isset($firstR['remaining_balance']), "Reservation displays remaining_balance");
assertTest(isset($firstR['accepted_quantity']), "Reservation displays accepted_quantity");
assertTest(isset($firstR['rejected_quantity']), "Reservation displays rejected_quantity");

// Calculation test: balance = total_amount - advance_payment
$expectedBalance = (float)$firstR['total_amount'] - (float)$firstR['advance_payment'];
assertTest(abs((float)$firstR['remaining_balance'] - $expectedBalance) < 0.01, "Reservation remaining_balance equals total_amount minus advance_payment");

// -------------------------------------------------------------------------
// 8. Accepted, Returned & Rejected Chickens Report
// -------------------------------------------------------------------------
echo "\n--- 8. Returns & Rejections Report ---\n";
$returns = $reportService->getReturnReport();
assertTest(is_array($returns) && count($returns) > 0, "Return report returns chicken return rows");
$firstRet = $returns[0];
assertTest(isset($firstRet['return_number']), "Return record displays return_number");
assertTest(isset($firstRet['total_returned_qty']), "Return record displays total_returned_qty");
assertTest(isset($firstRet['condition_status']), "Return record displays condition_status");
assertTest(isset($firstRet['reason']), "Return record displays reason");

// -------------------------------------------------------------------------
// 9. Operational Expenses Report
// -------------------------------------------------------------------------
echo "\n--- 9. Operational Expenses Report ---\n";
$expenses = $reportService->getExpenseReport();
assertTest(is_array($expenses) && count($expenses) > 0, "Expense report returns operational expense records");
$firstE = $expenses[0];
assertTest(isset($firstE['category']), "Expense record displays category");
assertTest(isset($firstE['amount']) && (float)$firstE['amount'] > 0, "Expense record displays amount");

// -------------------------------------------------------------------------
// 10. Financials & Profitability Report
// -------------------------------------------------------------------------
echo "\n--- 10. Financials & Profitability Report ---\n";
$fin = $reportService->getFinancialReport();
assertTest(isset($fin['net_sales']), "Financial report returns net_sales");
assertTest(isset($fin['advance_payments']), "Financial report returns advance_payments");
assertTest(isset($fin['total_expenses']), "Financial report returns total_expenses");
assertTest(isset($fin['net_profit']), "Financial report returns net_profit");
assertTest(isset($fin['expenses_by_category']), "Financial report breaks down expenses_by_category");

// Calculation test: net profit = net sales - total expenses
$expectedProfit = (float)$fin['net_sales'] - (float)$fin['total_expenses'];
assertTest(abs((float)$fin['net_profit'] - $expectedProfit) < 0.01, "Financial net_profit equals net_sales minus total_expenses");

// -------------------------------------------------------------------------
// 11. UI & Print Layout Verification
// -------------------------------------------------------------------------
echo "\n--- 11. UI & Print Layout Verification ---\n";
$reportsPhp = file_get_contents(__DIR__ . '/../poultrychat/reports.php');
assertTest(strpos($reportsPhp, 'ALQUIZAR POULTRY FARM') !== false, "reports.php includes ALQUIZAR POULTRY FARM in print header");
assertTest(strpos($reportsPhp, 'window.print()') !== false, "reports.php includes print trigger button");
assertTest(strpos($reportsPhp, '@media print') !== false, "reports.php defines @media print styles");
assertTest(strpos($reportsPhp, '.sidebar, .navbar') !== false, "reports.php hides navigation and sidebar during printing");
assertTest(strpos($reportsPhp, 'Doctor Consultation') !== false, "reports.php displays Doctor Consultation in Health tab");
assertTest(strpos($reportsPhp, 'feed_stage') !== false, "reports.php displays feed_stage in Feed tab");
assertTest(strpos($reportsPhp, 'worker_name') !== false, "reports.php displays worker_name in Audit tab");

// Check absence of obsolete egg/layer references
assertTest(stripos($reportsPhp, 'egg_grade') === false, "reports.php contains no obsolete egg_grade references");
assertTest(stripos($reportsPhp, 'layer') === false, "reports.php contains no layer references");

echo "\n========================================================================\n";
echo "SUMMARY: Passed: $passCount | Failed: $failCount\n";
echo "========================================================================\n";

if ($failCount > 0) {
    exit(1);
}
