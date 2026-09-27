<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Beranda — ringkasan proyek & tautan ke setiap dokumentasi.
Route::get('/', function () {
    return view('home');
})->name('home');

// Healthcheck — JSON untuk monitoring/load balancer, HTML bila dibuka di browser.
Route::get('/health', [HealthController::class, 'index'])->name('health');

// Uji Koneksi — halaman UI pemeriksaan database, cache, storage + ringkasan .env.
Route::get('/uji-koneksi', [HealthController::class, 'ujiKoneksi'])->name('uji-koneksi');

// Dokumentasi variabel lingkungan (.env).
Route::view('/dokumentasi/env', 'docs.env')->name('dokumentasi-env');
