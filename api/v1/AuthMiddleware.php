<?php
namespace Poultry\API;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class AuthMiddleware {
    private static $secretKey = "poultry_farm_super_secret_key_change_me_in_production"; // Matches login.php

    public static function authenticate() {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? '';

        if (empty($authHeader)) {
            // Some servers might not pass the Authorization header perfectly
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        }

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $jwt = $matches[1];
            try {
                $decoded = JWT::decode($jwt, new Key(self::$secretKey, 'HS256'));
                return (array) $decoded;
            } catch (Exception $e) {
                self::sendUnauthorized("Invalid or expired token");
            }
        } else {
            self::sendUnauthorized("Authorization header missing or invalid");
        }
    }

    private static function sendUnauthorized($message) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized: " . $message]);
        exit();
    }
}
