<?php
require_once __DIR__ . '/../src/autoload.php';
use Poultry\Core\Database;
$conn = Database::getInstance()->getConnection();

function testRoleRender($roleName, $conn) {
    echo "========================================================\n";
    echo "TESTING HOUSE 6 CARD FOR ROLE: $roleName\n";
    echo "========================================================\n";

    // Simulate session
    $user_role = $roleName;
    $isIntegrator = (strtolower($user_role) === 'integrator');
    $integratorCustomerId = 13; // Magnolia
    $user_id = ($roleName === 'admin' ? 1 : ($roleName === 'worker' ? 3 : 10));

    // Fetch House 6 and its reservations
    $hl = $conn->query("SELECT * FROM houses WHERE house_id = 6")->fetch(PDO::FETCH_ASSOC);
    $isOccupied = ($hl['status'] === 'occupied');
    $hId = 6;
    $activeBId = 0;

    $stmtHouseRes = $conn->prepare("
        SELECT DISTINCT r.*, c.name as customer_name, c.customer_code, c.phone as customer_phone
        FROM reservations r
        JOIN customers c ON r.customer_id = c.customer_id
        WHERE r.status = 'active'
          AND (
              (r.house_id = ?)
              OR (? > 0 AND r.batch_id = ?)
          )
        ORDER BY r.reservation_id DESC
    ");
    $stmtHouseRes->execute([$hId, $activeBId, $activeBId]);
    $houseReservations = $stmtHouseRes->fetchAll(PDO::FETCH_ASSOC);

    $isReserved = ($hl['status'] === 'reserved' || (!empty($houseReservations) && !$isOccupied));
    $firstRes = !empty($houseReservations) ? $houseReservations[0] : null;
    $firstResWf = $firstRes ? ($firstRes['workflow_stage'] ?? 'pending_approval') : 'pending_approval';
    $firstResId = $firstRes ? (int)$firstRes['reservation_id'] : 0;
    $resBirdsSum = $firstRes ? (int)$firstRes['quantity_birds'] : 0;
    $houseCap = (int)$hl['capacity'];
    $remainingCapacity = max(0, $houseCap - $resBirdsSum);

    echo "House Name: " . $hl['house_name'] . "\n";
    echo "Is Reserved: " . ($isReserved ? 'YES' : 'NO') . "\n";
    echo "First Res Number: " . ($firstRes ? $firstRes['reservation_number'] : 'NONE') . "\n";
    echo "First Res Stage: " . $firstResWf . "\n";

    // Check Header Badge
    $firstWf = !empty($houseReservations) ? ($houseReservations[0]['workflow_stage'] ?? 'pending_approval') : 'pending_approval';
    if ($firstWf === 'pending_approval') {
        $badgeText = "Step 2: Under Review";
    } elseif ($firstWf === 'approved') {
        $badgeText = "Step 3: Awaiting Delivery";
    } elseif ($firstWf === 'receiving') {
        $badgeText = "Step 4: Pending Verification";
    } else {
        $badgeText = "Reserved";
    }
    echo "Card Top Badge: [$badgeText]\n";

    // Check Card Body Subtitle
    if ($firstResWf === 'pending_approval') {
        echo "Card Subtitle: [Step 1: Reservation Submitted]\n";
    } elseif ($firstResWf === 'approved') {
        echo "Card Subtitle: [Step 2: Approved • Awaiting Delivery]\n";
    } elseif ($firstResWf === 'receiving') {
        echo "Card Subtitle: [Step 3: Chicks Arrived (Receiving)]\n";
    }

    // Check Action Button rendered for this role
    if ($firstResWf === 'pending_approval') {
        if (strtolower($user_role) === 'admin') {
            echo "Action for $roleName: [BUTTON: Review & Approve (Step 2)] -> opens #reviewReservationModal$firstResId\n";
        } elseif (in_array(strtolower($user_role), ['worker', 'staff'])) {
            echo "Action for $roleName: [INFO: Pending Step 2: Awaiting Farm Admin review & approval.]\n";
        } elseif ($isIntegrator) {
            echo "Action for $roleName: [INFO: Step 1 Submitted: Awaiting Farm Admin review & confirmation.]\n";
        }
    }
}

testRoleRender('admin', $conn);
testRoleRender('worker', $conn);
testRoleRender('integrator', $conn);
