<?php
// Mock server environment for index.php as Magnolia Chicks
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/poultry/poultrychat/index.php';
$_SERVER['SCRIPT_NAME'] = '/poultry/poultrychat/index.php';
session_start();
$_SESSION['user_id'] = 12;
$_SESSION['username'] = 'Magnolia Chicks';
$_SESSION['role'] = 'integrator';
$_SESSION['customer_id'] = 13;
$_SESSION['full_name'] = 'Atteina Shyra Misa';

ob_start();
include __DIR__ . '/../poultrychat/index.php';
$html = ob_get_clean();

echo "=== RENDER TEST FOR INTEGRATOR (MAGNOLIA CHICKS) ON index.php ===\n";

// 1. Check Batch List
$hasBatchList = (strpos($html, 'id="batchesSection"') !== false);
echo "1. Batch List visible on index.php: " . ($hasBatchList ? "YES (FAIL)" : "NO - REMOVED (PASS)") . "\n";

// 2. Check 5-step Workflow
$hasWorkflow = (strpos($html, 'Commercial Broiler Reservation &amp; Intake Process') !== false);
echo "2. Reservation Process Workflow: " . ($hasWorkflow ? "PRESENT (PASS)" : "MISSING (FAIL)") . "\n";

// 3. Check All 5 steps from photo
$s1 = strpos($html, 'Step 1 — Company submits a reservation') !== false && strpos($html, 'Pending Approval') !== false;
$s2 = strpos($html, 'Step 2 — Farm Admin approves') !== false && strpos($html, 'Approved — Awaiting Delivery') !== false;
$s3 = strpos($html, 'Step 3 — Company delivers the chicks') !== false && strpos($html, 'Receiving in Progress') !== false;
$s4 = strpos($html, 'Step 4 — Farm verifies receipt') !== false && strpos($html, 'Receipt Confirmed') !== false;
$s5 = strpos($html, 'Step 5 — Growing batch is activated') !== false && strpos($html, 'Growing') !== false;
echo "3. Steps 1 to 5 matches photo: " . ($s1 && $s2 && $s3 && $s4 && $s5 ? "ALL 5 MATCH (PASS)" : "FAIL") . "\n";

// 4. Check Reserved Birds
if (preg_match('/<div class="stat-val"[^>]*>([\d,]+)<\/div>\s*<div class="stat-lbl">\s*Your Reserved Birds\s*<\/div>/i', $html, $m)) {
    echo "4. Your Reserved Birds KPI: " . $m[1] . " birds " . ($m[1] === '3,000' ? "(PASS - Exactly 3,000 birds!)" : "(FAIL - got {$m[1]})") . "\n";
} else {
    echo "4. Reserved Birds match failed to find pattern.\n";
}

// 5. Test reservations.php
ob_start();
$_SERVER['PHP_SELF'] = '/poultry/poultrychat/reservations.php';
include __DIR__ . '/../poultrychat/reservations.php';
$resHtml = ob_get_clean();

$hasResWorkflow = (strpos($resHtml, 'Commercial Broiler Reservation &amp; Intake Process') !== false);
echo "5. reservations.php 5-Step Workflow: " . ($hasResWorkflow ? "PRESENT (PASS)" : "MISSING (FAIL)") . "\n";

if (preg_match('/<div class="fs-4 fw-bold text-primary mt-1">([\d,]+)/i', $resHtml, $m2)) {
    echo "6. reservations.php Your Reserved Birds: " . $m2[1] . " birds " . ($m2[1] === '3,000' ? "(PASS - Exactly 3,000 birds!)" : "(FAIL - got {$m2[1]})") . "\n";
}
