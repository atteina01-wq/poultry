<?php
$lines = file(__DIR__ . '/../poultrychat/reservations.php');
for ($i = 3614; $i <= 3640; $i++) {
    echo "$i: " . json_encode($lines[$i]) . "\n";
}
