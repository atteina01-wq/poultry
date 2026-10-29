<?php
$_SESSION = [
    'user_id' => 2,
    'role' => 'staff',
    'full_name' => 'Farm Worker',
    'username' => 'worker'
];
ob_start();
include('activities.php');
$output = ob_get_clean();
if (strpos($output, 'Fatal error') !== false || error_get_last()) {
    print_r(error_get_last());
} else {
    echo "No fatal errors. Output length: " . strlen($output);
}
