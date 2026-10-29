<?php
$lines = file('c:/xampp/htdocs/poultry/poultrychat/reservations.php');
for ($i = 1045; $i <= 1085; $i++) {
    echo ($i + 1) . ": " . $lines[$i];
}
