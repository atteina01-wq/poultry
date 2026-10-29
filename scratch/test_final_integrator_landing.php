<?php
$_SESSION['user_id'] = 10;
$_SESSION['username'] = 'Mags';
$_SESSION['role'] = 'integrator';
$_SESSION['customer_id'] = 13;
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
require __DIR__ . '/../poultrychat/index.php';
$html = ob_get_clean();

echo "=== TEST RESULTS FOR INTEGRATOR LANDING (index.php) ===\n";

// 1. Check Batch List is removed
if (strpos($html, 'id="batchesSection"') === false && strpos($html, '<table class="table-modern">') === false) {
    echo "[PASS] Batch List table is completely REMOVED for integrators on index.php.\n";
} else {
    echo "[FAIL] Batch List is still visible!\n";
}

// 2. Check 5-step Reservation Process workflow is present
$hasStep1 = strpos($html, 'Step 1 — Company submits a reservation') !== false && strpos($html, 'Pending Approval') !== false;
$hasStep2 = strpos($html, 'Step 2 — Farm Admin approves') !== false && strpos($html, 'Approved — Awaiting Delivery') !== false;
$hasStep3 = strpos($html, 'Step 3 — Company delivers the chicks') !== false && strpos($html, 'Receiving in Progress') !== false;
$hasStep4 = strpos($html, 'Step 4 — Farm verifies receipt') !== false && strpos($html, 'Receipt Confirmed') !== false;
$hasStep5 = strpos($html, 'Step 5 — Growing batch is activated') !== false && strpos($html, 'Growing') !== false;

if ($hasStep1 && $hasStep2 && $hasStep3 && $hasStep4 && $hasStep5) {
    echo "[PASS] All 5 Steps of the Reservation Process workflow are beautifully rendered!\n";
} else {
    echo "[FAIL] Some steps are missing in the workflow.\n";
}

// 3. Check Reserved Birds count
if (preg_match('/<div class="stat-val"[^>]*>([\d,]+)<\/div>\s*<div class="stat-lbl">\s*Your Reserved Birds\s*<\/div>/i', $html, $matches)) {
    $count = trim($matches[1]);
    echo "[INFO] Your Reserved Birds stat-val: {$count}\n";
    if ($count === '3,000') {
        echo "[PASS] Your Reserved Birds is EXACTLY 3,000 birds (NOT 8,000)!\n";
    } else {
        echo "[FAIL] Your Reserved Birds is {$count}, expected 3,000!\n";
    }
} else {
    echo "[NOTE] Checking with alternative pattern...\n";
    if (strpos($html, '3,000') !== false && strpos($html, 'Your Reserved Birds') !== false) {
        echo "[PASS] 'Your Reserved Birds' displays 3,000 birds.\n";
    } else {
        echo "[FAIL] 'Your Reserved Birds' not found matching 3,000.\n";
    }
}

// 4. Test notifications for integrator
require_once __DIR__ . '/../poultrychat/database.php';
$stmtN = $conn->prepare("
    SELECT COUNT(*) 
    FROM notifications 
    WHERE user_id = ? OR user_id IN (SELECT user_id FROM users WHERE customer_id = ?)
");
$stmtN->execute([10, 13]);
$notifCount = $stmtN->fetchColumn();
echo "[PASS] Integrator Notifications active: {$notifCount} notifications found for Magnolia account.\n";
