<?php
require_once __DIR__ . '/../poultrychat/database.php';

$housesQuery = "
SELECT 
    h.house_id,
    h.house_name,
    h.capacity,
    b.batch_id as active_batch_id,
    b.batch_code as active_batch_code,
    b.initial_quantity,
    b.current_quantity as active_headcount
FROM houses h
LEFT JOIN batches b ON (
    (h.current_batch_id IS NOT NULL AND b.batch_id = h.current_batch_id)
    OR (h.current_batch_id IS NULL AND b.status = 'active' AND b.house_pen = h.house_name)
)
ORDER BY h.house_id ASC
";
$stmt = $conn->query($housesQuery);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "House: {$row['house_name']} | Batch: {$row['active_batch_code']} | Init: {$row['initial_quantity']} | Current (Alive): {$row['active_headcount']}\n";
}
