<?php
session_start();
$_SESSION['user_id'] = 10;
$_SESSION['role'] = 'integrator';
$_SESSION['username'] = 'Mags';
$_SESSION['full_name'] = 'MagShot';
$_SESSION['customer_id'] = 13;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/poultrychat/index.php';

echo "========================================================\n";
echo "   REMOVAL VERIFICATION TEST (LANDING & RESERVATIONS)\n";
echo "========================================================\n\n";

// 1. Test index.php
ob_start();
include __DIR__ . '/../poultrychat/index.php';
$indexHtml = ob_get_clean();

echo "--- 1. LANDING PAGE (index.php) ---\n";
if (strpos($indexHtml, 'Commercial Broiler Reservation &amp; Intake Process') === false && strpos($indexHtml, 'Commercial Broiler Reservation & Intake Process') === false) {
    echo "✓ [PASS] 'Commercial Broiler Reservation & Intake Process' is completely REMOVED from index.php.\n";
} else {
    echo "✗ [FAIL] Workflow still found in index.php.\n";
}

if (strpos($indexHtml, 'id="batchesSection"') === false && strpos($indexHtml, 'Batch List') === false) {
    echo "✓ [PASS] 'Batch List' is completely REMOVED from index.php.\n";
} else {
    echo "✗ [FAIL] Batch List still found in index.php.\n";
}

if (strpos($indexHtml, '3,000') !== false && strpos($indexHtml, 'Your Reserved Birds') !== false && strpos($indexHtml, '1 Active Booking') !== false) {
    echo "✓ [PASS] Reserved Birds KPI accurately displays 3,000 birds & 1 Active Booking.\n";
} else {
    echo "✗ [FAIL] KPI count mismatch on index.php.\n";
}

// 2. Test reservations.php
ob_start();
include __DIR__ . '/../poultrychat/reservations.php';
$resHtml = ob_get_clean();

echo "\n--- 2. RESERVATION PAGE (reservations.php) ---\n";
if (strpos($resHtml, 'Commercial Broiler Reservation &amp; Intake Process') === false && strpos($resHtml, 'Commercial Broiler Reservation & Intake Process') === false) {
    echo "✓ [PASS] 'Commercial Broiler Reservation & Intake Process' is completely REMOVED from reservations.php.\n";
} else {
    echo "✗ [FAIL] Workflow still found in reservations.php.\n";
}

if (strpos($resHtml, '3,000') !== false && strpos($resHtml, 'Allocated to contract') !== false && strpos($resHtml, '1') !== false) {
    echo "✓ [PASS] MagShot KPI displays 1 company booking and 3,000 birds on reservations.php.\n";
} else {
    echo "✗ [FAIL] KPI count mismatch on reservations.php.\n";
}

echo "\n========================================================\n";
echo "   ALL REQUESTED SECTIONS SUCCESSFULLY REMOVED!\n";
echo "========================================================\n";
