<?php
require_once __DIR__ . '/../poultrychat/database.php';

header('Content-Type: text/plain');

echo "=== 1. VERIFY FULFILLED RESERVATIONS ARE FULLY PAID ===\n";
$stmt = $conn->query("
    SELECT reservation_id, reservation_number, status, total_amount, advance_payment,
           ROUND(total_amount - advance_payment, 2) as balance
    FROM reservations
    WHERE status = 'fulfilled'
");
$fulfilled = $stmt->fetchAll(PDO::FETCH_ASSOC);
$allFulfilledPaid = true;
foreach ($fulfilled as $f) {
    echo "ID: {$f['reservation_id']} | #{$f['reservation_number']} | Total: ₱{$f['total_amount']} | Advance: ₱{$f['advance_payment']} | Bal: ₱{$f['balance']}\n";
    if ((float)$f['balance'] > 0.001) {
        $allFulfilledPaid = false;
    }
}
echo "Result: " . ($allFulfilledPaid ? "PASS - All fulfilled reservations are 100% fully paid." : "FAIL") . "\n\n";

echo "=== 2. ACTIVE RESERVATIONS CHECK ===\n";
$stmtAct = $conn->query("
    SELECT reservation_id, reservation_number, status, total_amount, advance_payment,
           ROUND(total_amount - advance_payment, 2) as balance
    FROM reservations
    WHERE status = 'active'
");
$actives = $stmtAct->fetchAll(PDO::FETCH_ASSOC);
foreach ($actives as $a) {
    echo "ID: {$a['reservation_id']} | #{$a['reservation_number']} | Total: ₱{$a['total_amount']} | Advance: ₱{$a['advance_payment']} | Bal: ₱{$a['balance']}\n";
}

echo "\n=== 3. CHECK CODEBASE FOR AUTO-SETTLE IN RESERVATIONS.PHP ===\n";
$code = file_get_contents(__DIR__ . '/../poultrychat/reservations.php');
if (strpos($code, 'auto_settle_balance') !== false) {
    echo "FAIL: 'auto_settle_balance' still found in reservations.php!\n";
} else {
    echo "PASS: 'auto_settle_balance' has been completely removed from reservations.php.\n";
}

echo "\n=== 4. TEST SECURITY GATE ON UNPAID RESERVATION ===\n";
// Pick an active reservation with balance
$unpaid = null;
foreach ($actives as $a) {
    if ((float)$a['balance'] > 0.01) {
        $unpaid = $a;
        break;
    }
}
if ($unpaid) {
    echo "Testing gate with reservation #{$unpaid['reservation_number']} (Balance: ₱{$unpaid['balance']})...\n";
    $gateBlocked = false;
    $gateMsg = '';
    try {
        $stmtBal = $conn->prepare("SELECT total_amount, advance_payment, reservation_number FROM reservations WHERE reservation_id = ?");
        $stmtBal->execute([$unpaid['reservation_id']]);
        $resBalRow = $stmtBal->fetch(PDO::FETCH_ASSOC);
        if ($resBalRow) {
            $unpaidBal = round((float)$resBalRow['total_amount'] - (float)$resBalRow['advance_payment'], 2);
            if ($unpaidBal > 0.01) {
                throw new Exception("Security Gate: Cannot dispatch or pick up flock! An outstanding balance of ₱" . number_format($unpaidBal, 2) . " remains on #" . $resBalRow['reservation_number'] . ". Full payment must be manually settled first before pickup.");
            }
        }
    } catch (Exception $e) {
        $gateBlocked = true;
        $gateMsg = $e->getMessage();
    }
    echo "Gate check result: " . ($gateBlocked ? "PASS - Blocked with message: " . $gateMsg : "FAIL") . "\n";
} else {
    echo "No unpaid active reservation found.\n";
}

echo "\nALL TESTS FINISHED.\n";
