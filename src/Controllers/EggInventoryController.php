<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\EggInventoryService;

class EggInventoryController {
    private EggInventoryService $invService;

    public function __construct() {
        $this->invService = new EggInventoryService();
    }

    public function balance(Request $request): void {
        try {
            $balance = $this->invService->getBalance();
            Response::success($balance, 'Egg stock balance retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function movements(Request $request): void {
        try {
            $filters = [
                'grade_id' => $request->getQuery('grade_id'),
                'movement_type' => $request->getQuery('movement_type'),
                'batch_id' => $request->getQuery('batch_id')
            ];
            $limit = (int)$request->getQuery('limit', 100);
            $offset = (int)$request->getQuery('offset', 0);
            $movements = $this->invService->getMovements($filters, $limit, $offset);
            Response::success($movements, 'Egg inventory movements retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function adjust(Request $request): void {
        try {
            $data = $request->getBody();
            $userId = $request->getUserId();
            $result = $this->invService->adjustStock($data, $userId);
            Response::created($result, 'Stock adjusted successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }
}
