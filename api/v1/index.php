<?php
require_once __DIR__ . '/../../src/autoload.php';

use Poultry\Core\Request;
use Poultry\Core\Router;
use Poultry\Middleware\CorsMiddleware;
use Poultry\Middleware\AuthMiddleware;
use Poultry\Middleware\AdminOnlyMiddleware;
use Poultry\Controllers\AuthController;
use Poultry\Controllers\UserController;
use Poultry\Controllers\AuditLogController;
use Poultry\Controllers\BatchController;
// EggGradeController    -- DISABLED: egg grades are out of scope (broiler-only farm per Chapter 1)
// EggProductionController -- DISABLED: egg production is out of scope
// EggInventoryController  -- DISABLED: egg inventory is out of scope
// DemandSupplyController  -- DISABLED: egg supply/demand gap is out of scope
use Poultry\Controllers\CustomerController;
use Poultry\Controllers\OrderController;
use Poultry\Controllers\ReservationController;
use Poultry\Controllers\DistributionController;
use Poultry\Controllers\InventoryController;
use Poultry\Controllers\FeedConsumptionController;
use Poultry\Controllers\HealthRecordController;
use Poultry\Controllers\MortalityController;
use Poultry\Controllers\ExpenseController;
// WasteController -- DISABLED: waste tracking is out of scope per Chapter 1
use Poultry\Controllers\DashboardController;
use Poultry\Controllers\NotificationController;
use Poultry\Controllers\ReportController;
use Poultry\Controllers\AnalyticsController;

$request = new Request();

// Run CORS globally
$cors = new CorsMiddleware();
$cors->handle($request);

$router = new Router();

// ==========================================
// 1. PUBLIC ROUTES (No Auth Required)
// ==========================================
$router->post('/auth/login', [AuthController::class, 'login']);
$router->post('/auth/refresh', [AuthController::class, 'refresh']);

