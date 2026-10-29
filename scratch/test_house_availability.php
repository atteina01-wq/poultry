<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

// KPI counts matching reservations.php
$kpi_stmt = $conn->query("
    SELECT COUNT(*) as active_count, SUM(COALESCE(NULLIF(r.quantity_birds, 0), 0)) as birds 
    FROM reservations r 
    LEFT JOIN batches b ON r.batch_id = b.batch_id 
    WHERE r.status='active'
");
$kpi_data = $kpi_stmt->fetch(PDO::FETCH_ASSOC);
$kpi_active_count = (int)($kpi_data['active_count'] ?? 0);
$kpi_reserved_birds = (int)($kpi_data['birds'] ?? 0);

echo "KPI Active Count: $kpi_active_count\n";
echo "KPI Reserved Birds: $kpi_reserved_birds\n";

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
ORDER BY h.house_id ASC
";
$housesList = $conn->query($housesQuery)->fetchAll(PDO::FETCH_ASSOC);

$totalHouses = count($housesList);
$vacantCount = 0;
$partialCount = 0;
$fullCount = 0;
$totalAvailableCap = 0;
$totalOccupiedBirds = 0;

foreach ($housesList as $hl) {
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

    $totalOccupiedBirds += $activeHeadcount;
    $totalAvailableCap += $remainingCapacity;

    if ($isOccupied) {
        if ($remainingCapacity > 0) {
            $statusLabel = "Partial (" . number_format($remainingCapacity) . " avail)";
            $statusType = "partial";
            $partialCount++;
        } else {
            $statusLabel = "Growing (Full)";
            $statusType = "full";
            $fullCount++;
        }
    } elseif ($isReserved) {
        if ($remainingCapacity > 0) {
            $statusLabel = "Partial (" . number_format($remainingCapacity) . " avail)";
            $statusType = "partial";
            $partialCount++;
        } else {
            $statusLabel = "Reserved";
            $statusType = "reserved";
            $fullCount++;
        }
    } else {
        $statusLabel = "Vacant & Available";
        $statusType = "vacant";
        $vacantCount++;
    }

    $reservedBy = !empty($houseReservations[0]['customer_name']) ? $houseReservations[0]['customer_name'] : '';
    if (count($houseReservations) > 1) {
        $reservedBy .= ' (+' . (count($houseReservations) - 1) . ')';
    }

    echo "{$hl['house_name']}: Status=[$statusLabel] | Type=$statusType | Cap=$houseCap | Occupied=$activeHeadcount | Remaining=$remainingCapacity | ReservedBy=$reservedBy | Flock={$hl['active_batch_code']}\n";
}

echo "Summary: Vacant=$vacantCount | Partial=$partialCount | Full=$fullCount | Total Avail Cap=$totalAvailableCap | Total Occupied Birds=$totalOccupiedBirds\n";
