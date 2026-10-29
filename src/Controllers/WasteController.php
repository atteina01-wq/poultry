<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\WasteService;

class WasteController {
    private WasteService $wasteService;

    public function __construct() {
        $this->wasteService = new WasteService();
    }

    public function index(Request $request): void {
        try {
            $filters = [
                'waste_type' => $request->getQuery('waste_type'),
                'date_from' => $request->getQuery('date_from'),
                'date_to' => $request->getQuery('date_to')
            ];
            $records = $this->wasteService->listWaste($filters);
            Response::success($records, 'Waste records retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function store(Request $request): void {
        try {
            $data = $request->getBody();
            $userId = $request->getUserId();
            $result = $this->wasteService->recordWaste($data, $userId);
            Response::created($result, 'Waste record logged successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }
}
