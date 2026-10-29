<?php
namespace Poultry\Repositories;

use PDO;
use Poultry\Core\Database;

class UserRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByUsername(string $username): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT user_id, username, full_name, role, email, phone, is_active, last_login_at, created_at, updated_at
            FROM users WHERE user_id = ? LIMIT 1
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function updateLastLogin(int $userId): bool {
        $stmt = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }

    public function getAll(?string $role = null, ?int $isActive = null): array {
        $where = [];
        $params = [];

        if ($role !== null && $role !== '') {
            $where[] = 'role = ?';
            $params[] = $role;
        }

        if ($isActive !== null) {
            $where[] = 'is_active = ?';
            $params[] = $isActive;
        }

        $sql = "SELECT user_id, username, full_name, role, email, phone, is_active, last_login_at, created_at
                FROM users";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY is_active DESC, full_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): int {
        $sql = "INSERT INTO users (username, password, full_name, role, email, phone, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['full_name'],
            $data['role'] ?? 'staff',
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['is_active'] ?? 1
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $sql = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['full_name'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $id
        ]);
    }

    public function updateRole(int $id, string $role): bool {
        $stmt = $this->db->prepare("UPDATE users SET role = ? WHERE user_id = ?");
        return $stmt->execute([$role, $id]);
    }

    public function updateStatus(int $id, int $isActive): bool {
        $stmt = $this->db->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
        return $stmt->execute([$isActive, $id]);
    }

    public function updatePassword(int $id, string $plainPassword): bool {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        return $stmt->execute([$hash, $id]);
    }
}
