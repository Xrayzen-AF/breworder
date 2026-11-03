<?php

namespace Tests\Feature;

use App\Models\Meja;
use App\Models\Pelanggan;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\ReservasiMeja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fitur papan status meja di KDS barista.
 */
class BaristaTableStatusTest extends TestCase
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

    public function test_barista_bisa_mengubah_status_meja(): void
    {
        $barista = $this->pengguna('barista', 'barista@example.test');
        $meja = Meja::create(['nomor_meja' => 'M01', 'kapasitas' => 4, 'status' => 'kosong']);

        $res = $this->actingAs($barista)
            ->post(route('barista.meja.status.update', $meja), ['status' => 'dipesan']);

        $res->assertRedirect();
        $res->assertSessionHas('success');
        $this->assertSame('dipesan', $meja->fresh()->status);
    }

    public function test_meja_dengan_pesanan_aktif_tidak_bisa_dikosongkan(): void
    {
        $barista = $this->pengguna('barista', 'barista@example.test');
        $kasir = $this->pengguna('kasir', 'kasir@example.test');
        $meja = Meja::create(['nomor_meja' => 'M02', 'kapasitas' => 4, 'status' => 'terisi']);

        Pesanan::create([
            'nomor_pesanan' => 'PSN-TEST-0001',
            'id_pengguna' => $kasir->id_pengguna,
            'id_meja' => $meja->id_meja,
            'tipe_pesanan' => 'dine-in',
            'status_pesanan' => 'baru',
            'total_harga' => 25000,
        ]);

        $res = $this->actingAs($barista)
            ->post(route('barista.meja.status.update', $meja), ['status' => 'kosong']);

        $res->assertSessionHas('error');
        $this->assertSame('terisi', $meja->fresh()->status);
    }

    public function test_status_tidak_valid_ditolak(): void
    {
        $barista = $this->pengguna('barista', 'barista@example.test');
        $meja = Meja::create(['nomor_meja' => 'M03', 'kapasitas' => 2, 'status' => 'kosong']);

        $this->actingAs($barista)
            ->post(route('barista.meja.status.update', $meja), ['status' => 'penuh'])
            ->assertSessionHasErrors('status');

        $this->assertSame('kosong', $meja->fresh()->status);
    }

    public function test_role_lain_tidak_boleh_mengubah_status_meja(): void
    {
        $meja = Meja::create(['nomor_meja' => 'M04', 'kapasitas' => 2, 'status' => 'kosong']);
        $pelanggan = $this->pengguna('pelanggan', 'pelanggan@example.test');

        $this->actingAs($pelanggan)
            ->post(route('barista.meja.status.update', $meja), ['status' => 'terisi'])
            ->assertForbidden();

        $this->assertSame('kosong', $meja->fresh()->status);
    }

    public function test_papan_meja_menampilkan_nama_pemesan_dan_sisa_waktu_sesi(): void
    {
        $barista = $this->pengguna('barista', 'barista@example.test');
        $meja = Meja::create(['nomor_meja' => 'M05', 'kapasitas' => 4, 'status' => 'terisi']);
        $pelanggan = Pelanggan::create([
            'nama_pelanggan' => 'Rina Kartika',
            'nomor_telepon' => '081277788899',
        ]);

        ReservasiMeja::create([
            'id_meja' => $meja->id_meja,
            'id_pelanggan' => $pelanggan->id_pelanggan,
            'kode_booking' => 'RINA01',
            'status' => 'aktif',
            'dicek_in_pada' => now(),
            'berakhir_pada' => now()->addMinutes(75),
        ]);

        $this->actingAs($barista)
            ->get(route('barista.dashboard'))
            ->assertOk()
            ->assertSee('Rina Kartika')
            ->assertSee('sisa')
            ->assertSee('data-sisa-sesi', false);
    }

    public function test_sesi_kedaluwarsa_tidak_tampil_di_papan_barista(): void
    {
        $barista = $this->pengguna('barista', 'barista@example.test');
        $meja = Meja::create(['nomor_meja' => 'M06', 'kapasitas' => 4, 'status' => 'terisi']);
        $pelanggan = Pelanggan::create([
            'nama_pelanggan' => 'Sesi Lama',
            'nomor_telepon' => '081277788800',
        ]);

        ReservasiMeja::create([
            'id_meja' => $meja->id_meja,
            'id_pelanggan' => $pelanggan->id_pelanggan,
            'kode_booking' => 'LAMA01',
            'status' => 'aktif',
            'dicek_in_pada' => now()->subHours(2),
            'berakhir_pada' => now()->subMinute(),
        ]);

        $this->actingAs($barista)
            ->get(route('barista.dashboard'))
            ->assertOk()
            ->assertDontSee('Sesi Lama')
            ->assertDontSee('sisa 1 j');

        $this->assertSame('kosong', $meja->fresh()->status);
        $this->assertSame('kedaluwarsa', ReservasiMeja::firstOrFail()->status);
    }
}
