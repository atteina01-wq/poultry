<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../AuthMiddleware.php';

use Poultry\API\AuthMiddleware;
use Poultry\Repositories\BatchRepository;

// Protect this route with JWT
$userData = AuthMiddleware::authenticate();

// Ensure the user role has access
if (!in_array($userData['role'], ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(["error" => "Insufficient permissions"]);
    exit();
}

$batchRepo = new BatchRepository();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $flockType = $_GET['flock_type'] ?? null;
    $status = $_GET['status'] ?? null;
    
    $batches = $batchRepo->getAll($flockType, $status);
    
    echo json_encode([
        "success" => true,
        "data" => $batches
    ]);
} 
else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}
