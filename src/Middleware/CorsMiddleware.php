<?php
namespace Poultry\Middleware;

use Poultry\Core\Config;
use Poultry\Core\Request;

class CorsMiddleware {
    public function handle(Request $request): void {
        $allowedOrigins = Config::get('CORS_ALLOWED_ORIGINS', '*');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

        if ($allowedOrigins === '*') {
            header('Access-Control-Allow-Origin: *');
        } else {
            $originsList = array_map('trim', explode(',', $allowedOrigins));
            if (in_array($origin, $originsList)) {
                header("Access-Control-Allow-Origin: {$origin}");
            } else {
                header("Access-Control-Allow-Origin: " . ($originsList[0] ?? '*'));
            }
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-HTTP-Method-Override');
        header('Access-Control-Max-Age: 86400');

        if ($request->getMethod() === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
}
