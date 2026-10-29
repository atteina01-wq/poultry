<?php
$_SESSION = [
    'user_id' => 2,
    'role' => 'staff',
    'full_name' => 'Farm Worker',
    'username' => 'worker'
];
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
try {
    include('activities.php');
} catch (Throwable $e) {
    echo "CAUGHT EXCEPTION: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();
echo "Length of output: " . strlen($output) . "\n";
echo "Last 100 characters of output:\n";
echo substr($output, -100);
