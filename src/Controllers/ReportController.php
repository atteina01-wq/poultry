<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\ReportService;

class ReportController {
    private ReportService $reportService;

    public function __construct() {
        $this->reportService = new ReportService();
    }

    public function production(Request $request): void {
        try {
            $dateFrom = $request->getQuery('date_from');
            $dateTo = $request->getQuery('date_to');
            $batchId = $request->getQuery('batch_id') ? (int)$request->getQuery('batch_id') : null;
            $status = $request->getQuery('status');
            $report = $this->reportService->getProductionReport($dateFrom, $dateTo, $batchId, $status);
            Response::success($report, 'Broiler production report retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function mortality(Request $request): void {
        try {
            $dateFrom = $request->getQuery('date_from');
            $dateTo = $request->getQuery('date_to');
            $batchId = $request->getQuery('batch_id') ? (int)$request->getQuery('batch_id') : null;
            $report = $this->reportService->getMortalityReport($dateFrom, $dateTo, $batchId);
            Response::success($report, 'Mortality report retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function healthRecords(Request $request): void {
        try {
            $dateFrom = $request->getQuery('date_from');
            $dateTo = $request->getQuery('date_to');
            $batchId = $request->getQuery('batch_id') ? (int)$request->getQuery('batch_id') : null;
            $type = $request->getQuery('type');
            $report = $this->reportService->getHealthRecordReport($dateFrom, $dateTo, $batchId, $type);
            Response::success($report, 'Health records report retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function consumption(Request $request): void {
        try {
            $dateFrom = $request->getQuery('date_from');
            $dateTo = $request->getQuery('date_to');
            $batchId = $request->getQuery('batch_id') ? (int)$request->getQuery('batch_id') : null;
            $report = $this->reportService->getConsumptionReport($dateFrom, $dateTo, $batchId);
            Response::success($report, 'Consumption report retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }



    public function reservations(Request $request): void {
        try {
            $dateFrom = $request->getQuery('date_from');
            $dateTo = $request->getQuery('date_to');
            $status = $request->getQuery('status');
            $batchId = $request->getQuery('batch_id') ? (int)$request->getQuery('batch_id') : null;
            $report = $this->reportService->getReservationReport($dateFrom, $dateTo, $status, $batchId);
            Response::success($report, 'Reservations report retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }



    public function expenses(Request $request): void {
        try {
            $dateFrom = $request->getQuery('date_from');
            $dateTo = $request->getQuery('date_to');
            $category = $request->getQuery('category');
            $batchId = $request->getQuery('batch_id') ? (int)$request->getQuery('batch_id') : null;
            $report = $this->reportService->getExpenseReport($dateFrom, $dateTo, $category, $batchId);
            Response::success($report, 'Expenses report retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function inventory(Request $request): void {
        try {
            $dateFrom = $request->getQuery('date_from');
            $dateTo = $request->getQuery('date_to');
            $category = $request->getQuery('category');
            $status = $request->getQuery('status');
            $report = $this->reportService->getInventoryReport($dateFrom, $dateTo, $category, $status);
            Response::success($report, 'Inventory report retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function cancellations(Request $request): void {
        try {
            $dateFrom = $request->getQuery('date_from');
            $dateTo = $request->getQuery('date_to');
            $report = $this->reportService->getCancellationReport($dateFrom, $dateTo);
            Response::success($report, 'Cancellations report retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function financial(Request $request): void {
        try {
            $dateFrom = $request->getQuery('date_from');
            $dateTo = $request->getQuery('date_to');
            $report = $this->reportService->getFinancialReport($dateFrom, $dateTo);
            Response::success($report, 'Financial report retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function workerActivity(Request $request): void {
        try {
            $dateFrom = $request->getQuery('date_from');
            $dateTo = $request->getQuery('date_to');
            $batchId = $request->getQuery('batch_id') ? (int)$request->getQuery('batch_id') : null;
            $type = $request->getQuery('type');
            $report = $this->reportService->getWorkerActivityReport($dateFrom, $dateTo, $batchId, $type);
            Response::success($report, 'Worker activity report retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function flockPerformance(Request $request): void {
        try {
            $report = $this->reportService->getFlockPerformanceReport();
            Response::success($report, 'Flock performance report retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function inventoryValuation(Request $request): void {
        try {
            $report = $this->reportService->getInventoryValuation();
            Response::success($report, 'Inventory valuation report retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
