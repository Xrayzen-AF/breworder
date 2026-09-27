<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Controller Uji Koneksi & Healthcheck
 * -------------------------------------
 * Endpoint:
 *   GET /health            -> JSON status layanan (untuk monitoring/load balancer)
 *   GET /health (HTML)     -> halaman uji koneksi bergaya UI bila diakses browser
 *   GET /uji-koneksi       -> halaman Uji Koneksi lengkap dengan panel .env aktif
 *
 * Endpoint API (prefix /api, selalu JSON — lihat routes/api.php):
 *   GET /api/ping          -> ping ringan tanpa menyentuh layanan eksternal
 *   GET /api/health        -> healthcheck JSON (payload sama dengan /health)
 *   GET /api/uji-koneksi   -> hasil pemeriksaan + ringkasan .env aktif
 *
 * Pemeriksaan yang dijalankan:
 *   - Database  : koneksi aktif + nama driver & database
 *   - Cache     : tulis/baca/hapus kunci uji pada store CACHE_STORE
 *   - Storage   : tulis/baca/hapus berkas pada storage/app (disk lokal)
 *
 * Variabel lingkungan terkait (lihat .env):
 *   - APP_NAME, APP_ENV, CACHE_STORE
 *   - HEALTHCHECK_TOKEN : jika diisi (bukan "null"), endpoint /health wajib
 *     mengirim header X-Health-Token dengan nilai yang sama.
 */
class HealthController extends Controller
{
    /**
     * Nama kunci cache & berkas uji. Unik agar mudah dibersihkan.
     */
    protected string $cacheKey = 'healthcheck:ping';

    protected string $probeFile = 'healthcheck-probe.txt';

    /**
     * Endpoint utama healthcheck.
     * Browser menerima halaman HTML; klien API/monitoring menerima JSON.
     */
    public function index(Request $request): JsonResponse|View
    {
        if (! $this->tokenValid($request)) {
            return response()->json([
                'status' => 'unauthorized',
                'message' => 'Header X-Health-Token tidak valid atau hilang.',
            ], 401);
        }

        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
        ];

        // Setiap check berisi ['ok' => bool, 'detail' => array].
        $healthy = ! in_array(false, array_column($checks, 'ok'), true);

        $payload = [
            'status' => $healthy ? 'ok' : 'degraded',
            'app' => $this->appSummary(),
            'checks' => array_map(
                fn (array $check) => $check + ['status' => $check['ok'] ? 'ok' : 'fail'],
                $checks
            ),
        ];

        // Permintaan dari browser (Accept: text/html) -> tampilkan halaman UI.
        if ($request->acceptsHtml()) {
            return view('health.index', [
                'payload' => $payload,
                'healthy' => $healthy,
                'checks' => $checks,
            ]);
        }

