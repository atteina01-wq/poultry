<?php
namespace Poultry\Services;

use Exception;
use Poultry\Repositories\UserRepository;
use Poultry\Repositories\AuditLogRepository;

class UserService {
    private UserRepository $userRepo;
    private AuditLogRepository $auditRepo;

    public function __construct() {
        $this->userRepo = new UserRepository();
        $this->auditRepo = new AuditLogRepository();
    }

    public function listUsers(?string $role = null, ?int $isActive = null): array {
        return $this->userRepo->getAll($role, $isActive);
    }

    public function getUser(int $id): array {
        $user = $this->userRepo->findById($id);
        if (!$user) {
            throw new Exception("User not found");
        }
        return $user;
    }

    public function createUser(array $data, ?int $adminId = null): array {
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $fullName = trim($data['full_name'] ?? '');
        $role = $data['role'] ?? 'staff';

        if (empty($username) || empty($password) || empty($fullName)) {
            throw new Exception("Username, password, and full name are required");
        }

        if (!in_array($role, ['admin', 'staff'])) {
            throw new Exception("Invalid role. Must be 'admin' or 'staff'");
        }

        if ($this->userRepo->findByUsername($username)) {
            throw new Exception("Username '{$username}' is already taken");
        }

        $userId = $this->userRepo->create($data);
        $this->auditRepo->log($adminId, 'create_user', 'users', $userId, null, [
            'username' => $username,
            'full_name' => $fullName,
            'role' => $role
        ]);

        return $this->getUser($userId);
    }

    public function updateUser(int $id, array $data, ?int $adminId = null): array {
        $existing = $this->getUser($id);
        $this->userRepo->update($id, $data);
        $this->auditRepo->log($adminId, 'update_user', 'users', $id, $existing, $data);
        return $this->getUser($id);
    }

    public function updateRole(int $id, string $role, ?int $adminId = null): array {
        $existing = $this->getUser($id);
        if (!in_array($role, ['admin', 'staff'])) {
            throw new Exception("Invalid role. Allowed roles: 'admin', 'staff'");
        }

        $this->userRepo->updateRole($id, $role);
        $this->auditRepo->log($adminId, 'change_role', 'users', $id, ['role' => $existing['role']], ['role' => $role]);
        return $this->getUser($id);
    }

    public function updateStatus(int $id, int $isActive, ?int $adminId = null): array {
        $existing = $this->getUser($id);
        $this->userRepo->updateStatus($id, $isActive);
        $this->auditRepo->log($adminId, 'change_user_status', 'users', $id, ['is_active' => $existing['is_active']], ['is_active' => $isActive]);
        return $this->getUser($id);
    }
}
