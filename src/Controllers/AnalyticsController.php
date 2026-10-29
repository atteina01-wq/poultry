<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\DashboardService;

class AnalyticsController {
    private DashboardService $dashService;

    public function __construct() {
        $this->dashService = new DashboardService();
    }

    public function summary(Request $request): void {
        try {
            $charts = $this->dashService->getCharts();
            Response::success($charts, 'Analytics summary retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }





    public function inventory(Request $request): void {
        try {
            $kpis = $this->dashService->getKPIs();
            $charts = $this->dashService->getCharts();
            Response::success([
                'grades_breakdown' => $kpis['grades_breakdown'],
                'stock_movements' => $kpis['stock_movements'],
                'egg_production_by_grade' => $charts['egg_production_by_grade']
            ], 'Inventory analytics retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function reservations(Request $request): void {
        try {
            $charts = $this->dashService->getCharts();
            $kpis = $this->dashService->getKPIs();
            Response::success([
                'reservations_by_status' => $charts['reservations_by_status'],
                'reservations_kpi' => $kpis['reservations']
            ], 'Reservation analytics retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
