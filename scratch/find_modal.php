<?php
$content = file_get_contents(__DIR__ . '/../poultrychat/reservations.php');
$lines = explode("\n", $content);
foreach ($lines as $num => $line) {
    if (stripos($line, 'newBroilerReservationModal') !== false) {
        echo ($num + 1) . ": " . $line . "\n";
    }
}
