<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower($_SESSION['role'] ?? '');
if (in_array($role, ['admin', 'staff', 'worker'])) {
    header("Location: poultrychat/dashboard.php");
} else {
    header("Location: poultrychat/index.php");
}
exit();

