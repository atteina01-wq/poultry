<?php
/**
 * csrf.php - Minimal CSRF protection helper.
 *
 * - Generates a per-session CSRF token if one doesn't exist.
 * - Provides csrf_token() to output the token value.
 * - Provides csrf_field() to output a hidden input.
 * - Provides csrf_verify() to validate a POST token against the session.
 *
 * Usage:
 *   session_start();
 *   include('csrf.php');
 *   // On every form: <form>...<?php echo csrf_field(); ?>...</form>
 *   // At the top of every POST handler: csrf_verify();
 */

// Ensure a session is available with secure cookie flags
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $sent = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
            if (!hash_equals(csrf_token(), $sent)) {
                http_response_code(403);
                die('Invalid or missing CSRF token. Please go back, refresh the page, and try again.');
            }
        }
    }
}