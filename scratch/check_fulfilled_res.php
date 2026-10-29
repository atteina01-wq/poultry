<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

echo "=== CHECKING FULFILLED RESERVATIONS ===\n\n";

$fulfilled = $conn->query("
    SELECT reservation_id, reservation_number, batch_id, house_id, status, total_amount, advance_payment, 
           (total_amount - advance_payment) as remaining_balance 
    FROM reservations 
    WHERE status = 'fulfilled'
")->fetchAll(PDO::FETCH_ASSOC);

print_r($fulfilled);

echo "\n=== ALL RESERVATIONS STATUSES ===\n";
$allRes = $conn->query("
    SELECT reservation_id, reservation_number, status, total_amount, advance_payment, 
           (total_amount - advance_payment) as balance 
    FROM reservations 
    ORDER BY reservation_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

print_r($allRes);