        return response()->json($payload, $healthy ? 200 : 503);
    }

    /**
     * Versi API dari /health — selalu JSON, tanpa negosiasi Accept,
     * dengan token proteksi yang sama (HEALTHCHECK_TOKEN).
     */
    public function apiIndex(Request $request): JsonResponse
    {
        if (! $this->tokenValid($request)) {
            return response()->json([
                'status' => 'unauthorized',
                'message' => 'Header X-Health-Token tidak valid atau hilang.',
            ], 401);
        }

        $checks = $this->buildChecks();
        $healthy = ! in_array(false, array_column($checks, 'ok'), true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'app' => $this->appSummary(),
            'checks' => array_map(
                fn (array $check) => $check + ['status' => $check['ok'] ? 'ok' : 'fail'],
                $checks
            ),
        ], $healthy ? 200 : 503);
    }

    /**
     * Versi API dari /uji-koneksi — JSON berisi hasil pemeriksaan
     * dan ringkasan konfigurasi .env yang sedang aktif.
     */
    public function apiUjiKoneksi(): JsonResponse
    {
        $checks = $this->buildChecks();
        $healthy = ! in_array(false, array_column($checks, 'ok'), true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'env' => $this->envSummary(),
        ], $healthy ? 200 : 503);
    }

    /**
     * Halaman Uji Koneksi: menjalankan ulang seluruh pemeriksaan
     * dan menampilkan ringkasan konfigurasi .env yang sedang aktif.
     */
    public function ujiKoneksi(): View
    {
        $checks = $this->buildChecks();

        $healthy = ! in_array(false, array_column($checks, 'ok'), true);

        return view('health.uji-koneksi', [
            'checks' => $checks,
            'healthy' => $healthy,
            'envSummary' => $this->envSummary(),
        ]);
    }

    /**
     * Jalankan seluruh pemeriksaan layanan (database, cache, storage).
     */
    protected function buildChecks(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
        ];
    }

    /**
     * Ringkasan identitas aplikasi untuk payload healthcheck.
     */
    protected function appSummary(): array
    {
        return [
            'name' => config('app.name'),
            'env' => app()->environment(),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'timezone' => config('app.timezone'),
            'time' => now()->toIso8601String(),
        ];
    }

    /**
     * Ringkasan konfigurasi .env yang sedang aktif.
     */
    protected function envSummary(): array
    {
        return [
            'APP_NAME' => config('app.name'),
            'APP_ENV' => app()->environment(),
            'APP_DEBUG' => config('app.debug') ? 'true' : 'false',
            'APP_URL' => config('app.url'),
            'APP_TIMEZONE' => config('app.timezone'),
            'DB_CONNECTION' => config('database.default'),
            'CACHE_STORE' => config('cache.default'),
            'QUEUE_CONNECTION' => config('queue.default'),
            'SESSION_DRIVER' => config('session.driver'),
            'FILESYSTEM_DISK' => config('filesystems.default'),
        ];
    }

    /**
     * Cek 1 — Database: jalankan query paling ringan, lalu laporkan
     * nama driver dan nama database yang terhubung.
     */
    protected function checkDatabase(): array
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            $database = $connection->getDatabaseName();

            $connection->select('select 1');

            return [
                'ok' => true,
                'detail' => [
                    'driver' => $driver,
                    'database' => $database,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'detail' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Cek 2 — Cache: pastikan store cache benar-benar bisa menulis & membaca.
     */
    protected function checkCache(): array
    {
        try {
            $store = config('cache.default');
            $value = 'pong-'.now()->getTimestampMs();

            Cache::store($store)->put($this->cacheKey, $value, 10);
            $read = Cache::store($store)->pull($this->cacheKey);

            return [
                'ok' => $read === $value,
                'detail' => [
                    'store' => $store,
                    'written' => $value,
                    'read' => $read === $value ? 'cocok' : (string) $read,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'detail' => ['store' => config('cache.default'), 'error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Cek 3 — Storage: tulis, baca, lalu hapus berkas probe pada disk lokal.
     */
    protected function checkStorage(): array
    {
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            $content = 'probe-'.now()->getTimestampMs();

            $disk->put($this->probeFile, $content);
            $read = $disk->get($this->probeFile);
            $disk->delete($this->probeFile);

            return [
                'ok' => $read === $content,
                'detail' => [
                    'disk' => 'local',
                    'path' => storage_path('app'),
                    'written' => $content,
                    'read' => $read === $content ? 'cocok' : (string) $read,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'detail' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Validasi token proteksi dari .env (HEALTHCHECK_TOKEN).
     * Bernilai null/kosong/"null" -> proteksi dinonaktifkan (mode lokal).
     */
    protected function tokenValid(Request $request): bool
    {
        $expected = env('HEALTHCHECK_TOKEN');

        if ($expected === null || $expected === '' || strtolower((string) $expected) === 'null') {
            return true;
        }

        return hash_equals((string) $expected, (string) $request->header('X-Health-Token'));
    }
}
