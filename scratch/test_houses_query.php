<?php
require_once __DIR__ . '/../poultrychat/database.php';

$housesQuery = "
SELECT 
    h.house_id,
    h.house_name,
    h.capacity,
    h.status,
    h.notes,
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

echo "Total houses returned: " . count($housesList) . "\n";
foreach ($housesList as $hl) {
    echo "ID: {$hl['house_id']} - {$hl['house_name']} (Status: {$hl['status']}, ActiveBatch: {$hl['active_batch_id']})\n";
}
