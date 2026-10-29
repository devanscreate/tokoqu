<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\SystemController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public auth endpoint with rate limiter (W2.2)
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Catalog & delta sync
        Route::get('/catalog', [CatalogController::class, 'sync']);
        Route::post('/categories', [CatalogController::class, 'storeCategory']);
        Route::put('/categories/{category}', [CatalogController::class, 'updateCategory']);
        Route::post('/products', [CatalogController::class, 'storeProduct']);
        Route::put('/products/{product}', [CatalogController::class, 'updateProduct']);
        Route::post('/products/import-csv', [CatalogController::class, 'importCsv']);

        // Shifts & Cash movements (W3, W8, W9, W12)
        Route::get('/shifts', [ShiftController::class, 'index']);
        Route::get('/shifts/current', [ShiftController::class, 'current']);
        Route::post('/shifts/open', [ShiftController::class, 'open']);
        Route::post('/shifts/{shift}/close', [ShiftController::class, 'close']);
        Route::post('/shifts/{shift}/force-close', [ShiftController::class, 'forceClose']);
        Route::get('/shifts/{shift}/report', [ShiftController::class, 'showReport']);
        Route::post('/cash-movements', [ShiftController::class, 'storeCashMovement']);

        // Sales, Offline Sync, Void, Returns, Manager PIN Approvals (W4, W6, W7, W13)
        Route::get('/sales', [SaleController::class, 'index']);
        Route::post('/sales', [SaleController::class, 'store']);
        Route::post('/sales/batch', [SaleController::class, 'batchSync']);
        Route::post('/sales/{sale}/void', [SaleController::class, 'void']);
        Route::post('/sales/{sale}/returns', [SaleController::class, 'returnSale']);
        Route::post('/approvals', [SaleController::class, 'verifyApproval'])->middleware('throttle:pin-approval');

        // Inventory: Stock Receipts, Adjustments/Opname, Stock Card (W10, W11)
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::post('/stock-receipts', [InventoryController::class, 'storeReceipt']);
        Route::post('/stock-adjustments', [InventoryController::class, 'storeAdjustment']);
        Route::get('/products/{product}/stock-card', [InventoryController::class, 'stockCard']);

        // Reports & Review Queue (W14)
        Route::get('/reports/summary', [ReportController::class, 'summary']);
        Route::post('/reports/stock-conflicts/{sale}/resolve', [ReportController::class, 'resolveStockConflict']);

        // System administration (Owner: W1)
        Route::get('/system', [SystemController::class, 'index']);
        Route::put('/system/settings', [SystemController::class, 'updateSettings']);
        Route::post('/system/registers', [SystemController::class, 'storeRegister']);
        Route::put('/system/registers/{register}', [SystemController::class, 'updateRegister']);
        Route::post('/system/users', [SystemController::class, 'storeUser']);
        Route::put('/system/users/{user}', [SystemController::class, 'updateUser']);
    });
});

