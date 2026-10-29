<?php
namespace Poultry\Middleware;

use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Core\JWT;

class AuthMiddleware {
    public function handle(Request $request): void {
        $token = $request->getBearerToken();
        if (!$token) {
            Response::unauthorized('Authentication token is required. Pass in Authorization: Bearer <token>');
        }

        $payload = JWT::verify($token);
        if (!$payload) {
            Response::unauthorized('Invalid or expired authentication token');
        }

        $request->setUser($payload);
    }
}
