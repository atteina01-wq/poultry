<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\DashboardService;

class DashboardController {
    private DashboardService $dashService;

    public function __construct() {
        $this->dashService = new DashboardService();
    }

    public function summary(Request $request): void {
        try {
            $kpis = $this->dashService->getKPIs();
            Response::success($kpis, 'Dashboard KPIs retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function charts(Request $request): void {
        try {
            $charts = $this->dashService->getCharts();
            Response::success($charts, 'Dashboard charts data retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function activities(Request $request): void {
        try {
            $limit = (int)($request->getQuery('limit') ?? 15);
            $activities = $this->dashService->getRecentActivities($limit);
            Response::success($activities, 'Recent activities retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
