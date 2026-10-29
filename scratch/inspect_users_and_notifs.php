<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "=== USERS ===\n";
foreach ($conn->query("SELECT user_id, username, full_name, email, role, customer_id FROM users")->fetchAll(PDO::FETCH_ASSOC) as $u) {
    echo "[{$u['user_id']}] {$u['username']} ({$u['role']}) - {$u['full_name']} | CustID: {$u['customer_id']}\n";
}

echo "\n=== NOTIFICATIONS ===\n";
foreach ($conn->query("SELECT * FROM notifications ORDER BY notification_id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $n) {
    echo "[{$n['notification_id']}] User {$n['user_id']} | Type: {$n['type']} | Status: {$n['status']} | Msg: {$n['message']}\n";
}

echo "\n=== HOUSES ===\n";
foreach ($conn->query("SELECT house_id, house_name, capacity, status FROM houses")->fetchAll(PDO::FETCH_ASSOC) as $h) {
    echo "[{$h['house_id']}] {$h['house_name']} | Capacity: {$h['capacity']} | Status: {$h['status']}\n";
}
