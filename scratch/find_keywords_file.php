<?php
$lines = file(__DIR__ . '/../poultrychat/reservations.php');

$out = "Searching reservations.php:\n\n";

$keywords = ['auto_settle', 'fulfillModal', 'dispatch', 'settleModal', 'integrator_confirm_pickup', 'settle_reservation_balance', 'fulfill_reservation', 'Ready to Dispatch'];

foreach ($lines as $num => $line) {
    foreach ($keywords as $kw) {
        if (stripos($line, $kw) !== false) {
            $out .= sprintf("Line %4d [%s]: %s\n", $num + 1, $kw, trim($line));
            break;
        }
    }
}

file_put_contents(__DIR__ . '/keywords_output.txt', $out);
echo "Written " . strlen($out) . " bytes\n";
