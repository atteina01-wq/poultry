<?php
namespace Poultry\Services;

use Exception;
use Poultry\Core\JWT;
use Poultry\Repositories\UserRepository;
use Poultry\Repositories\AuditLogRepository;

class AuthService {
    private UserRepository $userRepo;
    private AuditLogRepository $auditRepo;

    public function __construct() {
        $this->userRepo = new UserRepository();
        $this->auditRepo = new AuditLogRepository();
    }

    public function login(string $username, string $password): array {
        $user = $this->userRepo->findByUsername($username);

        if (!$user || !password_verify($password, $user['password'])) {
            throw new Exception("Invalid username or password");
        }

        if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
            throw new Exception("Account is deactivated. Please contact an administrator.");
        }

        // Update last login
        $this->userRepo->updateLastLogin($user['user_id']);

        // Log audit
        $this->auditRepo->log($user['user_id'], 'login', 'users', $user['user_id']);

        $tokenPayload = [
            'user_id' => (int)$user['user_id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ];

        $token = JWT::generate($tokenPayload);

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 86400 * 7,
            'user' => $tokenPayload
        ];
    }

    public function refreshToken(string $token): array {
        $payload = JWT::verify($token);
        if (!$payload) {
            throw new Exception("Invalid or expired token");
        }

        $user = $this->userRepo->findById($payload['user_id']);
        if (!$user) {
            throw new Exception("User not found");
        }

        $newTokenPayload = [
            'user_id' => (int)$user['user_id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ];

        $newToken = JWT::generate($newTokenPayload);

        return [
            'token' => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => 86400 * 7,
            'user' => $newTokenPayload
        ];
    }

    public function getProfile(int $userId): array {
        $user = $this->userRepo->findById($userId);
        if (!$user) {
            throw new Exception("User not found");
        }
        return $user;
    }
}
