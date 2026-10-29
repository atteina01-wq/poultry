<?php
require_once __DIR__ . '/../poultrychat/database.php';
session_start();
$_SESSION['user_id'] = 8;
$_SESSION['role'] = 'integrator';
$_SESSION['username'] = 'magshot';
$_SESSION['full_name'] = 'MagShot';

ob_start();
include __DIR__ . '/../poultrychat/header.php';
$html = ob_get_clean();
echo "Header rendered successfully! Length: " . strlen($html) . "\n";
