<?php
$lines = file('c:/xampp/htdocs/poultry/poultrychat/reservations.php');
$actions = [
    'approve_reservation',
    'record_delivery_intake',
    'verify_receipt_and_activate',
    'cancel_reservation'
];

foreach ($actions as $act) {
    echo "=== ACTION: $act ===\n";
    foreach ($lines as $idx => $line) {
        if (strpos($line, "['$act']") !== false || strpos($line, "[\"$act\"]") !== false) {
            echo "Line " . ($idx + 1) . ": " . trim($line) . "\n";
            for ($i = $idx; $i < min(count($lines), $idx + 45); $i++) {
                echo ($i + 1) . ": " . $lines[$i];
            }
            break;
        }
    }
    echo "\n";
}
