<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\BatchService;

class BatchController {
    private BatchService $batchService;

    public function __construct() {
        $this->batchService = new BatchService();
    }

    public function index(Request $request): void {
        try {
            $flockType = $request->getQuery('flock_type');
            $status = $request->getQuery('status');
            $batches = $this->batchService->listBatches($flockType, $status);
            Response::success($batches, 'Batches retrieved successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function show(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $batch = $this->batchService->getBatch($id);
            Response::success($batch, 'Batch details retrieved');
        } catch (Exception $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function store(Request $request): void {
        try {
            $data = $request->getBody();
            $userId = $request->getUserId();
            $batch = $this->batchService->createBatch($data, $userId);
            Response::created($batch, 'Batch created successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function update(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $data = $request->getBody();
            $userId = $request->getUserId();
            $batch = $this->batchService->updateBatch($id, $data, $userId);
            Response::success($batch, 'Batch updated successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function updateStatus(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $status = (string)$request->getBody('status', '');
            $userId = $request->getUserId();
            $batch = $this->batchService->updateStatus($id, $status, $userId);
            Response::success($batch, 'Batch status updated successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }
}
