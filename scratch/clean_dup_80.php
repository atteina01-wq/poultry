<?php
require_once __DIR__ . '/../poultrychat/database.php';

// Verify reservation 80
$stmt = $conn->prepare("SELECT * FROM reservations WHERE reservation_id = 80");
$stmt->execute();
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if ($r) {
    echo "Found duplicate reservation #80 ({$r['reservation_number']}). Cleaning up...\n";
    $conn->prepare("DELETE FROM audit_logs WHERE table_name = 'reservations' AND record_id = 80")->execute();
    $conn->prepare("DELETE FROM reservations WHERE reservation_id = 80")->execute();
    echo "Reservation #80 cleaned up successfully!\n";
} else {
    echo "Reservation #80 already cleaned or not found.\n";
}
