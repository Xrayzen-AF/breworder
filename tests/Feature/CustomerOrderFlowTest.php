<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Meja;
use App\Models\Pelanggan;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MVP flow test: menu → keranjang → checkout → status → bayar kasir.
 */
class CustomerOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private function buatMenu(int $stok = 10): Produk
    {
        $kategori = Kategori::create(['nama_kategori' => 'Kopi', 'status_aktif' => true]);

        return Produk::create([
            'id_kategori' => $kategori->id_kategori,
            'kode_produk' => 'KP-'.uniqid(),
            'nama_produk' => 'Americano',
            'deskripsi' => 'Espresso + air',
            'harga' => 18000,
            'stok' => $stok,
            'status_tersedia' => true,
        ]);
    }

    private function pelanggan(): Pengguna
    {
        return Pengguna::factory()->create([
            'peran' => 'pelanggan',
            'nomor_telepon' => '081234567890',
        ]);
    }

    public function test_halaman_menu_tampil_dengan_produk(): void
    {
        $this->buatMenu();

        $this->actingAs($this->pelanggan())
            ->get(route('pelanggan.menu'))
            ->assertOk()
            ->assertSee('Americano')
            ->assertSee('Rp 18.000');
    }

    public function test_tambah_ke_keranjang_dan_tampil_di_halaman_keranjang(): void
    {
        $produk = $this->buatMenu();

        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.keranjang.tambah', $produk->id_produk), ['jumlah' => 2])
            ->assertRedirect();

        $this->get(route('pelanggan.keranjang.index'))
            ->assertOk()
            ->assertSee('Americano')
            ->assertSee('Rp 36.000'); // 2 × 18.000
    }

    public function test_stok_habis_ditolak(): void
    {
        $produk = $this->buatMenu(0);

        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.keranjang.tambah', $produk->id_produk))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_checkout_membutuhkan_meja_untuk_dine_in(): void
    {
        $produk = $this->buatMenu();
        $pelanggan = $this->pelanggan();

        $this->actingAs($pelanggan)
            ->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
        ])->assertSessionHasErrors('id_meja');
    }

    public function test_alur_lengkap_pesan_hingga_status_dan_stok_berkurang(): void
    {
        $produk = $this->buatMenu(10);
        $meja = Meja::create(['nomor_meja' => 'T1', 'kapasitas' => 4]);
        $pelanggan = $this->pelanggan();

        // 1. Tambah ke keranjang
        $this->actingAs($pelanggan)
            ->post(route('pelanggan.keranjang.tambah', $produk->id_produk), ['jumlah' => 2]);

        // 2. Checkout dine-in dengan meja
        $response = $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
            'id_meja' => $meja->id_meja,
            'catatan' => 'less sugar',
        ]);

        // 3. Redirect ke halaman status
        $response->assertRedirect();

        // Pesanan tersimpan dengan total benar
        $pesanan = Pesanan::latest('id_pesanan')->first();
        $this->assertSame('36000.00', (string) $pesanan->total_harga);
        $this->assertSame('baru', $pesanan->status_pesanan);
        $this->assertSame('dine-in', $pesanan->tipe_pesanan);
        $this->assertSame($meja->id_meja, $pesanan->id_meja);

        // Stok berkurang
        $this->assertSame(8, $produk->fresh()->stok);

        // 4. Halaman status tampil
        $this->get(route('pelanggan.status', $pesanan->nomor_pesanan))
            ->assertOk()
            ->assertSee($pesanan->nomor_pesanan)
            ->assertSee('Pesanan Diterima')
            ->assertSee('Sedang Dibuat')
            ->assertSee('Siap Diambil');

        // Keranjang dikosongkan
        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));
        $this->get(route('pelanggan.keranjang.index'))->assertOk();
    }

    public function test_barista_bisa_memproses_pesanan_dan_status_terlihat_pelanggan(): void
    {
        $produk = $this->buatMenu(5);
        $pelanggan = $this->pelanggan();
        $barista = Pengguna::factory()->create(['peran' => 'barista']);

        $this->actingAs($pelanggan)
            ->post(route('pelanggan.keranjang.tambah', $produk->id_produk));
        $this->post(route('pelanggan.checkout.simpan'), ['tipe_pesanan' => 'takeaway']);

        $pesanan = Pesanan::latest('id_pesanan')->first();

        // Barista memproses
        $this->actingAs($barista)
            ->patch(route('pesanan.status', $pesanan->id_pesanan), ['status_pesanan' => 'diproses']);

        // Pelanggan lihat status update
        $this->actingAs($pelanggan)
            ->get(route('pelanggan.status', $pesanan->nomor_pesanan))
            ->assertOk();

        $this->assertSame('diproses', $pesanan->fresh()->status_pesanan);
    }

    public function test_non_pelanggan_dilarang_akses_menu(): void
    {
        $this->actingAs(Pengguna::factory()->create(['peran' => 'kasir']))
            ->get(route('pelanggan.menu'))
            ->assertForbidden();
    }

    public function test_pelanggan_tak_bisa_melihat_status_milik_orang_lain(): void
    {
        $produk = $this->buatMenu();
        $a = $this->pelanggan();
        $this->actingAs($a)->post(route('pelanggan.keranjang.tambah', $produk->id_produk));
        $this->post(route('pelanggan.checkout.simpan'), ['tipe_pesanan' => 'takeaway']);
        $nomor = Pesanan::latest('id_pesanan')->first()->nomor_pesanan;

        $b = Pengguna::factory()->create(['peran' => 'pelanggan', 'nomor_telepon' => '089999999999']);
        $this->actingAs($b)->get(route('pelanggan.status', $nomor))->assertForbidden();
    }

    public function test_dashboard_tanpa_member_tak_bocorkan_riwayat_orang(): void
    {
        $produk = $this->buatMenu();
        $pemilik = $this->pelanggan();
        $this->actingAs($pemilik)->post(route('pelanggan.keranjang.tambah', $produk->id_produk));
        $this->post(route('pelanggan.checkout.simpan'), ['tipe_pesanan' => 'takeaway']);

        $tanpaHp = Pengguna::factory()->create(['peran' => 'pelanggan', 'nomor_telepon' => null]);

        $dashboard = $this->actingAs($tanpaHp)->get(route('pelanggan.dashboard'));
        $dashboard->assertOk();
        $this->assertNull($dashboard->viewData('pesananAktif'));

        // Riwayat (data) harus kosong — bukan milik orang lain.
        $riwayat = $this->actingAs($tanpaHp)->get(route('pelanggan.riwayat'));
        $riwayat->assertOk();
        $this->assertCount(0, $riwayat->viewData('riwayatPesanan'));
    }

    public function test_pelanggan_bisa_membatalkan_proses_pemesanan_dari_keranjang(): void
    {
        $produk = $this->buatMenu();
        $pelanggan = $this->pelanggan();

        $this->actingAs($pelanggan)
            ->post(route('pelanggan.keranjang.tambah', $produk->id_produk), ['jumlah' => 2])
            ->assertRedirect();

        $this->assertCount(1, session('keranjang'));
        $this->assertSame(2, array_sum(array_column(session('keranjang'), 'jumlah')));

        // Tombol batal membersihkan keranjang & pilihan checkout.
        $this->actingAs($pelanggan)
            ->post(route('pelanggan.keranjang.batal'))
            ->assertRedirect(route('pelanggan.menu'));

        $this->assertEmpty(session('keranjang'));
        $this->assertEmpty(session('voucher_kode'));
        $this->assertEmpty(session('tukar_poin'));
        $this->assertEmpty(session('checkout_tipe'));
        $this->assertEmpty(session('checkout_meja'));
    }

    public function test_pelanggan_bisa_membatalkan_pesanan_aktif_dari_dashboard(): void
    {
        $produk = $this->buatMenu(10);
        $pelanggan = $this->pelanggan();

        // Pesanan diterima di barista.
        $this->actingAs($pelanggan)
            ->post(route('pelanggan.keranjang.tambah', $produk->id_produk), ['jumlah' => 2])
            ->assertRedirect();

        $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'takeaway',
        ])->assertRedirect();

        $pesanan = Pesanan::latest('id_pesanan')->first();
        $this->assertSame('baru', $pesanan->fresh()->status_pesanan);

        // Tombol batal tampil di halaman beranda.
        $this->actingAs($pelanggan)
            ->get(route('pelanggan.dashboard'))
            ->assertSee('Batalkan pesanan', false);

        // Umumkan dan pastikan stok + poin dikembalikan.
        $this->actingAs($pelanggan)
            ->patch(route('pelanggan.pesanan.batal', $pesanan->id_pesanan))
            ->assertRedirect(route('pelanggan.dashboard'));

        $this->assertSame('dibatalkan', $pesanan->fresh()->status_pesanan);
        $this->assertSame(10, $produk->fresh()->stok);
    }

    public function test_ubah_kustomisasi_mengganti_bukan_menambah(): void
    {
        $produk = $this->buatMenu();
        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.keranjang.tambah', $produk->id_produk), ['ukuran' => 'large']);

        $key = array_key_first(session('keranjang'));

        // Buka mode ubah: opsi lama ter-prefill.
        $this->get(route('pelanggan.menu.kustom', $produk->id_produk).'?key='.$key)
            ->assertOk()
            ->assertSee('Simpan Perubahan', false)
            ->assertSee('name="ganti_key"', false);

        // Simpan varian beda dengan ganti_key: baris tetap 1.
        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk), [
            'susu' => 'oat', 'ganti_key' => $key,
        ])->assertRedirect(route('pelanggan.keranjang.index'));

        $cart = session('keranjang');
        $this->assertCount(1, $cart);
        $this->assertStringContainsString('Oat Milk', array_values($cart)[0]['opsi_text']);
    }

    public function test_hapus_item_keranjang_dengan_delete(): void
    {
        $produk = $this->buatMenu();
        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.keranjang.tambah', $produk->id_produk), ['ukuran' => 'large']);

        $key = array_key_first(session('keranjang'));
        $this->delete(route('pelanggan.keranjang.hapus', $key))
            ->assertRedirect();
        $this->assertEmpty(session('keranjang'));
    }

    public function test_tambah_melebihi_stok_lintas_varian_ditolak_di_keranjang(): void
    {
        $produk = $this->buatMenu(5);
        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.keranjang.tambah', $produk->id_produk), ['jumlah' => 5]);

        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk), [
            'jumlah' => 1, 'ukuran' => 'large', 'susu' => 'oat',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(5, array_sum(array_column(session('keranjang'), 'jumlah')));
    }

    public function test_tombol_plus_dashboard_menuju_kustomisasi(): void
    {
        $produk = $this->buatMenu();
        $this->actingAs($this->pelanggan())->get(route('pelanggan.dashboard'))
            ->assertOk()
            ->assertSee(route('pelanggan.menu.kustom', $produk->id_produk), false);
    }

    public function test_api_order_wajib_login(): void
    {
        $produk = $this->buatMenu();

        // Tamu tanpa login: bukan 201.
        $this->postJson('/api/orders', [
            'tipe_pesanan' => 'takeaway',
            'items' => [['id_produk' => $produk->id_produk, 'jumlah' => 1]],
        ])->assertUnauthorized();
        $this->getJson('/api/orders')->assertUnauthorized();
    }

    public function test_api_menolak_harga_di_bawah_modal(): void
    {
        $produk = $this->buatMenu();
        $kasir = Pengguna::factory()->create(['peran' => 'kasir']);

        $this->actingAs($kasir)->postJson('/api/orders', [
            'tipe_pesanan' => 'takeaway',
            'items' => [['id_produk' => $produk->id_produk, 'jumlah' => 1, 'harga_satuan' => 1]],
        ])->assertStatus(422);
    }

    public function test_api_pelanggan_tak_bisa_memakai_member_orang(): void
    {
        $produk = $this->buatMenu();
        $korban = Pelanggan::create([
            'nama_pelanggan' => 'Korban',
            'nomor_telepon' => '082222222222',
            'poin_loyalitas' => 500,
        ]);
        $penyerang = $this->pelanggan();
        $kasir = Pengguna::factory()->create(['peran' => 'kasir']);

        $res = $this->actingAs($penyerang)->postJson('/api/orders', [
            'id_pengguna' => $kasir->id_pengguna,
            'id_pelanggan' => $korban->id_pelanggan,
            'tukar_poin' => true,
            'tipe_pesanan' => 'takeaway',
            'items' => [['id_produk' => $produk->id_produk, 'jumlah' => 1]],
        ]);
        $res->assertCreated();

        $data = $res->json('data');
        $this->assertSame($penyerang->id_pengguna, $data['id_pengguna']);
        $this->assertNotSame($korban->id_pelanggan, $data['id_pelanggan']);
        $this->assertSame(500, $korban->fresh()->poin_loyalitas);
    }
}
