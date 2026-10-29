<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';
require_once 'c:/xampp/htdocs/poultry/poultrychat/helpers.php';

function check_house1_display($role, $user_id, $customer_id) {
    global $conn;
    $isIntegrator = (strtolower($role) === 'integrator');
    $integratorCustomerId = $customer_id;

    $housesQuery = "
    SELECT 
        h.*,
        b.batch_id as active_batch_id,
        b.batch_code as active_batch_code,
        b.current_quantity as active_headcount,
        b.arrival_date as active_arrival_date,
        b.expected_harvest_date as active_harvest_date,
        b.target_weight_kg as active_target_weight,
        b.current_weight_kg as active_current_weight,
        b.supplier as active_integrator
    FROM houses h
    LEFT JOIN batches b ON (
        (h.current_batch_id IS NOT NULL AND b.batch_id = h.current_batch_id)
        OR (h.current_batch_id IS NULL AND b.status = 'active' AND b.house_pen = h.house_name)
    )
    WHERE h.house_id = 1
    ";
    $hl = $conn->query($housesQuery)->fetch(PDO::FETCH_ASSOC);

    $isOccupied = !empty($hl['active_batch_id']) && (int)$hl['active_headcount'] > 0;
    $activeBId = (int)($hl['active_batch_id'] ?? 0);
    $hId = (int)($hl['house_id'] ?? 0);

    $stmtHouseRes = $conn->prepare("
        SELECT DISTINCT r.*, c.name as customer_name, c.customer_code, c.phone as customer_phone
        FROM reservations r
        JOIN customers c ON r.customer_id = c.customer_id
        WHERE r.status = 'active'
          AND (
              (r.house_id = ?)
              OR (? > 0 AND r.batch_id = ?)
          )
        ORDER BY r.quantity_birds DESC
    ");
    $stmtHouseRes->execute([$hId, $activeBId, $activeBId]);
    $houseReservations = $stmtHouseRes->fetchAll(PDO::FETCH_ASSOC);

    $isReserved = ($hl['status'] === 'reserved' || (!empty($houseReservations) && !$isOccupied));

    $resBirdsSum = 0;
    foreach ($houseReservations as $hResItem) {
        $resBirdsSum += (int)$hResItem['quantity_birds'];
    }
    $activeHeadcount = (int)($hl['active_headcount'] ?? 0);
    $committedBirds = max($resBirdsSum, $activeHeadcount);
    $houseCap = (int)($hl['capacity'] > 0 ? $hl['capacity'] : 5000);
    $remainingCapacity = max(0, $houseCap - $committedBirds);

    $isMyFlock = false;
    if (!empty($houseReservations)) {
        $firstRes = $houseReservations[0];
        $isMyFlock = ($isIntegrator && ((int)$firstRes['customer_id'] === $integratorCustomerId || (int)($firstRes['created_by'] ?? 0) === $user_id));
    }

    $agreedTargetWeight = !empty($houseReservations[0]['target_weight_per_bird']) 
        ? (float)$houseReservations[0]['target_weight_per_bird'] 
        : (!empty($hl['active_target_weight']) ? (float)$hl['active_target_weight'] : 1.80);

    $agreedTargetDate = !empty($houseReservations[0]['pickup_date']) 
        ? $houseReservations[0]['pickup_date'] 
        : (!empty($hl['active_harvest_date']) ? $hl['active_harvest_date'] : null);

    $readiness = $isOccupied ? check_release_readiness($conn, $hl['active_batch_id'], $agreedTargetWeight, $agreedTargetDate) : null;

    $curW = (float)($readiness['current_weight'] ?? 0);
    $tgtW = (float)($readiness['target_weight'] ?? 1.80);

    echo "Role: $role (user_id: $user_id, cust_id: $customer_id)\n";
    echo "  isMyFlock: " . ($isMyFlock ? 'YES' : 'NO') . "\n";
    echo "  House 1 Current Weight: $curW kg\n";
    echo "  House 1 Target Weight: $tgtW kg\n";
    echo "  Readiness Status: {$readiness['status_label']}\n";
    echo "  Readiness Message: {$readiness['message']}\n";
    echo "\n";
}

echo "=== SIMULATING ADMIN ===\n";
check_house1_display('admin', 1, null);

echo "=== SIMULATING INTEGRATOR MAGNOLIA (user 12, cust 13) ===\n";
check_house1_display('integrator', 12, 13);

echo "=== SIMULATING INTEGRATOR OTHER (user 4, cust 7) ===\n";
check_house1_display('integrator', 4, 7);

echo "=== SIMULATING GUEST / NO LOGIN ===\n";
check_house1_display('guest', 0, 0);
