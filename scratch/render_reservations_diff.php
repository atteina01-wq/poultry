<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = 'reservations.php';
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

session_start();
$_SESSION['user_id'] = 12; // Magnolia
$_SESSION['role'] = 'integrator';
$_SESSION['customer_id'] = 13;

ob_start();
chdir('c:/xampp/htdocs/poultry/poultrychat');
include 'c:/xampp/htdocs/poultry/poultrychat/reservations.php';
$integratorHTML = ob_get_clean();

// Search for House 1 or 0.12 or 0.04 or 0.4 in the HTML
echo "=== SEARCH IN INTEGRATOR HTML ===\n";
preg_match_all('/House 1.*?<\/div>\s*<\/div>\s*<\/div>/s', $integratorHTML, $matches);
foreach ($matches[0] as $m) {
    echo strip_tags($m) . "\n---\n";
}

echo "\n=== OCCURRENCES OF 0.12 in Integrator HTML: ===\n";
preg_match_all('/.{0,40}0\.12.{0,40}/', $integratorHTML, $m012);
print_r($m012[0]);

echo "\n=== OCCURRENCES OF 0.04 in Integrator HTML: ===\n";
preg_match_all('/.{0,40}0\.04.{0,40}/', $integratorHTML, $m004);
print_r($m004[0]);

echo "\n=== OCCURRENCES OF 0.4 in Integrator HTML: ===\n";
preg_match_all('/.{0,40}0\.4[0-9]?.{0,40}/', $integratorHTML, $m04);
print_r($m04[0]);
