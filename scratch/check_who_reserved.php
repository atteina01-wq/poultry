<?php
require_once __DIR__ . '/../poultrychat/database.php';
$stmt = $conn->query("
    SELECT r.reservation_id, r.reservation_number, r.customer_id, c.name as customer_name, r.batch_id, r.house_id, r.quantity_birds, r.status
    FROM reservations r
    JOIN customers c ON r.customer_id = c.customer_id
    WHERE r.status = 'active'
");
echo "ACTIVE RESERVATIONS:\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "Res #{$r['reservation_id']} ({$r['reservation_number']}): Customer = {$r['customer_name']} | BatchID = {$r['batch_id']} | HouseID = {$r['house_id']} | Birds = {$r['quantity_birds']} | Status = {$r['status']}\n";
}
