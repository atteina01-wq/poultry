<?php
require __DIR__ . '/../poultrychat/database.php';

$res = $conn->query("SELECT reservation_id, reservation_number, status, pickup_date, quantity_birds FROM reservations ORDER BY reservation_id ASC")->fetchAll(PDO::FETCH_ASSOC);

echo "Current Total Reservations: " . count($res) . "\n";
foreach ($res as $r) {
    echo "ID: {$r['reservation_id']} | #{$r['reservation_number']} | Status: {$r['status']} | Date: {$r['pickup_date']} | Qty: {$r['quantity_birds']}\n";
}
