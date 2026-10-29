<?php
function test_page($page, $role) {
    $_SESSION['user_id'] = ($role === 'admin' ? 1 : 2);
    $_SESSION['role'] = $role;
    $_SESSION['username'] = $role;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    include "c:/xampp/htdocs/poultry/poultrychat/{$page}";
    $html = ob_get_clean();
    echo "Page: {$page} | Role: {$role} | Rendered Length: " . strlen($html) . " bytes\n";
    if (strpos($html, 'Fatal error') !== false || strpos($html, 'Parse error') !== false) {
        echo "ERROR DETECTED!\n";
    }
}

test_page('healthrecords.php', 'admin');
test_page('healthrecords.php', 'worker');
test_page('feed.php', 'admin');
test_page('feed.php', 'worker');
test_page('reservations.php', 'admin');
test_page('reservations.php', 'integrator');
