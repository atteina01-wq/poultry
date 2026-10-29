<?php
// Scratch script: verify_integrator_render.php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/poultrychat/reservations.php';
$_SERVER['SCRIPT_NAME'] = '/poultrychat/reservations.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 3;
$_SESSION['role'] = 'integrator';
$_SESSION['full_name'] = 'Integrator Rep';
$_SESSION['customer_id'] = 1;

ob_start();
include __DIR__ . '/../poultrychat/reservations.php';
$html = ob_get_clean();

$hasSettleModal = (strpos($html, 'id="settleResModal') !== false);
$hasIntegratorPickup = (strpos($html, 'id="integratorPickupModal') !== false);
$hasRecordSettlementBtn = (strpos($html, 'Confirm & Settle Balance') !== false);

echo "Integrator Settle Modal present: " . ($hasSettleModal ? "YES" : "NO") . "\n";
echo "Integrator Pickup Modal present: " . ($hasIntegratorPickup ? "YES" : "NO") . "\n";
echo "Integrator Settle Submit Button present: " . ($hasRecordSettlementBtn ? "YES" : "NO") . "\n";
