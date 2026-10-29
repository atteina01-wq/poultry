<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/poultrychat/reservations.php';

session_start();

echo "=== TESTING INTEGRATOR RENDER ===\n";
$_SESSION['user_id'] = 8;
$_SESSION['role'] = 'integrator';
$_SESSION['username'] = 'magshot';
$_SESSION['full_name'] = 'MagShot';
$_SESSION['customer_id'] = 11;

ob_start();
include __DIR__ . '/../poultrychat/reservations.php';
$integratorHtml = ob_get_clean();

echo "Integrator HTML Length: " . strlen($integratorHtml) . "\n";
echo "Has 'Double booking of pens is strictly prohibited': " . (strpos($integratorHtml, 'Double booking of pens is strictly prohibited') !== false ? "YES" : "NO") . "\n";
echo "Has 'Pen Pre-Reserved': " . (strpos($integratorHtml, 'Pen Pre-Reserved') !== false ? "YES" : "NO") . "\n";
echo "Has 'Pending Flock Intake': " . (strpos($integratorHtml, 'Pending Flock Intake') !== false ? "YES" : "NO") . "\n";
echo "Has 'RES-20261013-004' (duplicate): " . (strpos($integratorHtml, 'RES-20261013-004') !== false ? "YES" : "NO (Cleaned!)") . "\n";
echo "Has 'RES-20261013-003': " . (strpos($integratorHtml, 'RES-20261013-003') !== false ? "YES" : "NO") . "\n";

echo "\n=== TESTING ADMIN RENDER ===\n";
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';
$_SESSION['full_name'] = 'System Administrator';
unset($_SESSION['customer_id']);

ob_start();
include __DIR__ . '/../poultrychat/reservations.php';
$adminHtml = ob_get_clean();

echo "Admin HTML Length: " . strlen($adminHtml) . "\n";
echo "Admin rendered successfully: " . (strlen($adminHtml) > 5000 ? "YES" : "NO") . "\n";
