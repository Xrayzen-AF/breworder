<?php

namespace Tests\Feature;

use App\Models\MetodePembayaran;
use App\Models\Pelanggan;
use App\Models\Pesanan;
use App\Models\StokBiji;
use App\Models\Transaksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsAgentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_summary_mengembalikan_struktur_sesuai_spesifikasi(): void
    {
        // Metode pembayaran pertama (cash) — sama seperti DashboardController
        $metode = MetodePembayaran::create([
            'kode_metode' => 'cash',
            'nama_metode' => 'Tunai',
            'ada_kembalian' => true,
        ]);

        $pesanan = Pesanan::factory()->create(['status_pesanan' => 'selesai']);
        Transaksi::create([
            'id_pesanan' => $pesanan->id_pesanan,
            'id_metode' => $metode->id_metode,
            'subtotal' => 18450000,
            'total_bayar' => 18450000,
            'kembalian' => 0,
            'status_pembayaran' => 'lunas',
            'waktu_pembayaran' => now(),
        ]);

        $response = $this->getJson('/api/v1/ops/financial-summary');

        $response->assertOk()
            ->assertJsonStructure(['today_revenue', 'revenue_vs_yesterday_pct', 'aggregated_gross_margin_pct'])
            ->assertJsonPath('today_revenue', 18450000);
    }

    public function test_traffic_telemetry_mengembalikan_struktur_sesuai_spesifikasi(): void
    {
        Pelanggan::factory()->create();

        // Pesanan jam 08:00 (rush window pagi)
        $pesanan = Pesanan::factory()->create([
            'status_pesanan' => 'selesai',
            'waktu_pesan' => today()->setHour(8)->setMinute(15),
        ]);

        $response = $this->getJson('/api/v1/ops/traffic-telemetry');

        $response->assertOk()
            ->assertJsonStructure(['total_tickets', 'avg_prep_time_seconds', 'peak_window', 'recommendation']);

        $data = $response->json();
        $this->assertSame(1, $data['total_tickets']);
        $this->assertSame('08:00-10:00', $data['peak_window']);
        $this->assertSame('+1 floor lead for 08:30 rush window', $data['recommendation']);
    }

    public function test_inventory_alerts_mengembalikan_item_kritis_sesuai_spesifikasi(): void
    {
        StokBiji::factory()->kritis()->create(); // 2.4 kg / threshold 5.0
        StokBiji::factory()->create(['sisa_kg' => 15.0, 'laju_konsumsi_kg_per_jam' => 0.5]); // aman

        $response = $this->getJson('/api/v1/inventory/alerts');

        $response->assertOk();

        $alerts = $response->json();
        $this->assertCount(1, $alerts);
        $this->assertSame('Ethiopia Yirgacheffe Beans', $alerts[0]['item']);
        $this->assertEqualsWithDelta(2.4, $alerts[0]['remaining_kg'], 0.001);
        $this->assertEqualsWithDelta(5.0, $alerts[0]['threshold_kg'], 0.001);
        $this->assertNotNull($alerts[0]['estimated_depletion']);
    }

    public function test_inventory_alerts_kosong_jika_semua_aman(): void
    {
        StokBiji::factory()->create(['sisa_kg' => 20.0]);

        $this->getJson('/api/v1/inventory/alerts')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_reorder_mencatat_log_dan_menambah_stok(): void
    {
        $biji = StokBiji::factory()->kritis()->create();

        $response = $this->postJson('/api/v1/inventory/reorder', [
            'destination' => 'Roastery Dispatch #01',
            'item' => 'Ethiopia Yirgacheffe Beans',
            'order_quantity_kg' => 22.6,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.item', 'Ethiopia Yirgacheffe Beans')
            ->assertJsonPath('data.order_quantity_kg', 22.6)
            ->assertJsonPath('data.webhook_status', 'no_webhook_configured');

        // Stok bertambah sesuai quantity
        $this->assertEqualsWithDelta(2.4 + 22.6, (float) $biji->fresh()->sisa_kg, 0.001);

        // Log reorder tercatat
        $this->assertDatabaseHas('log_reorder', [
            'id_stok_biji' => $biji->id_stok_biji,
            'destination' => 'Roastery Dispatch #01',
            'order_quantity_kg' => 22.6,
            'status' => 'terkirim',
        ]);
    }

    public function test_reorder_validasi_dan_item_tak_dikenal(): void
    {
        // Validasi gagal
        $this->postJson('/api/v1/inventory/reorder', [
            'destination' => 'Roastery Dispatch #01',
            'item' => 'Ethiopia Yirgacheffe Beans',
        ])->assertUnprocessable();

        // Item tidak ada
        $this->postJson('/api/v1/inventory/reorder', [
            'destination' => 'Roastery Dispatch #01',
            'item' => 'Unknown Beans',
            'order_quantity_kg' => 5,
        ])->assertNotFound();
    }
}
