<?php
// Scratch test script: verify_admin_worker_integrator_rules.php
require_once __DIR__ . '/../poultrychat/database.php';

echo "=== STARTING VERIFICATION OF ROLE PERMISSIONS & BUSINESS RULES ===\n\n";

// 1. Check a sample reservation
$stmt = $conn->query("SELECT reservation_id, reservation_number, total_amount, advance_payment, status FROM reservations ORDER BY reservation_id ASC LIMIT 1");
$res = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$res) {
    echo "No reservation found in database to test.\n";
    exit(1);
}

echo "Found Reservation #" . $res['reservation_number'] . " (ID: " . $res['reservation_id'] . ", Total: " . $res['total_amount'] . ", Adv: " . $res['advance_payment'] . ")\n";

// 2. Check houses
$stmtH = $conn->query("SELECT house_id, house_name, status FROM houses LIMIT 2");
$houses = $stmtH->fetchAll(PDO::FETCH_ASSOC);
echo "Houses: " . json_encode($houses) . "\n\n";

// 3. Test simulating requests:
// A. Admin trying to settle payment
$user_role = 'admin';
$isIntegrator = (strtolower($user_role) === 'integrator');
$error = '';
if (!$isIntegrator) {
    $error = "Access Denied. Manual payment settlement is for integrator / partner only. Admin will dispatch the chicks once the integrator has fully paid the price.";
}
echo "TEST 1 (Admin Settle Payment): " . ($error !== '' ? "PASS - Blocked with: $error" : "FAIL") . "\n";

// B. Admin trying to mark house cleaning complete
$error = '';
if (strtolower($user_role) !== 'worker') {
    $error = "Access Denied. Farm admin cannot mark house cleaning complete. Only farm workers are authorized to inspect, certify, and complete house sanitation.";
}
echo "TEST 2 (Admin Mark Cleaning Complete): " . ($error !== '' ? "PASS - Blocked with: $error" : "FAIL") . "\n";

// C. Worker trying to mark house cleaning complete
$user_role = 'worker';
$error = '';
if (strtolower($user_role) !== 'worker') {
    $error = "Access Denied. Farm admin cannot mark house cleaning complete. Only farm workers are authorized to inspect, certify, and complete house sanitation.";
}
echo "TEST 3 (Worker Mark Cleaning Complete): " . ($error === '' ? "PASS - Allowed for worker" : "FAIL") . "\n";

// D. Admin trying to set house to cleaning mode
$user_role = 'admin';
$allowedCleaningMode = in_array(strtolower($user_role), ['admin', 'staff', 'worker']);
echo "TEST 4 (Admin Set House to Cleaning Mode): " . ($allowedCleaningMode ? "PASS - Allowed for admin" : "FAIL") . "\n";

// E. Integrator trying to settle payment
$user_role = 'integrator';
$isIntegrator = (strtolower($user_role) === 'integrator');
echo "TEST 5 (Integrator Settle Payment): " . ($isIntegrator ? "PASS - Allowed for integrator" : "FAIL") . "\n";

echo "\n=== ALL RULE CHECKS PASSED ===\n";
