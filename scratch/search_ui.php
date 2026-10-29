<?php
$lines = file('c:/xampp/htdocs/poultry/poultrychat/reservations.php');
$searches = [
    'approve_reservation',
    'record_delivery_intake',
    'verify_receipt_and_activate',
    'workflow_stage',
    'modal'
];

foreach ($searches as $s) {
    echo "=== SEARCH: $s ===\n";
    $matches = 0;
    foreach ($lines as $idx => $line) {
        if (stripos($line, $s) !== false && $idx > 600) { // beyond the POST handlers
            echo "Line " . ($idx + 1) . ": " . trim(substr($line, 0, 120)) . "\n";
            $matches++;
            if ($matches >= 15) {
                echo "... more matches\n";
                break;
            }
        }
    }
    echo "\n";
}
