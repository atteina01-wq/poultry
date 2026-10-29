<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\EggProductionService;

class EggProductionController {
    private EggProductionService $prodService;

    public function __construct() {
        $this->prodService = new EggProductionService();
    }

    public function index(Request $request): void {
        try {
            $filters = [
                'batch_id' => $request->getQuery('batch_id'),
                'grade_id' => $request->getQuery('grade_id'),
                'date_from' => $request->getQuery('date_from'),
                'date_to' => $request->getQuery('date_to'),
                'date' => $request->getQuery('date'),
                'include_cancelled' => $request->getQuery('include_cancelled') === '1'
            ];
            $records = $this->prodService->listProduction($filters);
            Response::success($records, 'Production records retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function show(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $record = $this->prodService->getProduction($id);
            Response::success($record, 'Production record retrieved');
        } catch (Exception $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function store(Request $request): void {
        try {
            $data = $request->getBody();
            $userId = $request->getUserId();
            $record = $this->prodService->recordProduction($data, $userId);
            Response::created($record, 'Egg harvest recorded successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function update(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $data = $request->getBody();
            $userId = $request->getUserId();
            $record = $this->prodService->updateProduction($id, $data, $userId);
            Response::success($record, 'Egg production record updated');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function destroy(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $userId = $request->getUserId();
            $record = $this->prodService->cancelProduction($id, $userId);
            Response::success($record, 'Egg production record cancelled and stock reversed');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function summary(Request $request): void {
        try {
            $date = $request->getQuery('date');
            $summary = $this->prodService->getTodaySummary($date);
            Response::success($summary, 'Daily production summary retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
