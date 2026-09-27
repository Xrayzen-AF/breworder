<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json([
    'message' => 'pong',
    'app' => config('app.name'),
    'time' => now()->toIso8601String(),
]))->name('api.ping');

Route::get('/health', [HealthController::class, 'apiIndex'])->name('api.health');

Route::get('/uji-koneksi', [HealthController::class, 'apiUjiKoneksi'])->name('api.uji-koneksi');
