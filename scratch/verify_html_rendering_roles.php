<?php
// Scratch script: verify_html_rendering_roles.php

function renderPageForRole($role, $userId, $customerId = 0) {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SESSION = [
        'user_id' => $userId,
        'role' => $role,
        'full_name' => ucfirst($role) . ' User',
        'customer_id' => $customerId
    ];

    ob_start();
    // Include file
    include __DIR__ . '/../poultrychat/reservations.php';
    return ob_get_clean();
}

echo "=== TESTING ADMIN RENDER ===\n";
$adminHtml = renderPageForRole('admin', 1);
$adminHasSettleModal = (strpos($adminHtml, 'id="settleResModal') !== false);
$adminHasRecordSettlementBtn = (strpos($adminHtml, 'Record Manual Settlement') !== false);
$adminHasMarkCompleteBtn = (strpos($adminHtml, 'Mark Cleaning Complete (Available)') !== false);
$adminHasSetCleaningBtn = (strpos($adminHtml, 'Set House to Cleaning Mode') !== false);
$adminHasWorkerRequiredNotice = (strpos($adminHtml, 'Worker completion required') !== false);

echo "Admin has Settle Modal: " . ($adminHasSettleModal ? "FAIL (Should be false)" : "PASS (No modal)") . "\n";
echo "Admin has Record Settlement Button: " . ($adminHasRecordSettlementBtn ? "FAIL (Should be false)" : "PASS (No button)") . "\n";
echo "Admin has Mark Cleaning Complete Button: " . ($adminHasMarkCompleteBtn ? "FAIL (Should be false)" : "PASS (No button)") . "\n";
echo "Admin has Set Cleaning Mode Button: " . ($adminHasSetCleaningBtn ? "PASS (Present)" : "FAIL (Should be present)") . "\n";
echo "Admin sees Worker Required Notice: " . ($adminHasWorkerRequiredNotice ? "PASS (Present)" : "FAIL (Should be present)") . "\n";

echo "\n=== TESTING WORKER RENDER ===\n";
$workerHtml = renderPageForRole('worker', 2);
$workerHasSettleModal = (strpos($workerHtml, 'id="settleResModal') !== false);
$workerHasMarkCompleteBtn = (strpos($workerHtml, 'Mark Cleaning Complete (Available)') !== false);
$workerHasReservationBtn = (strpos($workerHtml, 'New Broiler Reservation') !== false);

echo "Worker has Settle Modal: " . ($workerHasSettleModal ? "FAIL (Should be false)" : "PASS (No modal)") . "\n";
echo "Worker has Mark Cleaning Complete Button: " . ($workerHasMarkCompleteBtn ? "PASS (Present)" : "FAIL (Should be present)") . "\n";
echo "Worker has New Reservation Button: " . ($workerHasReservationBtn ? "FAIL (Should be false)" : "PASS (No button)") . "\n";

echo "\n=== TESTING INTEGRATOR RENDER ===\n";
$integratorHtml = renderPageForRole('integrator', 3, 1);
$integratorHasSettleModal = (strpos($integratorHtml, 'id="settleResModal') !== false);
$integratorHasPickUpModal = (strpos($integratorHtml, 'id="integratorPickupModal') !== false);

echo "Integrator has Settle Modal: " . ($integratorHasSettleModal ? "PASS (Present for integrator)" : "INFO (Only rendered if unpaid reservations exist for this integrator)") . "\n";
echo "Integrator has Pick Up Modal: " . ($integratorHasPickUpModal ? "PASS (Present for integrator)" : "INFO") . "\n";

echo "\n=== HTML RENDER CHECKS COMPLETE ===\n";
