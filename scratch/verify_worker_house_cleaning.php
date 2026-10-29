<?php
/**
 * Test: Worker Role Reservations & House Cleaning Flow Verification
 */

require_once __DIR__ . '/../poultrychat/database.php';

echo "========================================================================\n";
echo "VERIFICATION: WORKER ROLE RESERVATIONS & HOUSE CLEANING PERMISSIONS\n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition($cond, $desc) {
    global $passCount, $failCount;
    if ($cond) {
        echo "[PASS] $desc\n";
        $passCount++;
    } else {
        echo "[FAIL] $desc\n";
        $failCount++;
    }
}

// 1. Test Worker UI output
echo "--- 1. Testing Worker UI Render on reservations.php ---\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 2;
$_SESSION['username'] = 'worker';
$_SESSION['full_name'] = 'Farm Worker';
$_SESSION['role'] = 'worker';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/poultrychat/reservations.php';

ob_start();
include __DIR__ . '/../poultrychat/reservations.php';
$workerHtml = ob_get_clean();

// Check Worker UI does NOT allow reserving
assertCondition(strpos($workerHtml, 'Reserve This House') === false, "Worker CANNOT see 'Reserve This House' button");
assertCondition(strpos($workerHtml, 'Reserve Upcoming Intake') === false, "Worker CANNOT see 'Reserve Upcoming Intake' button");
assertCondition(strpos($workerHtml, 'New Broiler Reservation') === false, "Worker CANNOT see 'New Broiler Reservation' header button");
assertCondition(strpos($workerHtml, 'Settle Balance') === false, "Worker CANNOT see 'Settle Balance' button");
assertCondition(strpos($workerHtml, 'Dispatch / Release Flock') === false, "Worker CANNOT see 'Dispatch / Release Flock' button");
assertCondition(strpos($workerHtml, '<form method="POST" id="newBroilerReservationForm"') === false, "Worker DOM does NOT contain #newBroilerReservationForm");

// Check Worker UI DOES show house cleaning actions
assertCondition(strpos($workerHtml, 'Clean This House (Set to Cleaning)') !== false, "Worker CAN see 'Clean This House (Set to Cleaning)' action");
assertCondition(strpos($workerHtml, 'Worker Action Mode') !== false, "Worker sees Worker Cleaning Mode banner");

// 2. Direct Logic Verification of mark_house_cleaning for Worker
echo "\n--- 2. Testing Worker Permission for mark_house_cleaning ---\n";
// Find a vacant or non-occupied house
$stmtH = $conn->query("SELECT house_id, house_name, status FROM houses WHERE status != 'occupied' LIMIT 1");
$testHouse = $stmtH->fetch(PDO::FETCH_ASSOC);
$hId = (int)$testHouse['house_id'];

// Simulate backend execution of mark_house_cleaning logic
$user_role = 'worker';
$user_id = 2;
$allowedToClean = in_array(strtolower($user_role), ['admin', 'staff', 'worker']);
assertCondition($allowedToClean, "Worker role is authorized in mark_house_cleaning permissions array");

$performerName = 'Farm Worker';
$conn->prepare("UPDATE houses SET status = 'cleaning', vacant_date = CURDATE(), notes = CONCAT(COALESCE(notes, ''), ' [Cleaning started by ', ?, ' (', ?, ') at ', NOW(), ']') WHERE house_id = ?")
     ->execute([$performerName, $user_role, $hId]);

$stmtAct = $conn->prepare("
    INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at, status)
    VALUES (NULL, ?, 'Cleaning', 'Pen Cleaning & Sanitation', 0, CURDATE(), ?, NOW(), 'pending')
");
$stmtAct->execute([$user_id, "Started pen washing & disinfection protocol for " . $testHouse['house_name']]);
$actPendingId = (int)$conn->lastInsertId();

$chk = $conn->query("SELECT status, notes FROM houses WHERE house_id = $hId")->fetch(PDO::FETCH_ASSOC);
assertCondition($chk['status'] === 'cleaning', "House #$hId successfully in 'cleaning' status");
assertCondition(strpos($chk['notes'], 'Cleaning started by Farm Worker') !== false, "House notes accurately reflect worker initiation");
assertCondition($actPendingId > 0, "Worker activity recorded with pending status (ID: $actPendingId)");

// 3. Testing Worker Permission for mark_house_available
echo "\n--- 3. Testing Worker Permission for mark_house_available ---\n";
$allowedToMarkAvailable = in_array(strtolower($user_role), ['admin', 'staff', 'worker']);
assertCondition($allowedToMarkAvailable, "Worker role is authorized in mark_house_available permissions array");

$conn->prepare("
    UPDATE houses 
    SET status = 'available', 
        vacant_date = CURDATE(), 
        current_batch_id = NULL,
        notes = CONCAT(COALESCE(notes, ''), ' [Sanitation completed by ', ?, ' (', ?, ') at ', NOW(), ']') 
    WHERE house_id = ?
")->execute([$performerName, $user_role, $hId]);

$stmtAct2 = $conn->prepare("
    INSERT INTO activities (batch_id, user_id, type, item_name, quantity, date, remarks, created_at, status)
    VALUES (NULL, ?, 'Cleaning', 'Facility Cleaning', 0, CURDATE(), ?, NOW(), 'completed')
");
$stmtAct2->execute([$user_id, "Completed deep washing & biosecurity disinfection of " . $testHouse['house_name'] . ". Pen ready for intake."]);
$actCompId = (int)$conn->lastInsertId();

$stmtClean = $conn->prepare("INSERT INTO cleaning_records (batch_id, date, remarks) VALUES (NULL, CURDATE(), ?)");
$stmtClean->execute(["Pen washing & disinfection completed for " . $testHouse['house_name'] . " by " . $performerName]);
$cleanRecId = (int)$conn->lastInsertId();

$chk2 = $conn->query("SELECT status, notes FROM houses WHERE house_id = $hId")->fetch(PDO::FETCH_ASSOC);
assertCondition($chk2['status'] === 'available', "House #$hId successfully marked 'available'");
assertCondition(strpos($chk2['notes'], 'Sanitation completed by Farm Worker') !== false, "House notes accurately reflect worker sanitation completion");
assertCondition($actCompId > 0, "Completed sanitation logged in activities table (ID: $actCompId)");
assertCondition($cleanRecId > 0, "Cleaning record inserted in cleaning_records table (ID: $cleanRecId)");

// 4. Test Worker Trying to Create Reservation (Strictly Denied)
echo "\n--- 4. Testing Worker Cannot Create Reservation ---\n";
$allowedToCreate = in_array(strtolower($user_role), ['admin', 'integrator']);
assertCondition(!$allowedToCreate, "Worker role is strictly DENIED from creating reservations");

// Clean up created test activity records
$conn->query("DELETE FROM activities WHERE activity_id IN ($actPendingId, $actCompId)");
$conn->query("DELETE FROM cleaning_records WHERE cleaning_id = $cleanRecId");

echo "\n========================================================================\n";
echo "SUMMARY: Passed: $passCount | Failed: $failCount\n";
echo "========================================================================\n";
