<?php
$lines = file(__DIR__ . '/../poultrychat/reservations.php');
for ($i = 3575; $i <= 3585; $i++) {
    echo "$i: " . json_encode($lines[$i]) . "\n";
}
