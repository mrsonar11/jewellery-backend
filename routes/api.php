<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\InvoiceController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\StockController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\RateController;
use App\Http\Controllers\API\UserController;

// Public route
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (JWT required)
Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    
    // Products
    Route::apiResource('products', ProductController::class);
    Route::get('products/search', [ProductController::class, 'search']);
    
    // Customers
    Route::apiResource('customers', CustomerController::class);
    Route::get('customers/{id}/history', [CustomerController::class, 'purchaseHistory']);
    
    // Invoices
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::post('invoices', [InvoiceController::class, 'store']);
    Route::get('invoices/{id}', [InvoiceController::class, 'show']);
    
    // Stock
    Route::post('stock/in', [StockController::class, 'stockIn']);
    Route::get('stock/low-stock', [StockController::class, 'lowStockAlert']);
    
    // Reports
    Route::get('reports/daily', [ReportController::class, 'dailySales']);
    Route::get('reports/monthly', [ReportController::class, 'monthlySales']);
    Route::get('reports/product-sales', [ReportController::class, 'productSales']);
    Route::get('reports/gst', [ReportController::class, 'gstReport']);
    Route::get('reports/profit', [ReportController::class, 'profitReport']);

    //Daily Rates
    Route::get('/rates/today', [RateController::class, 'today']);
    Route::post('/rates', [RateController::class, 'store'])->middleware('auth:api');

    // User
    // Route::apiResource('users', 'App\Http\Controllers\API\UserController');
    Route::apiResource('users', UserController::class);

    Route::get('/rates/today/comparison', [RateController::class, 'todayWithComparison']);
});