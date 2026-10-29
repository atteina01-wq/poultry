<?php
$content = file_get_contents(__DIR__ . '/../poultrychat/reservations.php');
$lines = explode("\n", $content);
foreach ($lines as $num => $line) {
    if (stripos($line, '$message') !== false || stripos($line, '$error') !== false) {
        if ($num > 550 && $num < 750) {
            echo ($num + 1) . ": " . $line . "\n";
        }
    }
}
