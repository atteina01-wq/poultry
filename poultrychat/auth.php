<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

$current_script = basename($_SERVER['PHP_SELF'] ?? '');
$public_pages = ['index.php', 'login.php', 'integrator_login.php', 'logout.php'];

// Check if user is logged in for protected pages
if (!isset($_SESSION['user_id']) && !in_array($current_script, $public_pages)) {
    if ($current_script === 'reservations.php') {
        header('Location: index.php?auth=register');
    } else {
        header('Location: index.php?auth=login');
    }
    exit();
}
?>
