<?php
$lines = file('poultrychat/reservations.php');
foreach ($lines as $i => $line) {
    if (stripos($line, 'openQuickWeighModal') !== false || stripos($line, 'quickWeighModal') !== false || stripos($line, 'birds_sampled') !== false) {
        echo ($i + 1) . ": " . trim($line) . "\n";
    }
}
