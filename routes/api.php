<?php

use App\Http\Controllers\Api\MidtransNotificationController;
use App\Http\Controllers\Api\OpsAgentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Webhook Midtrans (Aman dari proteksi CSRF)
Route::post('midtrans/callback', [MidtransNotificationController::class, 'handle']);

// Menu (publik)
Route::get('categories', [CategoryController::class, 'index']);
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{produk:kode_produk}', [ProductController::class, 'show']);

// Pesanan pelanggan (wajib login — mencegah order anonim & harga palsu)
Route::middleware('auth')->group(function () {
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{nomorPesanan}', [OrderController::class, 'showByNumber']);
});

// Kasir / barista (wajib login + peran sesuai)
Route::middleware(['auth', 'role:kasir,barista'])->group(function () {
    Route::get('orders', [OrderController::class, 'index']);
    Route::patch('orders/{pesanan}/status', [OrderController::class, 'updateStatus']);
    Route::patch('orders/{pesanan}/cancel', [OrderController::class, 'cancel']);
});

// ============================================================
// Multi-agent AI orchestration (Ops Console) — lihat spec §3
// ============================================================
Route::prefix('v1')->group(function () {
    Route::get('ops/financial-summary', [OpsAgentController::class, 'financialSummary']);
    Route::get('ops/traffic-telemetry', [OpsAgentController::class, 'trafficTelemetry']);
    Route::get('inventory/alerts', [OpsAgentController::class, 'inventoryAlerts']);
    Route::post('inventory/reorder', [OpsAgentController::class, 'reorder']);
});
