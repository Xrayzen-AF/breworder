<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencegah browser mencache halaman yang memerlukan login.
 * Tujuannya: setelah logout, tombol Back di browser tidak bisa
 * menampilkan kembali halaman dashboard dari cache — pengguna
 * benar-benar kembali ke halaman login.
 */
class PreventBackAfterLogout
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
        }

        return $response;
    }
}
