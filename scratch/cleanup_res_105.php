<?php
require_once __DIR__ . '/../poultrychat/database.php';

// Check Res #105
$res105 = $conn->query("SELECT * FROM reservations WHERE reservation_id = 105")->fetch(PDO::FETCH_ASSOC);
if ($res105) {
    // Delete batch 60 if it was created for res 105
    if (!empty($res105['batch_id'])) {
        $conn->prepare("DELETE FROM batches WHERE batch_id = ?")->execute([$res105['batch_id']]);
    }
    // Reset house 3 to vacant if it was assigned to batch 60
    $conn->prepare("UPDATE houses SET current_batch_id = NULL, status = 'vacant' WHERE house_id = 3")->execute();
    
    // Delete or mark fulfilled res 105
    $conn->prepare("DELETE FROM reservations WHERE reservation_id = 105")->execute();
    echo "SUCCESS: Reservation #105 removed and House 3 set to vacant.\n";
} else {
    echo "Res 105 not found.\n";
}

// Check Magnolia reservations
$rows = $conn->query("SELECT reservation_id, reservation_number, status, quantity_birds, house_id FROM reservations WHERE customer_id = 13")->fetchAll(PDO::FETCH_ASSOC);
echo "Remaining Magnolia reservations:\n";
print_r($rows);
