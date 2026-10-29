<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\AuthService;

class AuthController {
    private AuthService $authService;

    public function __construct() {
        $this->authService = new AuthService();
    }

    public function login(Request $request): void {
        try {
            $username = trim((string)$request->getBody('username', ''));
            $password = (string)$request->getBody('password', '');

            if (empty($username) || empty($password)) {
                Response::unprocessable(['username' => 'Username and password are required']);
            }

            $result = $this->authService->login($username, $password);
            Response::success($result, 'Login successful');
        } catch (Exception $e) {
            Response::unauthorized($e->getMessage());
        }
    }

    public function refresh(Request $request): void {
        try {
            $token = $request->getBearerToken();
            if (!$token) {
                Response::unauthorized('Bearer token required');
            }

            $result = $this->authService->refreshToken($token);
            Response::success($result, 'Token refreshed successfully');
        } catch (Exception $e) {
            Response::unauthorized($e->getMessage());
        }
    }

    public function me(Request $request): void {
        try {
            $userId = $request->getUserId();
            if (!$userId) {
                Response::unauthorized();
            }

            $user = $this->authService->getProfile($userId);
            Response::success($user, 'Profile retrieved successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function logout(Request $request): void {
        Response::success(null, 'Logout successful. Client token discarded.');
    }
}
