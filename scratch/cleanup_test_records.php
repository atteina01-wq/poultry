<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../poultrychat/database.php';

$del = $conn->exec("DELETE FROM activities WHERE activity_id >= 192");
echo "Deleted test activities: $del\n";

$delAudit = $conn->exec("DELETE FROM audit_logs WHERE table_name = 'activities' AND record_id >= 192");
echo "Deleted test audit logs: $delAudit\n";

echo "Current activities in DB:\n";
$stmt = $conn->query("SELECT activity_id, type, item_name, quantity, date, status FROM activities ORDER BY activity_id ASC");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "ID: {$r['activity_id']} | Type: {$r['type']} | Item: {$r['item_name']} | Qty: {$r['quantity']} | Date: {$r['date']} | Status: {$r['status']}\n";
}
