<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../poultrychat/database.php';

$countBefore = $conn->query("SELECT COUNT(*) FROM activities")->fetchColumn();
echo "Total activities before delete: $countBefore\n";

$delStmt = $conn->prepare("DELETE FROM activities WHERE type = 'Weight Sampling' OR item_name LIKE '%Sample Weighing%' OR item_name LIKE '%Weight Sampling%'");
$delStmt->execute();
$deleted = $delStmt->rowCount();
echo "Deleted $deleted Weight Sampling records.\n";

$countAfter = $conn->query("SELECT COUNT(*) FROM activities")->fetchColumn();
echo "Total activities after delete: $countAfter\n";

echo "\nRemaining activities by type:\n";
$stmt = $conn->query("SELECT type, COUNT(*) as cnt FROM activities GROUP BY type");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo " - {$r['type']}: {$r['cnt']}\n";
}

echo "\nAll remaining activities:\n";
$all = $conn->query("SELECT activity_id, batch_id, type, item_name, quantity, date, status FROM activities ORDER BY activity_id ASC");
foreach ($all->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "ID: {$r['activity_id']} | Type: {$r['type']} | Item: {$r['item_name']} | Qty: {$r['quantity']} | Date: {$r['date']} | Status: {$r['status']}\n";
}
