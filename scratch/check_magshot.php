<?php
require_once __DIR__ . '/../poultrychat/database.php';
$users = $conn->query("SELECT user_id, username, full_name, role, customer_id FROM users WHERE username LIKE '%mag%' OR full_name LIKE '%mag%'")->fetchAll(PDO::FETCH_ASSOC);
echo "=== USERS ===\n";
print_r($users);

echo "=== RESERVATIONS ===\n";
$res = $conn->query("SELECT r.reservation_id, r.reservation_number, r.customer_id, r.created_by, r.house_id, r.quantity_birds, r.status, r.workflow_stage, c.name as customer_name FROM reservations r LEFT JOIN customers c ON r.customer_id = c.customer_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($res as $r) {
    echo "Res #{$r['reservation_id']} ({$r['reservation_number']}): CustID={$r['customer_id']} ({$r['customer_name']}), CreatedBy={$r['created_by']}, House={$r['house_id']}, Qty={$r['quantity_birds']}, Status={$r['status']}, Stage={$r['workflow_stage']}\n";
}
