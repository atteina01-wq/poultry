<?php
// Scratch script: verify_worker_render.php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/poultrychat/reservations.php';
$_SERVER['SCRIPT_NAME'] = '/poultrychat/reservations.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'worker';
$_SESSION['full_name'] = 'Worker Staff';

ob_start();
include __DIR__ . '/../poultrychat/reservations.php';
$html = ob_get_clean();

$hasSettleModal = (strpos($html, 'id="settleResModal') !== false);
$hasMarkCompleteBtn = (strpos($html, 'Mark Cleaning Complete (Available)') !== false);
$hasCleanHouseBtn = (strpos($html, 'Clean This House (Set to Cleaning)') !== false);
$hasNewResBtn = (strpos($html, 'New Broiler Reservation') !== false);

echo "Worker Settle Modal present: " . ($hasSettleModal ? "YES" : "NO") . "\n";
echo "Worker Mark Cleaning Complete Button present: " . ($hasMarkCompleteBtn ? "YES" : "NO") . "\n";
echo "Worker Clean House Button present: " . ($hasCleanHouseBtn ? "YES" : "NO") . "\n";
echo "Worker New Reservation Button present: " . ($hasNewResBtn ? "YES" : "NO") . "\n";
