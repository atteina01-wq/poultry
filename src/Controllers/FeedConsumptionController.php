<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\FeedService;

class FeedConsumptionController {
    private FeedService $feedService;

    public function __construct() {
        $this->feedService = new FeedService();
    }

    public function index(Request $request): void {
        try {
            $filters = [
                'batch_id' => $request->getQuery('batch_id'),
                'inventory_id' => $request->getQuery('inventory_id'),
                'category' => $request->getQuery('category'),
                'date_from' => $request->getQuery('date_from'),
                'date_to' => $request->getQuery('date_to')
            ];
            $logs = $this->feedService->listFeedConsumption($filters);
            Response::success($logs, 'Feed and limestone consumption logs retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function store(Request $request): void {
        try {
            $data = $request->getBody();
            $userId = $request->getUserId();
            $result = $this->feedService->recordConsumption($data, $userId);
            Response::created($result, 'Feed consumption recorded successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function analytics(Request $request): void {
        try {
            $batchId = (int)$request->getQuery('batch_id', 0);
            if ($batchId <= 0) {
                Response::error('batch_id query parameter is required');
            }
            $analytics = $this->feedService->getAnalyticsByBatch($batchId);
            Response::success($analytics, 'Flock feed analytics retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
