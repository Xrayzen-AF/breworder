<?php

namespace App\Providers;

use App\Models\Pengguna;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Badge sidebar Ops Console — tersedia otomatis di semua view admin.
        View::composer('layouts.admin', function ($view) {
            $view->with([
                'totalKasir' => Pengguna::where('peran', 'kasir')->count(),
                'totalBarista' => Pengguna::where('peran', 'barista')->count(),
                'totalPelanggan' => Pengguna::where('peran', 'pelanggan')->count(),
            ]);
        });
    }
}
