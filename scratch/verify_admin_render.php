<?php
// Scratch script: verify_admin_render.php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/poultrychat/reservations.php';
$_SERVER['SCRIPT_NAME'] = '/poultrychat/reservations.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['full_name'] = 'Admin Owner';

ob_start();
include __DIR__ . '/../poultrychat/reservations.php';
$html = ob_get_clean();

$hasSettleModal = (strpos($html, 'id="settleResModal') !== false);
$hasRecordSettlementBtn = (strpos($html, 'Record Manual Settlement') !== false);
$hasSettleSubmitBtn = (strpos($html, 'Confirm & Settle Balance') !== false);
$hasMarkCompleteBtn = (strpos($html, 'Mark Cleaning Complete (Available)') !== false);
$hasSetCleaningBtn = (strpos($html, 'Set House to Cleaning Mode') !== false);
$hasWorkerNotice = (strpos($html, 'Worker completion required') !== false);

echo "Admin Settle Modal present: " . ($hasSettleModal ? "YES" : "NO") . "\n";
echo "Admin Record Settlement Button present: " . ($hasRecordSettlementBtn ? "YES" : "NO") . "\n";
echo "Admin Settle Submit Button present: " . ($hasSettleSubmitBtn ? "YES" : "NO") . "\n";
echo "Admin Mark Cleaning Complete Button present: " . ($hasMarkCompleteBtn ? "YES" : "NO") . "\n";
echo "Admin Set House to Cleaning Mode Button present: " . ($hasSetCleaningBtn ? "YES" : "NO") . "\n";
echo "Admin Worker Completion Notice present: " . ($hasWorkerNotice ? "YES" : "NO") . "\n";
