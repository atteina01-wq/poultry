<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['full_name'] = 'System Admin';
$_SESSION['username'] = 'admin';
$_SERVER['REQUEST_METHOD'] = 'GET';

function csrf_field() { return '<input type="hidden" name="csrf_token" value="mock">'; }
function e($str) { return htmlspecialchars((string)$str); }

ob_start();
try {
    include('activities_test.php');
} catch (Throwable $ex) {
    echo "ERROR: " . $ex->getMessage() . " at " . $ex->getFile() . ":" . $ex->getLine();
}
$out = ob_get_clean();
file_put_contents('scratch_output.html', $out);
echo "Wrote " . strlen($out) . " bytes to scratch_output.html";
