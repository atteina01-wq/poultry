<?php
$_SERVER['PHP_SELF'] = 'reservations.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 12;
$_SESSION['username'] = 'Magnolia Chicks';
$_SESSION['role'] = 'integrator';
$_SESSION['customer_id'] = 13;

ob_start();
// Change directory to poultrychat so relative includes work
chdir('c:/xampp/htdocs/poultry/poultrychat');
include 'c:/xampp/htdocs/poultry/poultrychat/reservations.php';
$html = ob_get_clean();

echo "HTML length: " . strlen($html) . "\n";

// Search for 0.12 in the HTML
echo "--- SEARCH FOR 0.12 IN INTEGRATOR PAGE ---\n";
$pos12 = strpos($html, '0.12');
while ($pos12 !== false) {
    echo "--- 0.12 MATCH AT $pos12 ---\n";
    echo strip_tags(substr($html, max(0, $pos12 - 150), 350)) . "\n\n";
    $pos12 = strpos($html, '0.12', $pos12 + 1);
}

// Search for 0.04 in the HTML
echo "--- SEARCH FOR 0.04 IN INTEGRATOR PAGE ---\n";
$pos04 = strpos($html, '0.04');
while ($pos04 !== false) {
    echo "--- 0.04 MATCH AT $pos04 ---\n";
    echo strip_tags(substr($html, max(0, $pos04 - 150), 350)) . "\n\n";
    $pos04 = strpos($html, '0.04', $pos04 + 1);
}

// Search for "House 1" in the HTML
echo "--- SEARCH FOR 'House 1' IN INTEGRATOR PAGE ---\n";
$posH1 = strpos($html, 'House 1');
while ($posH1 !== false) {
    echo "--- House 1 MATCH AT $posH1 ---\n";
    echo strip_tags(substr($html, max(0, $posH1 - 100), 500)) . "\n\n";
    $posH1 = strpos($html, 'House 1', $posH1 + 1);
}
