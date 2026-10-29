<?php
require_once __DIR__ . '/../poultrychat/database.php';
require_once __DIR__ . '/../poultrychat/helpers.php';

echo "=== 1. CHECK BATCHES & HOUSES IN DB ===\n";
$b1 = $conn->query("SELECT * FROM batches WHERE house_pen = 'House 1' AND status = 'active'")->fetch(PDO::FETCH_ASSOC);
echo "House 1 Active Batch: ID={$b1['batch_id']}, Code={$b1['batch_code']}, Arr={$b1['arrival_date']}, CurWeight={$b1['current_weight_kg']}, TgtWeight={$b1['target_weight_kg']}\n";

$h1 = $conn->query("SELECT * FROM houses WHERE house_name = 'House 1'")->fetch(PDO::FETCH_ASSOC);
echo "House 1 Record: ID={$h1['house_id']}, Status={$h1['status']}, CurBatchID={$h1['current_batch_id']}\n";

echo "\n=== 2. ADMIN VIEW SIMULATION ===\n";
// Admin viewing House 1 in Poultry House & Pen Availability Board
$agreedTargetWeight = 2.20;
$agreedTargetDate = null;
$readinessAdmin = check_release_readiness($conn, $h1['current_batch_id'], $agreedTargetWeight, $agreedTargetDate);
echo "Admin House 1 Readiness:\n";
echo "  Current Sample Weight: " . number_format($readinessAdmin['current_weight'], 2) . " kg\n";
echo "  Agreed Target Weight: " . number_format($readinessAdmin['target_weight'], 2) . " kg\n";
echo "  Diff needed: " . number_format($readinessAdmin['weight_diff'], 2) . " kg\n";
echo "  Message: " . $readinessAdmin['message'] . "\n";

echo "\n=== 3. INTEGRATOR VIEW SIMULATION (Magnolia, Customer 13, User 27) ===\n";
$integratorCustomerId = 13;
$user_id = 27;

// Fetch reservations for Magnolia
$sql = "
    SELECT r.*, c.name as customer_name, c.customer_code, c.phone as customer_phone,
           h.house_name, h.capacity as house_capacity,
           COALESCE(r.batch_id, h.current_batch_id) as effective_batch_id,
           COALESCE(b.batch_code, hb.batch_code) as batch_code, 
           COALESCE(b.current_quantity, hb.current_quantity, r.quantity_birds) as display_quantity, 
           COALESCE(b.breed, hb.breed, 'Cobb 500') as breed,
           COALESCE(b.arrival_date, hb.arrival_date) as arrival_date,
           COALESCE(b.current_weight_kg, hb.current_weight_kg, 0.04) as batch_current_weight,
           COALESCE(b.target_weight_kg, hb.target_weight_kg, 2.20) as batch_target_weight,
           COALESCE(b.chick_unit_cost, hb.chick_unit_cost, 38.00) as chick_cost,
           u.full_name as created_by_name
    FROM reservations r
    JOIN customers c ON r.customer_id = c.customer_id
    LEFT JOIN houses h ON r.house_id = h.house_id
    LEFT JOIN batches b ON r.batch_id = b.batch_id
    LEFT JOIN batches hb ON h.current_batch_id = hb.batch_id
    LEFT JOIN users u ON r.created_by = u.user_id
    WHERE r.customer_id = 13 OR r.created_by = 27
    ORDER BY r.reservation_id DESC
";
$integratorRes = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "Integrator Reservations Count: " . count($integratorRes) . "\n";
foreach ($integratorRes as $idx => $r) {
    $targetWeightBird = !empty($r['target_weight_per_bird']) ? (float)$r['target_weight_per_bird'] : 2.20;
    $effectiveBId = !empty($r['effective_batch_id']) ? (int)$r['effective_batch_id'] : (!empty($r['batch_id']) ? (int)$r['batch_id'] : 0);
    $readiness = !empty($effectiveBId) ? check_release_readiness($conn, $effectiveBId, $targetWeightBird, $r['pickup_date'] ?? null) : null;

    echo "Row #" . ($idx + 1) . ": Res ID=" . $r['reservation_id'] . " | Number=" . $r['reservation_number'] . " | House=" . $r['house_name'] . " | Batch=" . $r['batch_code'] . "\n";
    echo "  Reserved Birds: " . number_format($r['display_quantity']) . " | Target: " . number_format($targetWeightBird, 2) . " kg/bird\n";
    if ($readiness) {
        echo "  Readiness Display: Growing (" . number_format($readiness['current_weight'], 2) . " / " . number_format($readiness['target_weight'], 2) . " kg)\n";
    }
}

// Integrator House Board Check for House 1
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
    WHERE h.house_name = 'House 1'
";
$hl = $conn->query($housesQuery)->fetch(PDO::FETCH_ASSOC);
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
$stmtHouseRes->execute([(int)$hl['house_id'], (int)$hl['active_batch_id'], (int)$hl['active_batch_id']]);
$houseReservations = $stmtHouseRes->fetchAll(PDO::FETCH_ASSOC);

$firstRes = $houseReservations[0] ?? null;
$agreedTargetWeight = !empty($firstRes['target_weight_per_bird']) 
    ? (float)$firstRes['target_weight_per_bird'] 
    : (!empty($hl['active_target_weight']) ? (float)$hl['active_target_weight'] : 1.80);

$agreedTargetDate = !empty($firstRes['pickup_date']) 
    ? $firstRes['pickup_date'] 
    : (!empty($hl['active_harvest_date']) ? $hl['active_harvest_date'] : null);

$readinessIntHouse = check_release_readiness($conn, $hl['active_batch_id'], $agreedTargetWeight, $agreedTargetDate);

echo "\n=== 4. INTEGRATOR HOUSE 1 BOARD CARD ===\n";
echo "House 1 Badge: " . ($readinessIntHouse['ready'] ? 'Ready for Release' : 'Growing (Full)') . "\n";
echo "Active Flock: " . $hl['active_batch_code'] . "\n";
echo "Occupied Birds: " . number_format($hl['active_headcount']) . " heads\n";
echo "Sample Weight Card: " . number_format($readinessIntHouse['current_weight'], 2) . " / " . number_format($readinessIntHouse['target_weight'], 2) . " kg\n";
echo "Sample Text: Current sample: " . number_format($readinessIntHouse['current_weight'], 2) . " kg (" . round(($readinessIntHouse['current_weight'] / $readinessIntHouse['target_weight']) * 100) . "%). Needs " . number_format(max(0, $readinessIntHouse['target_weight'] - $readinessIntHouse['current_weight']), 2) . " kg more before release.\n";