// ==========================================
// 2. AUTHENTICATED ROUTES (Admin & Staff)
// ==========================================
$router->group('', [AuthMiddleware::class], function (Router $r) {
    // Auth User
    $r->get('/auth/me', [AuthController::class, 'me']);
    $r->post('/auth/logout', [AuthController::class, 'logout']);

    // Dashboard & Production Analytics (Visual reference layout)
    $r->get('/dashboard/summary', [DashboardController::class, 'summary']);
    $r->get('/dashboard/charts', [DashboardController::class, 'charts']);
    $r->get('/dashboard/activities', [DashboardController::class, 'activities']);

    // Batches / Flocks
    $r->get('/batches', [BatchController::class, 'index']);
    $r->get('/batches/{id}', [BatchController::class, 'show']);
    $r->patch('/batches/{id}/status', [BatchController::class, 'updateStatus']);

    // Egg Grades — DISABLED: out of scope (broiler-only farm per Chapter 1)
    // $r->get('/egg-grades', [EggGradeController::class, 'index']);
    // $r->get('/egg-grades/{id}', [EggGradeController::class, 'show']);

    // Egg Production — DISABLED: out of scope (broiler-only farm per Chapter 1)
    // $r->get('/egg-production', [EggProductionController::class, 'index']);
    // $r->post('/egg-production', [EggProductionController::class, 'store']);
    // $r->get('/egg-production/summary', [EggProductionController::class, 'summary']);
    // $r->get('/egg-production/{id}', [EggProductionController::class, 'show']);
    // $r->put('/egg-production/{id}', [EggProductionController::class, 'update']);

    // Egg Inventory Ledger — DISABLED: out of scope per Chapter 1
    // $r->get('/egg-inventory/balance', [EggInventoryController::class, 'balance']);
    // $r->get('/egg-inventory/movements', [EggInventoryController::class, 'movements']);

    // Demand & Supply Analytics — DISABLED: out of scope per Chapter 1
    // $r->get('/demand-supply/summary', [DemandSupplyController::class, 'summary']);
    // $r->get('/demand-supply/deficits', [DemandSupplyController::class, 'deficits']);

    // Customers - MOVED TO ADMIN ONLY GROUP

    // Orders (Commercial Sales)
    $r->get('/orders', [OrderController::class, 'index']);
    $r->post('/orders', [OrderController::class, 'store']);
    $r->get('/orders/{id}', [OrderController::class, 'show']);
    $r->patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    $r->post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    $r->post('/orders/{id}/return', [OrderController::class, 'returnOrder']);

    // Reservations (Advance Booking)
    $r->get('/reservations', [ReservationController::class, 'index']);
    $r->post('/reservations', [ReservationController::class, 'store']);
    $r->get('/reservations/{id}', [ReservationController::class, 'show']);
    $r->post('/reservations/{id}/convert-to-order', [ReservationController::class, 'convertToOrder']);
    $r->patch('/reservations/{id}/status', [ReservationController::class, 'updateStatus']);

    // Distributions (Delivery Notes)
    $r->get('/distributions', [DistributionController::class, 'index']);
    $r->post('/distributions', [DistributionController::class, 'store']);
    $r->get('/distributions/{id}', [DistributionController::class, 'show']);
    $r->patch('/distributions/{id}/status', [DistributionController::class, 'updateStatus']);

    // Central Inventory (Feed, Limestone, Medicine, Supplies)
    $r->get('/inventory', [InventoryController::class, 'index']);
    $r->post('/inventory', [InventoryController::class, 'store']);
    $r->get('/inventory/expiring', [InventoryController::class, 'expiring']);
    $r->get('/inventory/{id}', [InventoryController::class, 'show']);

    // Feed & Limestone Consumption
    $r->get('/feed-consumption', [FeedConsumptionController::class, 'index']);
    $r->post('/feed-consumption', [FeedConsumptionController::class, 'store']);
    $r->get('/feed-consumption/analytics', [FeedConsumptionController::class, 'analytics']);

    // Flock Health Records
    $r->get('/health-records', [HealthRecordController::class, 'index']);
    $r->post('/health-records', [HealthRecordController::class, 'store']);

    // Mortality & Culls
    $r->get('/mortality', [MortalityController::class, 'index']);
    $r->post('/mortality', [MortalityController::class, 'store']);
    $r->get('/mortality/summary', [MortalityController::class, 'summary']);

    // Operational Expenses
    $r->get('/expenses', [ExpenseController::class, 'index']);
    $r->post('/expenses', [ExpenseController::class, 'store']);
    $r->get('/expenses/summary', [ExpenseController::class, 'summary']);

    // Waste & Damage Records — DISABLED: out of scope per Chapter 1
    // $r->get('/waste', [WasteController::class, 'index']);
    // $r->post('/waste', [WasteController::class, 'store']);

    // Notifications & Alerts
    $r->get('/notifications', [NotificationController::class, 'index']);
    $r->patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    $r->post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    // General & Operational Reports - MOVED TO ADMIN ONLY GROUP
    // Analytics Endpoints
    $r->get('/analytics/summary', [AnalyticsController::class, 'summary']);
    $r->get('/analytics/orders', [AnalyticsController::class, 'orders']);
    $r->get('/analytics/sales', [AnalyticsController::class, 'sales']);
    $r->get('/analytics/inventory', [AnalyticsController::class, 'inventory']);
    $r->get('/analytics/reservations', [AnalyticsController::class, 'reservations']);

    // ==========================================
    // 3. ADMIN-ONLY ROUTES (Role: Admin)
    // ==========================================
    $r->group('', [AdminOnlyMiddleware::class], function (Router $adminRouter) {
        // User Administration
        $adminRouter->get('/users', [UserController::class, 'index']);
        $adminRouter->post('/users', [UserController::class, 'store']);
        $adminRouter->get('/users/{id}', [UserController::class, 'show']);
        $adminRouter->put('/users/{id}', [UserController::class, 'update']);
        $adminRouter->patch('/users/{id}/role', [UserController::class, 'updateRole']);
        $adminRouter->patch('/users/{id}/status', [UserController::class, 'updateStatus']);

        // Customers
        $adminRouter->get('/customers', [CustomerController::class, 'index']);
        $adminRouter->post('/customers', [CustomerController::class, 'store']);
        $adminRouter->get('/customers/{id}', [CustomerController::class, 'show']);
        $adminRouter->put('/customers/{id}', [CustomerController::class, 'update']);
        $adminRouter->patch('/customers/{id}/status', [CustomerController::class, 'updateStatus']);

        // General & Operational Reports
        $adminRouter->get('/reports/production', [ReportController::class, 'production']);
        $adminRouter->get('/reports/mortality', [ReportController::class, 'mortality']);
        $adminRouter->get('/reports/health-records', [ReportController::class, 'healthRecords']);
        $adminRouter->get('/reports/consumption', [ReportController::class, 'consumption']);
        $adminRouter->get('/reports/orders', [ReportController::class, 'orders']);
        $adminRouter->get('/reports/reservations', [ReportController::class, 'reservations']);
        $adminRouter->get('/reports/inventory', [ReportController::class, 'inventory']);
        $adminRouter->get('/reports/cancellations', [ReportController::class, 'cancellations']);
        $adminRouter->get('/reports/financial', [ReportController::class, 'financial']);
        $adminRouter->get('/reports/worker-activity', [ReportController::class, 'workerActivity']);
        $adminRouter->get('/reports/flock-performance', [ReportController::class, 'flockPerformance']);

        // System Audit Logs
        $adminRouter->get('/audit-logs', [AuditLogController::class, 'index']);

        // Flock Setup
        $adminRouter->post('/batches', [BatchController::class, 'store']);
        $adminRouter->put('/batches/{id}', [BatchController::class, 'update']);

        // Egg Grade Management — DISABLED: out of scope per Chapter 1
        // $adminRouter->put('/egg-grades/{id}', [EggGradeController::class, 'update']);

        // Egg Production Soft Deletion — DISABLED: out of scope per Chapter 1
        // $adminRouter->delete('/egg-production/{id}', [EggProductionController::class, 'destroy']);

        // Manual Egg Inventory Adjustments — DISABLED: out of scope per Chapter 1
        // $adminRouter->post('/egg-inventory/adjust', [EggInventoryController::class, 'adjust']);

        // Customer Status Control - MOVED ABOVE

        // Inventory Item Updates
        $adminRouter->put('/inventory/{id}', [InventoryController::class, 'update']);

        // Financial & Valuation Reports - MOVED ABOVE
        $adminRouter->get('/reports/inventory-valuation', [ReportController::class, 'inventoryValuation']);
    });
});

$router->dispatch($request);
