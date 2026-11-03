<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kategori;
use App\Models\Meja;
use App\Models\MetodePembayaran;
use App\Models\MidtransSettings;
use App\Models\Produk;
use App\Models\Promo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Store Settings — profil toko + default operasional.
 * Disimpan di storage/app/store-settings.json (tanpa migrasi baru).
 */
class SettingController extends Controller
{
    public const FILE = 'store-settings.json';

    public static function defaults(): array
    {
        return [
            'store_name' => 'Tejas Coffee',
            'branch' => 'Metro Downtown Flagship #04',
            'address' => 'Jl. Metro Raya No. 04, Jakarta',
            'phone' => '021-555-0104',
            'open_hour' => '06:30',
            'close_hour' => '22:00',
            'threshold_default_kg' => 5.0,
            'safety_cap_default_kg' => 25.0,
            'poin_per_10k' => 1,
            'biaya_layanan' => 0,
            'roastery_destination' => 'Roastery Dispatch #01',
        ];
    }

    public static function load(): array
    {
        $path = storage_path('app/'.self::FILE);
        if (is_file($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            if (is_array($json)) {
                return array_merge(self::defaults(), $json);
            }
        }

        return self::defaults();
    }

    public function index(): View
    {
        $settings = self::load();

        $info = [
            'Laravel' => app()->version(),
            'PHP' => PHP_VERSION,
            'DB' => DB::connection()->getDriverName().' / '.DB::connection()->getDatabaseName(),
            'Produk' => Produk::count(),
            'Kategori' => Kategori::count(),
            'Meja' => Meja::count(),
            'Promo' => Promo::count(),
            'Metode bayar' => MetodePembayaran::count(),
        ];

        $metode = MetodePembayaran::orderBy('id_metode')->get();
        $meja = Meja::orderBy('id_meja')->limit(20)->get();
        $midtrans = MidtransSettings::query()->latest('updated_at')->latest('id')->first();

        return view('admin.settings.index', [
            'settings' => $settings,
            'info' => $info,
            'metode' => $metode,
            'meja' => $meja,
            'midtrans' => $midtrans,
            'midtransInfo' => [
                'configured' => $midtrans && filled($midtrans->server_key),
                'mode' => $midtrans?->is_production ? 'Production' : 'Sandbox',
                'server_masked' => $this->mask((string) $midtrans?->server_key),
                'client_masked' => $this->mask((string) $midtrans?->client_key),
                'webhook_url' => url('/api/midtrans/callback'),
            ],
            'activeMenu' => 'settings',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'store_name' => ['required', 'string', 'max:100'],
            'branch' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'open_hour' => ['required', 'date_format:H:i'],
            'close_hour' => ['required', 'date_format:H:i'],
            'threshold_default_kg' => ['required', 'numeric', 'min:0', 'max:1000'],
            'safety_cap_default_kg' => ['required', 'numeric', 'min:0', 'max:10000'],
            'poin_per_10k' => ['required', 'integer', 'min:0', 'max:100'],
            'biaya_layanan' => ['required', 'numeric', 'min:0', 'max:999999'],
            'roastery_destination' => ['required', 'string', 'max:100'],
        ]);

        file_put_contents(
            storage_path('app/'.self::FILE),
            json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return back()->with('success', 'Pengaturan toko disimpan.');
    }

    public function storeMetode(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'kode_metode' => ['required', 'string', 'max:50', 'unique:metode_pembayaran,kode_metode'],
            'nama_metode' => ['required', 'string', 'max:100'],
        ]);

        MetodePembayaran::create([
            'kode_metode' => strtolower(trim($v['kode_metode'])),
            'nama_metode' => trim($v['nama_metode']),
            'ada_kembalian' => str_contains(strtolower($v['kode_metode']), 'cash'),
        ]);

        return back()->with('success', "Metode {$v['nama_metode']} ditambahkan.");
    }

    /**
     * Simpan kredensial Midtrans. Field kosong = pertahankan nilai lama,
     * sehingga admin bisa mengganti mode tanpa mengetik ulang server key.
     */
    public function updateMidtrans(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'server_key' => ['nullable', 'string', 'max:255'],
            'client_key' => ['nullable', 'string', 'max:255'],
            'is_production' => ['nullable', 'boolean'],
        ]);

        $existing = MidtransSettings::query()->latest('updated_at')->latest('id')->first();
        $isProduction = $request->boolean('is_production');
        $serverKey = trim((string) ($validated['server_key'] ?? ''));
        $clientKey = trim((string) ($validated['client_key'] ?? ''));

        if ($serverKey === '' && ! $existing) {
            return back()
                ->withErrors(['server_key' => 'Server Key wajib diisi saat pertama kali mengatur Midtrans.'])
                ->withInput();
        }

        $row = MidtransSettings::firstOrNew(['is_production' => $isProduction]);
        $row->server_key = $serverKey !== '' ? $serverKey : ($row->server_key ?: $existing?->server_key);
        $row->client_key = $clientKey !== '' ? $clientKey : ($row->client_key ?: $existing?->client_key);
        $row->webhook_verified = $row->webhook_verified ?: 'pending';
        $row->save();

        return back()->with('success', 'Konfigurasi Midtrans disimpan ('.($isProduction ? 'Production' : 'Sandbox').').');
    }

    private function mask(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (strlen($value) <= 10) {
            return str_repeat('•', strlen($value));
        }

        return substr($value, 0, 6).'••••'.substr($value, -4);
    }
}
