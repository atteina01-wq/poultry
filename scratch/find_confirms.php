<?php
$lines = file('c:/xampp/htdocs/poultry/poultrychat/reservations.php');
foreach ($lines as $i => $line) {
    if (strpos($line, 'confirm(') !== false) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
