<?php
namespace Poultry\Repositories;

use PDO;
use Poultry\Core\Database;

class CustomerRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(array $filters = []): array {
        $where = [];
        $params = [];

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 'is_active = ?';
            $params[] = (int)$filters['is_active'];
        }

        if (!empty($filters['customer_type'])) {
            $where[] = 'customer_type = ?';
            $params[] = $filters['customer_type'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(name LIKE ? OR customer_code LIKE ? OR phone LIKE ? OR contact_person LIKE ?)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql = "SELECT * FROM customers";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY is_active DESC, name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE customer_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByCode(string $code, ?int $excludeId = null): ?array {
        $sql = "SELECT * FROM customers WHERE customer_code = ?";
        $params = [$code];
        if ($excludeId !== null) {
            $sql .= " AND customer_id != ?";
            $params[] = $excludeId;
        }
        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int {
        $sql = "INSERT INTO customers (
                    customer_code, name, contact_person, phone, email, address, customer_type, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['customer_code'],
            $data['name'],
            $data['contact_person'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $data['customer_type'] ?? 'retail'
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $sql = "UPDATE customers SET
                    customer_code = ?, name = ?, contact_person = ?, phone = ?,
                    email = ?, address = ?, customer_type = ?
                WHERE customer_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['customer_code'],
            $data['name'],
            $data['contact_person'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $data['customer_type'] ?? 'retail',
            $id
        ]);
    }

    public function updateStatus(int $id, int $isActive): bool {
        $stmt = $this->db->prepare("UPDATE customers SET is_active = ? WHERE customer_id = ?");
        return $stmt->execute([$isActive, $id]);
    }


}
