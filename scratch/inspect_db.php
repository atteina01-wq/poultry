<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "--- NOTIFICATIONS COLUMNS ---\n";
foreach ($conn->query("DESCRIBE notifications") as $col) {
    echo "{$col['Field']} ({$col['Type']}) Null:{$col['Null']} Key:{$col['Key']} Default:{$col['Default']}\n";
}

echo "\n--- USERS SAMPLE ---\n";
foreach ($conn->query("SELECT user_id, username, full_name, role, customer_id, address FROM users LIMIT 10") as $u) {
    echo "{$u['user_id']} | {$u['username']} | {$u['role']} | cust_id:{$u['customer_id']} | {$u['address']}\n";
}

echo "\n--- CUSTOMERS SAMPLE ---\n";
foreach ($conn->query("SELECT customer_id, name, customer_code, address FROM customers LIMIT 10") as $c) {
    echo "{$c['customer_id']} | {$c['name']} | {$c['customer_code']} | {$c['address']}\n";
}

echo "\n--- HOUSES ---\n";
foreach ($conn->query("SELECT house_id, house_name, capacity, status, current_batch_id FROM houses") as $h) {
    echo "{$h['house_id']} | {$h['house_name']} | cap:{$h['capacity']} | status:{$h['status']} | batch:{$h['current_batch_id']}\n";
}

echo "\n--- ACTIVE RESERVATIONS ---\n";
foreach ($conn->query("SELECT reservation_id, reservation_number, customer_id, house_id, batch_id, quantity_birds, weight_kg, target_weight_per_bird, pickup_date, total_amount, advance_payment, is_dispatched FROM reservations WHERE status='active'") as $r) {
    echo "#{$r['reservation_number']} (ID:{$r['reservation_id']}) | Cust:{$r['customer_id']} | House:{$r['house_id']} | Batch:{$r['batch_id']} | Qty:{$r['quantity_birds']} | TargetWt:{$r['target_weight_per_bird']} | Date:{$r['pickup_date']} | Tot:{$r['total_amount']} | Adv:{$r['advance_payment']} | Dispatched:{$r['is_dispatched']}\n";
}
