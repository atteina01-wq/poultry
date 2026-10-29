<?php
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

echo "=== HOUSES AND THEIR ACTIVE BATCHES ===\n";
$houses = $conn->query("
    SELECT h.house_id, h.house_name, h.capacity, h.status as house_status, h.current_batch_id,
           b.batch_code, b.status as batch_status, b.arrival_date, b.breed, b.current_quantity
    FROM houses h
    LEFT JOIN batches b ON h.current_batch_id = b.batch_id
    ORDER BY h.house_id ASC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($houses as $h) {
    echo "House {$h['house_id']}: {$h['house_name']} (Cap: {$h['capacity']}, Status: {$h['house_status']}) => Batch: {$h['batch_code']} (Status: {$h['batch_status']}, Qty: {$h['current_quantity']})\n";
}
