<?php
/**
 * csrf.php - CSRF protection with Double-Submit Cookie & Database Session fallback.
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
if (file_exists(__DIR__ . '/../src/autoload.php')) {
    require_once __DIR__ . '/../src/autoload.php';
}

if (session_status() === PHP_SESSION_NONE) {
    if (class_exists('Poultry\Core\DbSessionHandler')) {
        \Poultry\Core\DbSessionHandler::register();
    }
    ini_set('session.use_strict_mode', '0');
    session_start();
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            if (!empty($_COOKIE['poultry_csrf_token'])) {
                $_SESSION['csrf_token'] = (string)$_COOKIE['poultry_csrf_token'];
            } else {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }
        if (empty($_COOKIE['poultry_csrf_token']) || $_COOKIE['poultry_csrf_token'] !== $_SESSION['csrf_token']) {
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            @setcookie('poultry_csrf_token', $_SESSION['csrf_token'], [
                'expires' => time() + 86400 * 7,
                'path' => '/',
                'domain' => '',
                'secure' => $isSecure,
                'httponly' => false,
                'samesite' => 'Lax'
            ]);
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
            $sessionToken = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
            $cookieToken = isset($_COOKIE['poultry_csrf_token']) ? (string)$_COOKIE['poultry_csrf_token'] : '';

            $valid = false;
            if ($sent !== '') {
                if ($sessionToken !== '' && hash_equals($sessionToken, $sent)) {
                    $valid = true;
                } elseif ($cookieToken !== '' && hash_equals($cookieToken, $sent)) {
                    $valid = true;
                    $_SESSION['csrf_token'] = $cookieToken;
                }
            }

            if (!$valid) {
                http_response_code(403);
                die('Invalid or missing CSRF token. Please go back, refresh the page, and try again.');
            }
        }
    }
}
