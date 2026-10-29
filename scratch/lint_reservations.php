<?php
$output = [];
$return_var = 0;
exec('php -l c:/xampp/htdocs/poultry/poultrychat/reservations.php 2>&1', $output, $return_var);
echo implode("\n", $output) . "\n";
echo "Exit code: " . $return_var . "\n";
