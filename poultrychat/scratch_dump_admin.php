<?php
$_SESSION = [
    'user_id' => 1,
    'role' => 'admin',
    'full_name' => 'System Admin',
    'username' => 'admin'
];
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
try {
    include('activities.php');
} catch (Throwable $e) {}
$out = ob_get_clean();
echo $out;
