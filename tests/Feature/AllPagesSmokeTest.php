<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\Produk;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test: setiap halaman utama harus bisa dirender (tanpa 500)
 * untuk role yang berhak membukanya.
 */
class AllPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $peran, string $email): Pengguna
    {
        return Pengguna::where('peran', $peran)->where('email', $email)->firstOrFail();
    }

    public function test_halaman_admin_semua_terbuka(): void
    {
        $admin = $this->user('admin', 'admin@kopi.dev');
        $produk = Produk::firstOrFail();

        $urls = [
            '/admin/dashboard',
            '/admin/sales',
            '/admin/inventory',
            '/admin/staff',
            '/admin/customers',
            '/admin/settings',
            '/admin/orders',
            '/admin/employees',
            '/admin/employees/create',
            '/admin/employees/'.$this->user('kasir', 'kasir@kopi.dev')->id_pengguna.'/edit',
            '/admin/master/kategori',
            '/admin/master/produk',
            '/admin/master/meja',
            '/admin/master/promo',
        ];

        foreach ($urls as $url) {
            $response = $this->actingAs($admin)->get($url);
            $response->assertSuccessful();

            // Jangan sampai directive Blade yang tidak dikenal ikut tercetak.
            $response->assertDontSee('@ucfirst', false);
            $response->assertDontSee('@elseRp', false);
            $response->assertDontSee('{{', false);

            // Shell admin harus lengkap: sidebar + slot judul halaman.
            $response->assertSee('admin-sidebar', false);
            $response->assertSee('admin-topbar', false);
        }
    }

    public function test_halaman_kasir_barista_pelanggan_terbuka(): void
    {
        $this->actingAs($this->user('kasir', 'kasir@kopi.dev'))
            ->get('/kasir/dashboard')->assertSuccessful();

        $this->actingAs($this->user('barista', 'barista@kopi.dev'))
            ->get('/barista/dashboard')->assertSuccessful();

        $pelanggan = $this->user('pelanggan', 'pelanggan@kopi.dev');
        $produk = Produk::firstOrFail();

        foreach ([
            '/pelanggan/dashboard',
            '/pelanggan/menu',
            '/pelanggan/menu/'.$produk->id_produk.'/kustom',
            '/pelanggan/riwayat',
            '/pelanggan/profil',
        ] as $url) {
            $this->actingAs($pelanggan)->get($url)->assertSuccessful();
        }
    }
}
