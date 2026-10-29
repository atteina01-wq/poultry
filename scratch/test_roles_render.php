<?php
function test_worker_and_admin_pages($page) {
    echo "=== TESTING {$page} ===\n";
    foreach (['admin', 'worker'] as $role) {
        $_SESSION['user_id'] = ($role === 'admin' ? 1 : 2);
        $_SESSION['role'] = $role;
        $_SESSION['username'] = $role;
        ob_start();
        include "c:/xampp/htdocs/poultry/poultrychat/{$page}";
        $html = ob_get_clean();
        
        echo "Role: [{$role}]\n";
        if ($page === 'healthrecords.php') {
            echo " - Has 'New Health Record' form: " . (strpos($html, 'New Health Record') !== false ? "YES" : "NO") . "\n";
            echo " - Has Action button in table: " . (preg_match('/<button[^>]+title="[^"]*action/i', $html) ? "YES" : "NO") . "\n";
            echo " - Has 'Admin (No Action)' or view-only: " . (strpos($html, '—') !== false ? "YES" : "NO") . "\n";
        } elseif ($page === 'activities.php') {
            echo " - Has 'Record Activity' form: " . (strpos($html, 'Record Activity') !== false ? "YES" : "NO") . "\n";
            echo " - Has 'Complete Action' button in table: " . (strpos($html, 'name=\'complete_activity\'') !== false || strpos($html, 'name="complete_activity"') !== false ? "YES" : "NO") . "\n";
            echo " - Has 'Pending Action' badge: " . (strpos($html, 'Pending Action') !== false ? "YES" : "NO") . "\n";
        }
    }
    echo "\n";
}

test_worker_and_admin_pages('healthrecords.php');
test_worker_and_admin_pages('activities.php');
