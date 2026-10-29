<?php
namespace Poultry\Services;

use Exception;
use Poultry\Repositories\CustomerRepository;
use Poultry\Repositories\AuditLogRepository;

class CustomerService {
    private CustomerRepository $custRepo;
    private AuditLogRepository $auditRepo;

    public function __construct() {
        $this->custRepo = new CustomerRepository();
        $this->auditRepo = new AuditLogRepository();
    }

    public function listCustomers(array $filters = []): array {
        return $this->custRepo->getAll($filters);
    }

    public function getCustomer(int $id): array {
        $cust = $this->custRepo->findById($id);
        if (!$cust) {
            throw new Exception("Customer not found");
        }
        $cust['order_history'] = [];
        return $cust;
    }

    public function createCustomer(array $data, ?int $userId = null): array {
        $code = trim($data['customer_code'] ?? '');
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $type = $data['customer_type'] ?? 'retail';

        if (empty($code)) {
            throw new Exception("Customer code is required");
        }
        if (empty($name)) {
            throw new Exception("Customer name is required");
        }
        if (!in_array($type, ['retail', 'wholesale', 'institutional'])) {
            throw new Exception("Invalid customer type");
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address format");
        }

        // Duplicate code check
        if ($this->custRepo->findByCode($code)) {
            throw new Exception("Customer code '{$code}' already exists");
        }

        $custId = $this->custRepo->create($data);
        $this->auditRepo->log($userId, 'create', 'customers', $custId, null, $data);

        return $this->getCustomer($custId);
    }

    public function updateCustomer(int $id, array $data, ?int $userId = null): array {
        $existing = $this->getCustomer($id);

        $code = trim($data['customer_code'] ?? $existing['customer_code']);
        $name = trim($data['name'] ?? $existing['name']);
        $email = trim($data['email'] ?? ($existing['email'] ?? ''));
        $type = $data['customer_type'] ?? $existing['customer_type'];

        if (empty($code) || empty($name)) {
            throw new Exception("Customer code and name are required");
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address format");
        }

        // Check if code changed and collides
        if ($code !== $existing['customer_code']) {
            $other = $this->custRepo->findByCode($code, $id);
            if ($other) {
                throw new Exception("Customer code '{$code}' already exists on another customer");
            }
        }

        $data['customer_code'] = $code;
        $data['name'] = $name;
        $data['customer_type'] = $type;

        $this->custRepo->update($id, $data);
        $this->auditRepo->log($userId, 'update', 'customers', $id, $existing, $data);

        return $this->getCustomer($id);
    }

    public function updateStatus(int $id, int $isActive, ?int $userId = null): array {
        $existing = $this->getCustomer($id);
        $this->custRepo->updateStatus($id, $isActive);
        $this->auditRepo->log($userId, 'update_status', 'customers', $id, ['is_active' => $existing['is_active']], ['is_active' => $isActive]);

        return $this->getCustomer($id);
    }
}
