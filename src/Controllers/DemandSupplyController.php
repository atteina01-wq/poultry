<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\DemandSupplyService;

class DemandSupplyController {
    private DemandSupplyService $dsService;

    public function __construct() {
        $this->dsService = new DemandSupplyService();
    }

    public function summary(Request $request): void {
        try {
            $summary = $this->dsService->getSummary();
            Response::success($summary, 'Demand and supply summary retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function deficits(Request $request): void {
        try {
            $deficits = $this->dsService->getDeficits();
            Response::success($deficits, 'Supply deficit alert items');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
