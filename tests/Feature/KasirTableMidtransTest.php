<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Meja;
use App\Models\MetodePembayaran;
use App\Models\MidtransSettings;
use App\Models\Pelanggan;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasirTableMidtransTest extends TestCase
{
    use RefreshDatabase;

    private function kasir(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => 'kasir']);
    }

    /**
     * Ganti hanya transport Core API; normalisasi + penyimpanan tetap
     * dieksekusi oleh service asli.
     */
    private function fakeMidtrans(array $extra = []): MidtransService
    {
        $service = new class($extra) extends MidtransService
        {
            public int $charges = 0;

            public array $lastParams = [];

            public function __construct(private array $extra) {}

            protected function sendCharge(array $params): object
            {
                $this->charges++;
                $this->lastParams = $params;

                return (object) array_merge([
                    'order_id' => $params['transaction_details']['order_id'],
                    'transaction_id' => 'trx-midtrans-test',
                    'payment_type' => $params['payment_type'],
                    'transaction_status' => 'pending',
                    'gross_amount' => (string) $params['transaction_details']['gross_amount'],
                    'expiry_time' => now('Asia/Jakarta')->addMinutes(15)->format('Y-m-d H:i:s'),
                ], $this->extra);
            }
        };

        $this->app->instance(MidtransService::class, $service);

        return $service;
    }

    public function test_papan_meja_menghitung_status_dari_pesanan_aktif(): void
    {
        $mejaTerisi = Meja::create(['nomor_meja' => 'M01', 'kapasitas' => 2, 'status' => 'kosong']);
        Meja::create(['nomor_meja' => 'M02', 'kapasitas' => 4, 'status' => 'dipesan']);
        Meja::create(['nomor_meja' => 'M03', 'kapasitas' => 6, 'status' => 'kosong']);

        Pesanan::factory()->create([
            'id_meja' => $mejaTerisi->id_meja,
            'status_pesanan' => 'diproses',
            'total_harga' => 25000,
        ]);

        $response = $this->actingAs($this->kasir())
            ->getJson(route('kasir.meja.status'))
            ->assertOk();

        $response->assertJsonPath('tables.0.nomor', 'M01')
            ->assertJsonPath('tables.0.status', 'terisi')
            ->assertJsonPath('tables.0.orders.0.total', 25000)
            ->assertJsonPath('tables.1.status', 'dipesan')
            ->assertJsonPath('tables.2.status', 'kosong')
            ->assertJsonPath('counts.terisi', 1)
            ->assertJsonPath('counts.dipesan', 1)
            ->assertJsonPath('counts.kosong', 1);
    }

    public function test_barista_tidak_bisa_membaca_papan_meja_kasir(): void
    {
        $this->actingAs(Pengguna::factory()->create(['peran' => 'barista']))
            ->getJson(route('kasir.meja.status'))
            ->assertForbidden();
    }

    public function test_midtrans_belum_dikonfigurasi_memberi_pesan_jelas(): void
    {
        MidtransSettings::query()->delete();
        config(['services.midtrans.server_key' => null, 'services.midtrans.client_key' => null]);

        $pesanan = Pesanan::factory()->create(['total_harga' => 25000]);

        $this->actingAs($this->kasir())
            ->postJson(route('pesanan.midtrans.qris', $pesanan->id_pesanan))
            ->assertStatus(422)
            ->assertJsonPath('code', 'midtrans_not_configured');
    }

    public function test_qris_membuat_transaksi_pending_dan_menggunakan_ulang_pembayaran_aktif(): void
    {
        MidtransSettings::create([
            'server_key' => 'SB-Mid-server-test',
            'client_key' => 'SB-Mid-client-test',
            'is_production' => false,
        ]);

        $pesanan = Pesanan::factory()->create(['total_harga' => 25000]);
        $fake = $this->fakeMidtrans([
            'actions' => [[
                'name' => 'generate-qr-code',
                'method' => 'GET',
                'url' => 'https://api.sandbox.midtrans.com/v2/qris/test/qr-code',
            ]],
        ]);

        $this->actingAs($this->kasir())
            ->postJson(route('pesanan.midtrans.qris', $pesanan->id_pesanan))
            ->assertOk()
            ->assertJsonPath('reused', false)
            ->assertJsonPath('payment.qr_url', 'https://api.sandbox.midtrans.com/v2/qris/test/qr-code');

        $this->assertSame(1, $fake->charges);
        $this->assertSame('qris', $fake->lastParams['payment_type']);
        $this->assertDatabaseHas('transaksi', [
            'id_pesanan' => $pesanan->id_pesanan,
            'midtrans_order_id' => $fake->lastParams['transaction_details']['order_id'],
            'status_pembayaran' => 'menunggu',
        ]);

        // Permintaan kedua harus memakai QR yang sama, bukan menagih ulang.
        $this->actingAs($this->kasir())
            ->postJson(route('pesanan.midtrans.qris', $pesanan->id_pesanan))
            ->assertOk()
            ->assertJsonPath('reused', true)
            ->assertJsonPath('payment.qr_url', 'https://api.sandbox.midtrans.com/v2/qris/test/qr-code');

        $this->assertSame(1, $fake->charges);
    }

    public function test_transfer_va_memvalidasi_bank_dan_menyimpan_nomor_va(): void
    {
        MidtransSettings::create([
            'server_key' => 'SB-Mid-server-test',
            'client_key' => 'SB-Mid-client-test',
            'is_production' => false,
        ]);

        $pesanan = Pesanan::factory()->create(['total_harga' => 25000]);
        $fake = $this->fakeMidtrans([
            'va_numbers' => [['bank' => 'bca', 'va_number' => '12345678901']],
        ]);

        $this->actingAs($this->kasir())
            ->postJson(route('pesanan.midtrans.transfer', $pesanan->id_pesanan), ['bank' => 'bank-palsu'])
            ->assertStatus(422);

        $this->actingAs($this->kasir())
            ->postJson(route('pesanan.midtrans.transfer', $pesanan->id_pesanan), ['bank' => 'bca'])
            ->assertOk()
            ->assertJsonPath('payment.va_number', '12345678901')
            ->assertJsonPath('payment.bank', 'BCA');

        $this->assertSame(1, $fake->charges);
        $this->assertSame('bca', $fake->lastParams['bank_transfer']['bank']);
        $this->assertDatabaseHas('transaksi', [
            'id_pesanan' => $pesanan->id_pesanan,
            'midtrans_payment_type' => 'bank_transfer',
            'status_pembayaran' => 'menunggu',
        ]);
    }

    public function test_webhook_settlement_menandai_lunas_dan_memberi_poin(): void
    {
        MidtransSettings::create([
            'server_key' => 'SB-Mid-server-test',
            'client_key' => 'SB-Mid-client-test',
            'is_production' => false,
        ]);

        $pelanggan = Pelanggan::factory()->create(['poin_loyalitas' => 0]);
        $pesanan = Pesanan::factory()->create([
            'id_pelanggan' => $pelanggan->id_pelanggan,
            'total_harga' => 25000,
        ]);

        $orderId = $pesanan->nomor_pesanan.'-1700000000';
        $gross = '25000.00';
        $signature = hash('sha512', $orderId.'200'.$gross.'SB-Mid-server-test');

        $this->postJson('/api/midtrans/callback', [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $gross,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => 'qris',
            'transaction_id' => 'trx-sandbox-1',
        ])->assertOk()->assertJsonPath('payment_status', 'lunas');

        $pesanan->refresh();

        $this->assertSame('lunas', $pesanan->transaksi->status_pembayaran);
        $this->assertSame($orderId, $pesanan->transaksi->midtrans_order_id);
        $this->assertSame(2, $pelanggan->fresh()->poin_loyalitas);
    }

    public function test_webhook_dengan_signature_salah_ditolak(): void
    {
        MidtransSettings::create([
            'server_key' => 'SB-Mid-server-test',
            'client_key' => 'SB-Mid-client-test',
            'is_production' => false,
        ]);

        $pesanan = Pesanan::factory()->create(['total_harga' => 25000]);

        $this->postJson('/api/midtrans/callback', [
            'order_id' => $pesanan->nomor_pesanan.'-1700000000',
            'status_code' => '200',
            'gross_amount' => '25000.00',
            'signature_key' => 'signature-palsu',
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('transaksi', [
            'id_pesanan' => $pesanan->id_pesanan,
            'status_pembayaran' => 'lunas',
        ]);
    }

    public function test_tiket_pos_menyimpan_metode_dan_langsung_membuka_pembayaran(): void
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
        MetodePembayaran::create([
            'kode_metode' => 'QRIS',
            'nama_metode' => 'QRIS / Midtrans',
            'ada_kembalian' => false,
        ]);

        $response = $this->actingAs($this->kasir())
            ->withSession(['kasir_ticket' => [
                $produk->id_produk => [
                    'id_produk' => $produk->id_produk,
                    'nama' => $produk->nama_produk,
                    'harga' => (float) $produk->harga,
                    'jumlah' => 1,
                ],
            ]])
            ->post(route('kasir.ticket.buat'), [
                'tipe_pesanan' => 'takeaway',
                'metode' => 'qris',
            ]);

        $pesanan = Pesanan::latest('id_pesanan')->firstOrFail();

        $response->assertRedirect(route('kasir.dashboard', [
            'ticket' => $pesanan->id_pesanan,
            'bayar' => 'qris',
        ]));

        $this->assertSame('QRIS', $pesanan->transaksi->metodePembayaran->kode_metode);
        $this->assertSame('menunggu', $pesanan->transaksi->status_pembayaran);
    }

    public function test_admin_bisa_menyimpan_kredensial_midtrans(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.settings.midtrans'), [
                'server_key' => 'SB-Mid-server-baru',
                'client_key' => 'SB-Mid-client-baru',
                'is_production' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('midtrans_settings', [
            'server_key' => 'SB-Mid-server-baru',
            'client_key' => 'SB-Mid-client-baru',
            'is_production' => true,
        ]);
    }
}
