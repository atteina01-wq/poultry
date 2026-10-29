<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\ExpenseService;

class ExpenseController {
    private ExpenseService $expenseService;

    public function __construct() {
        $this->expenseService = new ExpenseService();
    }

    public function index(Request $request): void {
        try {
            $filters = [
                'category' => $request->getQuery('category'),
                'batch_id' => $request->getQuery('batch_id'),
                'date_from' => $request->getQuery('date_from'),
                'date_to' => $request->getQuery('date_to')
            ];
            $expenses = $this->expenseService->listExpenses($filters);
            Response::success($expenses, 'Expenses retrieved successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function store(Request $request): void {
        try {
            $data = $request->getBody();
            $userId = $request->getUserId();
            $result = $this->expenseService->recordExpense($data, $userId);
            Response::created($result, 'Expense recorded successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function summary(Request $request): void {
        try {
            $dateFrom = $request->getQuery('date_from');
            $dateTo = $request->getQuery('date_to');
            $summary = $this->expenseService->getSummaryByCategory($dateFrom, $dateTo);
            Response::success($summary, 'Expenses summary by category');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
