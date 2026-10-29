<?php
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
include "c:/xampp/htdocs/poultry/poultrychat/activities.php";
$html = ob_get_clean();

echo "Activities Page Rendered: " . strlen($html) . " bytes\n";
echo "Has 'BroilerFeeds': " . (strpos($html, 'BroilerFeeds') !== false ? 'YES' : 'NO') . "\n";
echo "Has 'Anti-plema': " . (strpos($html, 'Anti-plema') !== false ? 'YES' : 'NO') . "\n";
echo "Has 'Disinfectant Spray': " . (strpos($html, 'Disinfectant Spray') !== false ? 'YES' : 'NO') . "\n";
echo "Has 'BR-20261012-319 - House 5': " . (strpos($html, 'BR-20261012-319 - House 5') !== false ? 'YES' : 'NO') . "\n";
