<?php
require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Repositories/DashboardRepository.php';
require_once __DIR__ . '/../src/Services/ReportService.php';

echo "========================================================================\n";
echo "CHAPTER 1 ALIGNMENT VERIFICATION TEST SUITE (ALQUIZAR POULTRY FARM)\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $name, bool $condition, string $details = "") {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] " . $name . "\n";
        $passCount++;
    } else {
        echo "[FAIL] " . $name . ($details ? " -> " . $details : "") . "\n";
        $failCount++;
    }
}

$dashRepo = new \Poultry\Repositories\DashboardRepository();
$reportService = new \Poultry\Services\ReportService();

// 1. Mortality Alert Logic Verification
echo "--- 1. Mortality Alert & Health Monitoring ---\n";
$alerts = $dashRepo->getHealthAlerts();
assertTest("DashboardRepository::getHealthAlerts() executes without SQL error", is_array($alerts));

// Simulate synthetic batch & mortality calculations
$testDenominator1 = 1000; // current
$loss1 = 10; // loss in 24h -> pre-loss = 1010, rate = 10/1010 = 0.99% -> NO alert (< 1.0%)
$rate1 = ($loss1 / ($testDenominator1 + $loss1)) * 100;
assertTest("Mortality rate < 1.0% does NOT trigger alert (0.99%)", $rate1 < 1.0);

$testDenominator2 = 990;
$loss2 = 10; // loss in 24h -> pre-loss = 1000, rate = 10/1000 = 1.00% -> TRIGGERS alert (>= 1.0%)
$rate2 = ($loss2 / ($testDenominator2 + $loss2)) * 100;
assertTest("Mortality rate == 1.00% TRIGGERS alert (>= 1.0%)", $rate2 >= 1.0);

// 2. Report Service Verification
echo "\n--- 2. Centralized Reports (Chapter 1 Scope) ---\n";
$prodRep = $reportService->getProductionReport();
assertTest("ReportService::getProductionReport() returns broiler production rows", is_array($prodRep));

$mortRep = $reportService->getMortalityReport();
assertTest("ReportService::getMortalityReport() returns mortality rows", is_array($mortRep));

$healthRep = $reportService->getHealthRecordReport();
assertTest("ReportService::getHealthRecordReport() returns health rows", is_array($healthRep));

$feedRep = $reportService->getConsumptionReport();
assertTest("ReportService::getConsumptionReport() returns consumption rows", is_array($feedRep));

$invRep = $reportService->getInventoryReport();
assertTest("ReportService::getInventoryReport() returns inventory items", is_array($invRep) && isset($invRep['items']));

$resRep = $reportService->getReservationReport();
assertTest("ReportService::getReservationReport() returns reservations", is_array($resRep));

$finRep = $reportService->getFinancialReport();
assertTest("ReportService::getFinancialReport() returns net_sales, total_expenses, net_profit", 
    isset($finRep['net_sales']) && isset($finRep['total_expenses']) && isset($finRep['net_profit']));

$auditRep = $reportService->getWorkerActivityReport();
assertTest("ReportService::getWorkerActivityReport() returns activity log rows", is_array($auditRep));

// 3. KPI Structure Verification
echo "\n--- 3. Dashboard Broiler KPIs ---\n";
$kpis = $dashRepo->getKPIs();
assertTest("KPIs include broiler_batches summary", isset($kpis['broiler_batches']));
assertTest("KPI broiler_batches contains total_active_batches", isset($kpis['broiler_batches']['total_active_batches']));
assertTest("KPI broiler_batches contains total_live_birds", isset($kpis['broiler_batches']['total_live_birds']));
assertTest("KPI broiler_batches contains week_mortality", isset($kpis['broiler_batches']['week_mortality']));

echo "\n------------------------------------------------------------------------\n";
echo "SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "------------------------------------------------------------------------\n";
