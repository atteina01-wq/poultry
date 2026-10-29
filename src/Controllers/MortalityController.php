<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\MortalityService;

class MortalityController {
    private MortalityService $mortService;

    public function __construct() {
        $this->mortService = new MortalityService();
    }

    public function index(Request $request): void {
        try {
            $filters = [
                'batch_id' => $request->getQuery('batch_id'),
                'date_from' => $request->getQuery('date_from'),
                'date_to' => $request->getQuery('date_to')
            ];
            $records = $this->mortService->listMortality($filters);
            Response::success($records, 'Mortality records retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function store(Request $request): void {
        try {
            $data = $request->getBody();
            $userId = $request->getUserId();
            $result = $this->mortService->recordMortality($data, $userId);
            Response::created($result, 'Mortality record saved successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function summary(Request $request): void {
        try {
            $period = $request->getQuery('period', 'daily');
            $batchId = $request->getQuery('batch_id') !== null ? (int)$request->getQuery('batch_id') : null;
            $summary = $this->mortService->getSummary($period, $batchId);
            Response::success($summary, 'Mortality periodic summary retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
