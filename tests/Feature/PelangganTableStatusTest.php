<?php

namespace Tests\Feature;

use App\Models\Meja;
use App\Models\Pengguna;
use App\Models\Pesanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tombol & panel "Status Meja" real-time di beranda pelanggan.
 */
class PelangganTableStatusTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(string $peran, string $email): Pengguna
    {
        return Pengguna::create([
            'nama_lengkap' => ucfirst($peran).' Uji',
            'email' => $email,
            'nomor_telepon' => '08'.random_int(1000000000, 1999999999),
            'kata_sandi' => 'password',
            'peran' => $peran,
            'status_aktif' => true,
        ]);
    }

    public function test_pelanggan_mendapat_snapshot_status_meja(): void
    {
        $pelanggan = $this->pengguna('pelanggan', 'pelanggan@example.test');
        $kasir = $this->pengguna('kasir', 'kasir@example.test');

        $kosong = Meja::create(['nomor_meja' => 'M01', 'kapasitas' => 4, 'status' => 'kosong']);
        $terisi = Meja::create(['nomor_meja' => 'M02', 'kapasitas' => 2, 'status' => 'kosong']);
        Meja::create(['nomor_meja' => 'M03', 'kapasitas' => 6, 'status' => 'dipesan']);

        Pesanan::create([
            'nomor_pesanan' => 'PSN-TEST-9001',
            'id_pengguna' => $kasir->id_pengguna,
            'id_meja' => $terisi->id_meja,
            'tipe_pesanan' => 'dine-in',
            'status_pesanan' => 'diproses',
            'total_harga' => 45000,
        ]);

        $res = $this->actingAs($pelanggan)
            ->getJson(route('pelanggan.meja.status'));

        $res->assertOk();
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));

        $data = $res->json();

        $this->assertSame(3, count($data['tables']));
        $this->assertSame(['kosong' => 1, 'terisi' => 1, 'dipesan' => 1, 'total' => 3], $data['counts']);

        $byNomor = collect($data['tables'])->keyBy('nomor');
        $this->assertSame('kosong', $byNomor['M01']['status']);
        $this->assertSame('terisi', $byNomor['M02']['status']);
        $this->assertSame('dipesan', $byNomor['M03']['status']);
        $this->assertSame('Kosong', $byNomor['M01']['label']);
        $this->assertSame(6, $byNomor['M03']['kapasitas']);
        $this->assertSame(1, $byNomor['M02']['aktif']);
        $this->assertSame(0, $byNomor['M01']['aktif']);
        $this->assertSame($kosong->id_meja, $byNomor['M01']['id']);
    }

    public function test_status_yang_diatur_barista_langsung_terlihat_pelanggan(): void
    {
        $barista = $this->pengguna('barista', 'barista@example.test');
        $pelanggan = $this->pengguna('pelanggan', 'pelanggan@example.test');
        $meja = Meja::create(['nomor_meja' => 'M09', 'kapasitas' => 4, 'status' => 'kosong']);

        $this->actingAs($barista)
            ->post(route('barista.meja.status.update', $meja), ['status' => 'terisi'])
            ->assertSessionHas('success');

        $this->actingAs($pelanggan)
            ->getJson(route('pelanggan.meja.status'))
            ->assertOk()
            ->assertJsonPath('tables.0.status', 'terisi')
            ->assertJsonPath('tables.0.label', 'Terisi')
            ->assertJsonPath('counts.terisi', 1);

        $this->actingAs($barista)
            ->post(route('barista.meja.status.update', $meja), ['status' => 'dipesan'])
            ->assertSessionHas('success');

        $this->actingAs($pelanggan)
            ->getJson(route('pelanggan.meja.status'))
            ->assertJsonPath('tables.0.status', 'dipesan')
            ->assertJsonPath('counts.dipesan', 1);

        $this->actingAs($barista)
            ->post(route('barista.meja.status.update', $meja), ['status' => 'kosong'])
            ->assertSessionHas('success');

        $this->actingAs($pelanggan)
            ->getJson(route('pelanggan.meja.status'))
            ->assertJsonPath('tables.0.status', 'kosong')
            ->assertJsonPath('counts.kosong', 1);
    }

    public function test_snapshot_pelanggan_tidak_membocorkan_detail_pesanan(): void
    {
        $pelanggan = $this->pengguna('pelanggan', 'pelanggan@example.test');
        $kasir = $this->pengguna('kasir', 'kasir@example.test');
        $meja = Meja::create(['nomor_meja' => 'M05', 'kapasitas' => 4, 'status' => 'terisi']);

        Pesanan::create([
            'nomor_pesanan' => 'PSN-RAHASIA-1',
            'id_pengguna' => $kasir->id_pengguna,
            'id_meja' => $meja->id_meja,
            'tipe_pesanan' => 'dine-in',
            'status_pesanan' => 'baru',
            'total_harga' => 123456,
        ]);

        $res = $this->actingAs($pelanggan)->getJson(route('pelanggan.meja.status'));

        $res->assertOk();
        $this->assertSame([], $res->json('tables.0.orders'));

        $body = $res->getContent();
        $this->assertStringNotContainsString('PSN-RAHASIA-1', $body);
        $this->assertStringNotContainsString('123456', $body);
    }

    public function test_role_lain_tidak_boleh_mengakses_status_meja_pelanggan(): void
    {
        $kasir = $this->pengguna('kasir', 'kasir@example.test');

        $this->actingAs($kasir)
            ->getJson(route('pelanggan.meja.status'))
            ->assertForbidden();
    }

    public function test_beranda_pelanggan_merender_panel_status_meja(): void
    {
        $pelanggan = $this->pengguna('pelanggan', 'pelanggan@example.test');
        Meja::create(['nomor_meja' => 'M07', 'kapasitas' => 4, 'status' => 'kosong']);

        $res = $this->actingAs($pelanggan)->get(route('pelanggan.dashboard'));

        $res->assertOk()
            ->assertSee('Status Meja')
            ->assertSee('data-meja-open', false)
            ->assertSee('data-meja-grid', false)
            ->assertSee('data-meja-counts', false)
            ->assertSee('M07');
    }
}
