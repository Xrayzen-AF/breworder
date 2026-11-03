<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\PreventBackAfterLogout;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // Halaman yang diakses user login tidak dicache browser,
        // agar tombol Back tidak membuka dashboard setelah logout.
        $middleware->web(append: [
            PreventBackAfterLogout::class,
        ]);

        // Pengguna yang sudah login membuka /login atau /register
        // langsung diarahkan ke dashboard sesuai role-nya.
        $middleware->redirectUsersTo(function () {
            $user = auth()->user();

            return match ($user?->peran) {
                'admin' => route('admin.dashboard'),
                'kasir' => route('kasir.dashboard'),
                'barista' => route('barista.dashboard'),
                'pelanggan' => route('pelanggan.dashboard'),
                default => '/',
            };
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
