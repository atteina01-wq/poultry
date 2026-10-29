<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\CustomerService;

class CustomerController {
    private CustomerService $custService;

    public function __construct() {
        $this->custService = new CustomerService();
    }

    public function index(Request $request): void {
        try {
            $filters = [
                'customer_type' => $request->getQuery('customer_type'),
                'is_active' => $request->getQuery('is_active'),
                'search' => $request->getQuery('search')
            ];
            $customers = $this->custService->listCustomers($filters);
            Response::success($customers, 'Customers retrieved successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function show(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $customer = $this->custService->getCustomer($id);
            Response::success($customer, 'Customer profile details');
        } catch (Exception $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function store(Request $request): void {
        try {
            $data = $request->getBody();
            $userId = $request->getUserId();
            $customer = $this->custService->createCustomer($data, $userId);
            Response::created($customer, 'Customer registered successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function update(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $data = $request->getBody();
            $userId = $request->getUserId();
            $customer = $this->custService->updateCustomer($id, $data, $userId);
            Response::success($customer, 'Customer record updated');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function updateStatus(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $isActive = (int)$request->getBody('is_active', 1);
            $userId = $request->getUserId();
            $customer = $this->custService->updateStatus($id, $isActive, $userId);
            Response::success($customer, 'Customer status changed');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }
}
