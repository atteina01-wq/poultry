<?php
require_once __DIR__ . '/../poultrychat/database.php';

// Check reservation 79
$r79 = $conn->query("SELECT * FROM reservations WHERE reservation_id = 79")->fetch(PDO::FETCH_ASSOC);
if ($r79) {
    echo "Found duplicate reservation #79 ({$r79['reservation_number']}):\n";
    print_r($r79);
    
    // Delete audit logs for 79
    $conn->prepare("DELETE FROM audit_logs WHERE table_name = 'reservations' AND record_id = 79")->execute();
    // Delete reservation 79
    $conn->prepare("DELETE FROM reservations WHERE reservation_id = 79")->execute();
    echo "\nSuccessfully removed duplicate reservation #79 (RES-20261013-003) from database!\n";
} else {
    echo "Reservation #79 not found.\n";
}

// Check remaining reservations for Magnolia (customer 11)
$remaining = $conn->query("SELECT reservation_id, reservation_number, status, total_amount, advance_payment, actual_pickup_date FROM reservations WHERE customer_id = 11 ORDER BY reservation_id DESC")->fetchAll(PDO::FETCH_ASSOC);
echo "\nRemaining reservations for Magnolia:\n";
print_r($remaining);
