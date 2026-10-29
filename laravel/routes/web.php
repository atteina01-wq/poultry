<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\MortalityController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HealthRecordController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\WasteController;

// Public Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Authenticated Application Routes
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Batches
    Route::prefix('batches')->name('batches.')->group(function () {
        Route::get('/', [BatchController::class, 'index'])->name('index');
        Route::post('/', [BatchController::class, 'store'])->name('store');
        Route::put('/{id}', [BatchController::class, 'update'])->name('update');
        Route::post('/{id}/weigh', [BatchController::class, 'quickWeigh'])->name('weigh');
    });

    // Reservations
    Route::prefix('reservations')->name('reservations.')->group(function () {
        Route::get('/', [ReservationController::class, 'index'])->name('index');
        Route::post('/', [ReservationController::class, 'store'])->name('store');
        Route::put('/{id}', [ReservationController::class, 'update'])->name('update');
        Route::post('/{id}/fulfill', [ReservationController::class, 'fulfill'])->name('fulfill');
        Route::post('/{id}/cancel', [ReservationController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/restore', [ReservationController::class, 'restore'])->name('restore');
    });

    // Mortality Tracker
    Route::prefix('mortality')->name('mortality.')->group(function () {
        Route::get('/', [MortalityController::class, 'index'])->name('index');
        Route::post('/', [MortalityController::class, 'store'])->name('store');
    });

    // Feed Consumption
    Route::prefix('feed')->name('feed.')->group(function () {
        Route::get('/', [FeedController::class, 'index'])->name('index');
        Route::post('/', [FeedController::class, 'store'])->name('store');
    });

    // Flock Health
    Route::prefix('health')->name('health.')->group(function () {
        Route::get('/', [HealthRecordController::class, 'index'])->name('index');
        Route::post('/', [HealthRecordController::class, 'store'])->name('store');
        Route::post('/status', [HealthRecordController::class, 'updateStatus'])->name('update-status');
    });

    // Stock Inventory
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::post('/', [InventoryController::class, 'store'])->name('store');
        Route::put('/{id}', [InventoryController::class, 'update'])->name('update');
    });

    // Expenses
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');
    });

    // Retailers / Customers
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::put('/{id}', [CustomerController::class, 'update'])->name('update');
        Route::post('/{id}/deactivate', [CustomerController::class, 'deactivate'])->name('deactivate');
    });

    // Farm Operations / Activities
    Route::prefix('activities')->name('activities.')->group(function () {
        Route::get('/', [ActivityController::class, 'index'])->name('index');
        Route::post('/', [ActivityController::class, 'store'])->name('store');
        Route::post('/{id}/complete', [ActivityController::class, 'complete'])->name('complete');
        Route::get('/inventory-items', [ActivityController::class, 'getInventoryItems'])->name('inventory-items');
    });

    // Reports & Analytics
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // Farm Profile / Company
    Route::prefix('company')->name('company.')->group(function () {
        Route::get('/', [CompanyController::class, 'index'])->name('index');
        Route::put('/', [CompanyController::class, 'update'])->name('update');
    });

    // User Management
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('/{id}', [UserController::class, 'update'])->name('update');
    });

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Waste & Damage
    Route::prefix('waste')->name('waste.')->group(function () {
        Route::get('/', [WasteController::class, 'index'])->name('index');
        Route::post('/', [WasteController::class, 'store'])->name('store');
    });
});
