<?php
require_once __DIR__ . '/../poultrychat/database.php';

echo "Columns in reservations:\n";
foreach ($conn->query("DESCRIBE reservations") as $c) {
    echo " - " . $c['Field'] . " (" . $c['Type'] . ")\n";
}

echo "\nSample reservation data:\n";
$stmt = $conn->query("SELECT reservation_id, reservation_number, customer_id, house_id, batch_id, quantity_birds, total_amount, advance_payment, status FROM reservations ORDER BY reservation_id DESC LIMIT 5");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $bal = (float)$r['total_amount'] - (float)$r['advance_payment'];
    echo "ID: {$r['reservation_id']} | #{$r['reservation_number']} | Cust: {$r['customer_id']} | Birds: {$r['quantity_birds']} | Total: {$r['total_amount']} | Adv: {$r['advance_payment']} | Bal: {$bal} | Status: {$r['status']}\n";
}
