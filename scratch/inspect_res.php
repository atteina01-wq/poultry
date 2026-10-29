<?php
require __DIR__ . '/../poultrychat/database.php';

echo "--- RESERVATIONS COLUMNS ---\n";
foreach ($conn->query("DESCRIBE reservations") as $r) {
    echo "{$r['Field']} ({$r['Type']})\n";
}

echo "\n--- HOUSES COLUMNS ---\n";
foreach ($conn->query("DESCRIBE houses") as $r) {
    echo "{$r['Field']} ({$r['Type']})\n";
}

echo "\n--- BATCHES COLUMNS ---\n";
foreach ($conn->query("DESCRIBE batches") as $r) {
    echo "{$r['Field']} ({$r['Type']})\n";
}

echo "\n--- SAMPLE ACTIVE RESERVATIONS ---\n";
$stmt = $conn->query("
    SELECT r.reservation_id, r.reservation_number, r.customer_id, c.name as customer_name,
           r.house_id, h.house_name, r.batch_id, r.quantity_birds, r.weight_kg,
           r.target_weight_per_bird, r.unit_price, r.total_amount, r.pickup_date, r.notes, r.status
    FROM reservations r
    LEFT JOIN customers c ON r.customer_id = c.customer_id
    LEFT JOIN houses h ON r.house_id = h.house_id
    ORDER BY r.reservation_id DESC LIMIT 5
");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
