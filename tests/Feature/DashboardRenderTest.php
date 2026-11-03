<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\MetodePembayaran;
use App\Models\Pelanggan;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Transaksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRenderTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $peran): Pengguna
    {
        return Pengguna::factory()->create(['peran' => $peran]);
    }

    public function test_dashboard_barista_kds_dirender_dengan_3_kolom(): void
    {
        $this->actingAs($this->makeUser('barista'))
            ->get(route('barista.dashboard'))
            ->assertOk()
            ->assertSee('Pesanan Dapur')
            ->assertSee('Menunggu')
            ->assertSee('Dibuat')
            ->assertSee('Siap Diambil');
    }

    public function test_dashboard_kasir_pos_dirender_dengan_metrik_dan_daftar_pesanan(): void
    {
        $this->actingAs($this->makeUser('kasir'))
            ->get(route('kasir.dashboard'))
            ->assertOk()
            ->assertSee('Kasir')
            ->assertSee('Pendapatan hari ini')
            ->assertSee('Pesanan Masuk');
    }

    public function test_dashboard_admin_memakai_data_asli_bukan_contoh(): void
    {
        $admin = $this->makeUser('admin');
        $kasir = $this->makeUser('kasir');
        $pesanan = Pesanan::factory()->create([
            'id_pengguna' => $kasir->id_pengguna,
            'status_pesanan' => 'selesai',
            'total_harga' => 50000,
        ]);
        $metode = MetodePembayaran::create([
            'kode_metode' => 'CASH',
            'nama_metode' => 'Tunai',
            'ada_kembalian' => true,
        ]);
        Transaksi::create([
            'id_pesanan' => $pesanan->id_pesanan,
            'id_metode' => $metode->id_metode,
            'subtotal' => 50000,
            'total_bayar' => 50000,
            'kembalian' => 0,
            'status_pembayaran' => 'lunas',
            'waktu_pembayaran' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Ringkasan Toko')
            ->assertSee('Rp 50.000')
            ->assertDontSee('Velvet Signature Iced Latte');
    }

    public function test_dashboard_pelanggan_dirender_dengan_menu_tanpa_riwayat(): void
    {
        $this->actingAs($this->makeUser('pelanggan'))
            ->get(route('pelanggan.dashboard'))
            ->assertOk()
            ->assertSee('Velvet Club')
            ->assertSee('Kategori Menu Cepat')
            ->assertDontSee('Riwayat Pesanan');
    }

    public function test_halaman_riwayat_pelanggan_dirender(): void
    {
        $this->actingAs($this->makeUser('pelanggan'))
            ->get(route('pelanggan.riwayat'))
            ->assertOk()
            ->assertSee('Riwayat Pesanan');
    }

    public function test_pelanggan_dilarang_membuka_dashboard_kasir_dan_barista(): void
    {
        $this->actingAs($this->makeUser('pelanggan'))
            ->get(route('kasir.dashboard'))
            ->assertForbidden();

        $this->actingAs($this->makeUser('pelanggan'))
            ->get(route('barista.dashboard'))
            ->assertForbidden();
    }

    public function test_barista_dilarang_menandai_lunas_hanya_kasir_boleh(): void
    {
        $pesanan = Pesanan::factory()->create(['status_pesanan' => 'baru']);

        $this->actingAs($this->makeUser('barista'))
            ->patch(route('pesanan.lunas', $pesanan->id_pesanan))
            ->assertForbidden();

        $this->assertDatabaseMissing('transaksi', ['id_pesanan' => $pesanan->id_pesanan]);
    }

    public function test_kasir_bisa_menandai_pesanan_lunas_tanpa_mengubah_status(): void
    {
        $pesanan = Pesanan::factory()->create(['status_pesanan' => 'baru', 'total_harga' => 25000]);

        $this->actingAs($this->makeUser('kasir'))
            ->patch(route('pesanan.lunas', $pesanan->id_pesanan), [
                'jumlah_bayar' => 30000,
            ])
            ->assertRedirect();

        $pesanan->refresh();

        // Pelunasan TIDAK otomatis menyelesaikan pesanan (barista tetap memasak).
        $this->assertSame('baru', $pesanan->status_pesanan);
        $this->assertSame('lunas', $pesanan->transaksi->status_pembayaran);
        $this->assertSame('30000.00', (string) $pesanan->transaksi->total_bayar);
        $this->assertSame('5000.00', (string) $pesanan->transaksi->kembalian);
    }

    public function test_selesai_mensyaratkan_pembayaran_lunas(): void
    {
        $pesanan = Pesanan::factory()->create(['status_pesanan' => 'diproses', 'total_harga' => 20000]);

        // Tanpa bayar → ditolak.
        $this->actingAs($this->makeUser('kasir'))
            ->patch(route('pesanan.status', $pesanan->id_pesanan), ['status_pesanan' => 'selesai'])
            ->assertRedirect();
        $this->assertSame('diproses', $pesanan->fresh()->status_pesanan);

        // Setelah lunas → boleh selesai.
        $this->patch(route('pesanan.lunas', $pesanan->id_pesanan), ['jumlah_bayar' => 20000])
            ->assertRedirect();
        $this->patch(route('pesanan.status', $pesanan->id_pesanan), ['status_pesanan' => 'selesai'])
            ->assertRedirect();
        $this->assertSame('selesai', $pesanan->fresh()->status_pesanan);
    }

    public function test_transisi_tidak_valid_ditolak(): void
    {
        $pesanan = Pesanan::factory()->create(['status_pesanan' => 'baru']);

        // Lompat baru → selesai dilarang (harus via diproses + lunas).
        $this->actingAs($this->makeUser('barista'))
            ->patch(route('pesanan.status', $pesanan->id_pesanan), ['status_pesanan' => 'selesai'])
            ->assertRedirect();
        $this->assertSame('baru', $pesanan->fresh()->status_pesanan);
    }

    public function test_batal_mengembalikan_stok(): void
    {
        $kategori = Kategori::create(['nama_kategori' => 'Kopi', 'status_aktif' => true]);
        $produk = Produk::create([
            'id_kategori' => $kategori->id_kategori,
            'kode_produk' => 'KP-'.uniqid(),
            'nama_produk' => 'Latte',
            'harga' => 20000,
            'stok' => 10,
            'status_tersedia' => true,
        ]);
        $kasir = $this->makeUser('kasir');

        $response = $this->actingAs($kasir)->postJson('/api/orders', [
            'tipe_pesanan' => 'takeaway',
            'items' => [['id_produk' => $produk->id_produk, 'jumlah' => 3]],
        ]);
        $response->assertCreated();
        $this->assertSame(7, $produk->fresh()->stok);

        $pesanan = Pesanan::latest('id_pesanan')->first();
        $this->assertSame('menunggu', $pesanan->transaksi->status_pembayaran);

        $this->actingAs($kasir)
            ->patch(route('pesanan.status', $pesanan->id_pesanan), ['status_pesanan' => 'dibatalkan'])
            ->assertRedirect();
        $this->assertSame('dibatalkan', $pesanan->fresh()->status_pesanan);
        $this->assertSame(10, $produk->fresh()->stok);
    }

    public function test_barista_bisa_memproses_pesanan_baru(): void
    {
        $pesanan = Pesanan::factory()->create(['status_pesanan' => 'baru']);

        $this->actingAs($this->makeUser('barista'))
            ->patch(route('pesanan.status', $pesanan->id_pesanan), ['status_pesanan' => 'diproses'])
            ->assertRedirect();

        $this->assertSame('diproses', $pesanan->fresh()->status_pesanan);
    }

    public function test_dashboard_kasir_menampilkan_pesanan_aktif_dari_database(): void
    {
        Pesanan::factory()->create(['status_pesanan' => 'baru']);
        Pesanan::factory()->create(['status_pesanan' => 'diproses']);
        Pesanan::factory()->create(['status_pesanan' => 'selesai']);

        $response = $this->actingAs($this->makeUser('kasir'))
            ->get(route('kasir.dashboard'));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('pesananAktif')->count());
        $this->assertSame(1, $response->viewData('jumlahSelesaiHariIni'));
    }

    public function test_riwayat_pelanggan_terkait_nomor_telepon_akun(): void
    {
        $pengguna = Pengguna::factory()->create([
            'peran' => 'pelanggan',
            'nomor_telepon' => '081234567890',
        ]);

        $pelanggan = Pelanggan::factory()->create(['nomor_telepon' => '081234567890']);
        Pesanan::factory()->count(3)->create(['id_pelanggan' => $pelanggan->id_pelanggan]);

        $response = $this->actingAs($pengguna)->get(route('pelanggan.riwayat'));

        $response->assertOk();
        $this->assertSame(3, $response->viewData('riwayatPesanan')->count());
    }

    public function test_kasir_bisa_serah_terima_pesanan_baru_yang_sudah_lunas(): void
    {
        $pesanan = Pesanan::factory()->create(['status_pesanan' => 'baru', 'total_harga' => 22000]);
        $kasir = $this->makeUser('kasir');

        // Sebelum lunas -> ditolak
        $this->actingAs($kasir)
            ->patch(route('pesanan.status', $pesanan->id_pesanan), ['status_pesanan' => 'selesai'])
            ->assertRedirect();
        $this->assertSame('baru', $pesanan->fresh()->status_pesanan);

        // Setelah lunas -> kasir berhasil serah terima langsung
        $this->actingAs($kasir)
            ->patch(route('pesanan.lunas', $pesanan->id_pesanan), ['jumlah_bayar' => 22000])
            ->assertRedirect();
        $this->actingAs($kasir)
            ->patch(route('pesanan.status', $pesanan->id_pesanan), ['status_pesanan' => 'selesai'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('selesai', $pesanan->fresh()->status_pesanan);
    }

    public function test_kasir_bisa_memproses_pesanan_baru_menjadi_diproses(): void
    {
        $pesanan = Pesanan::factory()->create(['status_pesanan' => 'baru']);
        $kasir = $this->makeUser('kasir');

        $this->actingAs($kasir)
            ->patch(route('pesanan.status', $pesanan->id_pesanan), ['status_pesanan' => 'diproses'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('diproses', $pesanan->fresh()->status_pesanan);
    }
}
