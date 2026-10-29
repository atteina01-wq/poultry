<?php
$file = __DIR__ . '/../poultrychat/reservations.php';
$output = [];
$return_var = 0;
exec("php -l " . escapeshellarg($file), $output, $return_var);
echo "LINT OUTPUT:\n" . implode("\n", $output) . "\n";
echo "STATUS: " . ($return_var === 0 ? "SUCCESS" : "ERROR") . "\n";
