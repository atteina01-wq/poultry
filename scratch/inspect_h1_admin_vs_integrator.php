<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = 'reservations.php';
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

session_start();

// Admin
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
unset($_SESSION['customer_id']);

ob_start();
include 'c:/xampp/htdocs/poultry/poultrychat/reservations.php';
$adminHTML = ob_get_clean();

// Integrator
$_SESSION['user_id'] = 12;
$_SESSION['role'] = 'integrator';
$_SESSION['customer_id'] = 13;

ob_start();
include 'c:/xampp/htdocs/poultry/poultrychat/reservations.php';
$integratorHTML = ob_get_clean();

echo "=== ADMIN: HOUSE 1 ON BOARD ===\n";
if (preg_match('/<h6[^>]*>House 1<\/h6>.*?Record Weight Sample/s', $adminHTML, $m)) {
    echo strip_tags($m[0]) . "\n";
} elseif (preg_match('/<h6[^>]*>House 1<\/h6>.*?<\/div>\s*<\/div>\s*<\/div>/s', $adminHTML, $m)) {
    echo strip_tags($m[0]) . "\n";
}

echo "\n=== INTEGRATOR: HOUSE 1 ON BOARD ===\n";
if (preg_match('/<h6[^>]*>House 1<\/h6>.*?<\/div>\s*<\/div>\s*<\/div>/s', $integratorHTML, $m)) {
    echo strip_tags($m[0]) . "\n";
}

echo "\n=== ADMIN: TABLE ROWS FOR RES 104 OR HOUSE 1 ===\n";
if (preg_match('/RES-20261121-001.*?<\/tr>/s', $adminHTML, $m)) {
    echo strip_tags($m[0]) . "\n";
}

echo "\n=== INTEGRATOR: TABLE ROWS FOR RES 104 OR HOUSE 1 ===\n";
if (preg_match('/RES-20261121-001.*?<\/tr>/s', $integratorHTML, $m)) {
    echo strip_tags($m[0]) . "\n";
}
