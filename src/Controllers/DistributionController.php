<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\DistributionService;

class DistributionController {
    private DistributionService $distService;

    public function __construct() {
        $this->distService = new DistributionService();
    }

    public function index(Request $request): void {
        try {
            $filters = [
                'order_id' => $request->getQuery('order_id'),
                'customer_id' => $request->getQuery('customer_id'),
                'status' => $request->getQuery('status'),
                'date' => $request->getQuery('date')
            ];
            $distributions = $this->distService->listDistributions($filters);
            Response::success($distributions, 'Distributions retrieved successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function show(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $dist = $this->distService->getDistribution($id);
            Response::success($dist, 'Distribution details retrieved');
        } catch (Exception $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function store(Request $request): void {
        try {
            $data = $request->getBody();
            $userId = $request->getUserId();
            $dist = $this->distService->createDistribution($data, $userId);
            Response::created($dist, 'Distribution dispatched successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function updateStatus(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $status = (string)$request->getBody('status', '');
            $userId = $request->getUserId();
            $dist = $this->distService->updateStatus($id, $status, $userId);
            Response::success($dist, 'Distribution status updated');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }
}
