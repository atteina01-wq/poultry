<?php
namespace Poultry\Services;

use Exception;
use Poultry\Repositories\ExpenseRepository;
use Poultry\Repositories\AuditLogRepository;

class ExpenseService {
    private ExpenseRepository $expenseRepo;
    private AuditLogRepository $auditRepo;

    public function __construct() {
        $this->expenseRepo = new ExpenseRepository();
        $this->auditRepo = new AuditLogRepository();
    }

    public function listExpenses(array $filters = []): array {
        return $this->expenseRepo->getAll($filters);
    }

    public function recordExpense(array $data, ?int $userId = null): array {
        $amount = (float)($data['amount'] ?? 0.0);
        $category = $data['category'] ?? 'other';
        $validCategories = ['feed', 'medicine', 'supply', 'limestone', 'waste', 'labor', 'utilities', 'other'];

        if ($amount <= 0) {
            throw new Exception("Expense amount must be greater than 0");
        }
        if (!in_array($category, $validCategories)) {
            throw new Exception("Invalid expense category");
        }

        $data['recorded_by'] = $userId;
        $expenseId = $this->expenseRepo->create($data);
        $this->auditRepo->log($userId, 'create', 'expenses', $expenseId, null, $data);

        return [
            'expense_id' => $expenseId,
            'amount' => $amount,
            'category' => $category,
            'message' => 'Expense recorded successfully'
        ];
    }

    public function getSummaryByCategory(?string $dateFrom = null, ?string $dateTo = null): array {
        return $this->expenseRepo->getSummaryByCategory($dateFrom, $dateTo);
    }
}
