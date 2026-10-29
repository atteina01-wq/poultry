<?php
require_once __DIR__ . '/../poultrychat/database.php';
header('Content-Type: text/plain');

echo "=== UPDATING ALL FULFILLED RESERVATIONS TO FULLY PAID ===\n\n";

$before = $conn->query("
    SELECT reservation_id, reservation_number, total_amount, advance_payment, 
           (total_amount - advance_payment) as remaining_balance 
    FROM reservations 
    WHERE status = 'fulfilled'
")->fetchAll(PDO::FETCH_ASSOC);

echo "Fulfilled reservations before update:\n";
print_r($before);

$stmt = $conn->prepare("
    UPDATE reservations 
    SET advance_payment = total_amount, updated_at = NOW() 
    WHERE status = 'fulfilled' AND (total_amount > advance_payment OR advance_payment IS NULL)
");
$stmt->execute();
$affected = $stmt->rowCount();
echo "\nUpdated $affected fulfilled reservation(s) to advance_payment = total_amount.\n\n";

$after = $conn->query("
    SELECT reservation_id, reservation_number, total_amount, advance_payment, 
           (total_amount - advance_payment) as remaining_balance 
    FROM reservations 
    WHERE status = 'fulfilled'
")->fetchAll(PDO::FETCH_ASSOC);

echo "Fulfilled reservations after update:\n";
print_r($after);
