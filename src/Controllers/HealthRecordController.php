<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\HealthService;

class HealthRecordController {
    private HealthService $healthService;

    public function __construct() {
        $this->healthService = new HealthService();
    }

    public function index(Request $request): void {
        try {
            $filters = [
                'batch_id' => $request->getQuery('batch_id'),
                'type' => $request->getQuery('type'),
                'date_from' => $request->getQuery('date_from'),
                'date_to' => $request->getQuery('date_to')
            ];
            $records = $this->healthService->listHealthRecords($filters);
            Response::success($records, 'Health logs retrieved successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function store(Request $request): void {
        try {
            $data = $request->getBody();
            $userId = $request->getUserId();
            $result = $this->healthService->recordHealth($data, $userId);
            Response::created($result, 'Health record logged successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }
}
