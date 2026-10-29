<?php
header('Content-Type: text/plain');
$lines = file(__DIR__ . '/../poultrychat/reservations.php');

echo "<pre>\nSearching reservations.php:\n\n";

$keywords = ['auto_settle', 'fulfillModal', 'dispatch', 'settleModal', 'integrator_confirm_pickup', 'settle_reservation_balance', 'fulfill_reservation'];

foreach ($lines as $num => $line) {
    foreach ($keywords as $kw) {
        if (stripos($line, $kw) !== false) {
            echo htmlspecialchars(sprintf("Line %4d [%s]: %s", $num + 1, $kw, trim($line))) . "\n";
            break;
        }
    }
}
echo "</pre>";
