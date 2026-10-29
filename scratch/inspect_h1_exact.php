<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = 'reservations.php';
require_once 'c:/xampp/htdocs/poultry/poultrychat/database.php';

session_start();

function get_h1_text($role, $user_id, $customer_id) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['role'] = $role;
    if ($customer_id) $_SESSION['customer_id'] = $customer_id;
    else unset($_SESSION['customer_id']);

    ob_start();
    include 'c:/xampp/htdocs/poultry/poultrychat/reservations.php';
    $html = ob_get_clean();

    // Extract the card for House 1
    if (preg_match('/<!--.*?House 1.*?-->|House 1.*?Capacity: 5,000 birds.*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/s', $html, $m)) {
        return $m[0];
    }
    // Or find where "House 1" appears in availability board
    $pos = strpos($html, 'House 1');
    if ($pos !== false) {
        return substr($html, $pos, 1500);
    }
    return "Not found";
}

echo "=== ADMIN VIEW OF HOUSE 1 ===\n";
echo strip_tags(get_h1_text('admin', 1, null)) . "\n";

echo "=== INTEGRATOR (User 12, Cust 13 - Owner of Res 104) VIEW OF HOUSE 1 ===\n";
echo strip_tags(get_h1_text('integrator', 12, 13)) . "\n";

echo "=== INTEGRATOR (User 4, Cust 7) VIEW OF HOUSE 1 ===\n";
echo strip_tags(get_h1_text('integrator', 4, 7)) . "\n";
