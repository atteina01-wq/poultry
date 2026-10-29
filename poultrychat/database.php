<?php
// Register Composer Autoloader
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

$host = class_exists('Poultry\Core\Config') ? \Poultry\Core\Config::get('DB_HOST', 'localhost') : (getenv('DB_HOST') ?: 'localhost');
$port = class_exists('Poultry\Core\Config') ? \Poultry\Core\Config::get('DB_PORT', '3306') : (getenv('DB_PORT') ?: '3306');
$db_name = class_exists('Poultry\Core\Config') ? \Poultry\Core\Config::get('DB_NAME', 'poultry_db') : (getenv('DB_NAME') ?: 'poultry_db');
$username = class_exists('Poultry\Core\Config') ? \Poultry\Core\Config::get('DB_USER', 'root') : (getenv('DB_USER') ?: 'root');
$password = class_exists('Poultry\Core\Config') ? \Poultry\Core\Config::get('DB_PASS', '') : (getenv('DB_PASS') ?: '');

date_default_timezone_set('Asia/Manila');

try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    if ($host !== 'localhost' && $host !== '127.0.0.1') {
        $options[PDO::MYSQL_ATTR_SSL_CA] = true;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    // Single, direct PDO connection to the database
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4", $username, $password, $options);
    $conn->exec("SET time_zone = '+08:00'");
} catch(PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// Global CSRF protection for all POST requests.
// csrf.php auto-starts the session and csrf_verify() aborts invalid POSTs.
include('csrf.php');
csrf_verify();

// XSS helper: escape output for safe HTML rendering.
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// Shared business-logic helpers (mortality, feed, health recording).
include('helpers.php');
?>
