<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../vendor/autoload.php';
use Firebase\JWT\JWT;
use Poultry\Core\Config;

// We need a DB connection without triggering the session/CSRF logic of the legacy system
$host = class_exists('Poultry\Core\Config') ? Config::get('DB_HOST', 'localhost') : 'localhost';
$db_name = class_exists('Poultry\Core\Config') ? Config::get('DB_NAME', 'poultry_db') : 'poultry_db';
$username = class_exists('Poultry\Core\Config') ? Config::get('DB_USER', 'root') : 'root';
$password = class_exists('Poultry\Core\Config') ? Config::get('DB_PASS', '') : '';

try {
    $db = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$user = $input['username'] ?? '';
$pass = $input['password'] ?? '';

if (empty($user) || empty($pass)) {
    http_response_code(400);
    echo json_encode(["error" => "Username and password required"]);
    exit();
}

$stmt = $db->prepare("SELECT user_id, username, password_hash, role, is_active FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$user]);
$userData = $stmt->fetch();

if ($userData && password_verify($pass, $userData['password_hash'])) {
    if ((int)$userData['is_active'] !== 1) {
        http_response_code(403);
        echo json_encode(["error" => "Account is inactive"]);
        exit();
    }
    
    // Generate JWT
    $secretKey = "poultry_farm_super_secret_key_change_me_in_production"; // Ideal to put this in Config
    $issuedAt = time();
    $expirationTime = $issuedAt + 3600;  // jwt valid for 1 hour
    
    $payload = [
        'iat' => $issuedAt,
        'exp' => $expirationTime,
        'sub' => $userData['user_id'],
        'role' => $userData['role']
    ];
    
    $jwt = JWT::encode($payload, $secretKey, 'HS256');
    
    echo json_encode([
        "success" => true,
        "token" => $jwt,
        "user" => [
            "id" => $userData['user_id'],
            "username" => $userData['username'],
            "role" => $userData['role']
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode(["error" => "Invalid credentials"]);
}
