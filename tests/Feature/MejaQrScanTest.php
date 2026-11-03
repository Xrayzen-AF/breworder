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
 * Alur scan QR meja: status meja, check-in pemilik akun, penolakan meja
 * milik orang lain, dan pelepasan meja.
 */
class MejaQrScanTest extends TestCase
{
    use RefreshDatabase;

    private function pelanggan(string $telepon = '081200000001', ?string $nama = null): Pengguna
    {
        return Pengguna::factory()->create([
            'peran' => 'pelanggan',
            'nomor_telepon' => $telepon,
            'nama_lengkap' => $nama ?? 'Pelanggan Uji',
        ]);
    }

    private function meja(string $nomor = 'M01'): Meja
    {
        return Meja::create(['nomor_meja' => $nomor, 'kapasitas' => 4, 'status' => 'kosong']);
    }

    private function urlScan(Meja $meja): string
    {
        return route('meja.scan', ['meja' => $meja->qr_token]);
    }

    private function reservasiAktif(Meja $meja, string $kode = 'ABC123'): ReservasiMeja
    {
        $pelanggan = Pelanggan::create([
            'nama_pelanggan' => 'Pemilik Meja',
            'nomor_telepon' => '081299999999',
        ]);

        return ReservasiMeja::create([
            'id_meja' => $meja->id_meja,
            'id_pelanggan' => $pelanggan->id_pelanggan,
            'kode_booking' => $kode,
            'status' => 'aktif',
            'dicek_in_pada' => now(),
            'berakhir_pada' => now()->addMinutes(90),
        ]);
    }

    public function test_setiap_meja_mendapat_token_qr_unik(): void
    {
        $satu = $this->meja('M01');
        $dua = $this->meja('M02');

        $this->assertNotEmpty($satu->qr_token);
        $this->assertNotEmpty($dua->qr_token);
        $this->assertNotSame($satu->qr_token, $dua->qr_token);
        $this->assertSame(40, strlen($satu->qr_token));
    }

    public function test_tamu_bisa_melihat_status_meja_tanpa_login(): void
    {
        $meja = $this->meja('M02');

        $this->get($this->urlScan($meja))
            ->assertOk()
            ->assertSee('Meja M02')
            ->assertSee('Meja ini kosong')
            ->assertSee('Masuk untuk memakai meja ini')
            ->assertDontSee('Ya, pakai meja ini');
    }

    public function test_tamu_tidak_bisa_check_in_tanpa_login(): void
    {
        $meja = $this->meja();

        $this->post(route('pelanggan.meja.checkin', ['meja' => $meja->qr_token]))
            ->assertRedirect(route('login'));

        $this->assertSame(0, ReservasiMeja::count());
    }

    public function test_tamu_melihat_form_kode_booking_tanpa_detail_pemesan(): void
    {
        $meja = $this->meja('M05');
        $this->reservasiAktif($meja, 'RAHASIA');
        $meja->update(['status' => 'terisi']);

        $this->get($this->urlScan($meja))
            ->assertOk()
            ->assertSee('Tidak bisa login?')
            ->assertSee('Verifikasi kode booking')
            ->assertDontSee('RAHASIA')
            ->assertDontSee('Pemilik Meja');
    }

    public function test_tamu_bisa_verifikasi_kode_booking_dan_dikenali_sebagai_pemilik(): void
    {
        $meja = $this->meja('M06');
        $reservasi = $this->reservasiAktif($meja, 'KODE01');
        $meja->update(['status' => 'terisi']);

        $this->post(route('meja.verifikasi', ['meja' => $meja->qr_token]), ['kode_booking' => 'kode01'])
            ->assertRedirect($this->urlScan($meja))
            ->assertSessionHas('success')
            ->assertSessionHas('meja_verifikasi_'.$meja->id_meja, $reservasi->id_reservasi);

        $this->get($this->urlScan($meja))
            ->assertOk()
            ->assertSee('sudah dipesan atas nama Anda')
            ->assertSee('KODE01')
            ->assertSee('Masuk untuk pesan menu')
            ->assertDontSee('Verifikasi kode booking');
    }

    public function test_kode_booking_salah_ditolak(): void
    {
        $meja = $this->meja('M14');
        $this->reservasiAktif($meja, 'BENAR1');

        $this->post(route('meja.verifikasi', ['meja' => $meja->qr_token]), ['kode_booking' => 'SALAH1'])
            ->assertSessionHasErrors('kode_booking')
            ->assertSessionMissing('meja_verifikasi_'.$meja->id_meja);
    }

    public function test_kode_booking_tidak_berlaku_di_meja_lain(): void
    {
        $mejaA = $this->meja('M15');
        $mejaB = $this->meja('M16');
        $this->reservasiAktif($mejaA, 'PINDAH');

        $this->post(route('meja.verifikasi', ['meja' => $mejaB->qr_token]), ['kode_booking' => 'PINDAH'])
            ->assertSessionHasErrors('kode_booking');
    }

    public function test_kode_booking_sesi_kedaluwarsa_ditolak(): void
    {
        $meja = $this->meja('M17');
        $reservasi = $this->reservasiAktif($meja, 'LAMA12');
        $reservasi->update(['berakhir_pada' => now()->subMinute()]);

        $this->post(route('meja.verifikasi', ['meja' => $meja->qr_token]), ['kode_booking' => 'LAMA12'])
            ->assertSessionHasErrors('kode_booking');
    }

    public function test_url_lama_pelanggan_diarahkan_ke_halaman_scan_publik(): void
    {
        $meja = $this->meja();
        $pengguna = $this->pelanggan();

        $this->actingAs($pengguna)
            ->get(route('pelanggan.meja.scan', ['meja' => $meja->qr_token]))
            ->assertRedirect($this->urlScan($meja));
    }

    public function test_meja_kosong_menawarkan_pilihan_pakai_meja(): void
    {
        $meja = $this->meja('M04');

        $this->actingAs($this->pelanggan())
            ->get($this->urlScan($meja))
            ->assertOk()
            ->assertSee('Meja M04')
            ->assertSee('Meja ini kosong')
            ->assertSee('Ya, pakai meja ini')
            ->assertSee('Tidak, lihat menu dulu');
    }

    public function test_check_in_membuat_reservasi_atas_nama_akun_dan_menandai_meja_terisi(): void
    {
        $meja = $this->meja('M07');
        $pengguna = $this->pelanggan('081200000007', 'Sari');

        $this->actingAs($pengguna)
            ->post(route('pelanggan.meja.checkin', ['meja' => $meja->qr_token]))
            ->assertRedirect($this->urlScan($meja))
            ->assertSessionHas('success');

        $pelanggan = Pelanggan::where('nomor_telepon', '081200000007')->firstOrFail();
        $reservasi = ReservasiMeja::firstOrFail();

        $this->assertSame($meja->id_meja, $reservasi->id_meja);
        $this->assertSame($pelanggan->id_pelanggan, $reservasi->id_pelanggan);
        $this->assertSame('aktif', $reservasi->status);
        $this->assertNotNull($reservasi->dicek_in_pada);
        $this->assertSame('terisi', $meja->fresh()->status);
        $this->assertSame(6, strlen($reservasi->kode_booking));

        // Pemilik membuka ulang meja: dikenali tanpa password.
        $this->actingAs($pengguna)
            ->get($this->urlScan($meja))
            ->assertOk()
            ->assertSee('sudah dipesan atas nama Anda')
            ->assertSee($reservasi->kode_booking)
            ->assertDontSee('Ya, pakai meja ini');
    }

    public function test_pelanggan_lain_ditolak_dan_tidak_bisa_mengklaim_meja(): void
    {
        $meja = $this->meja('M03');
        $pemilik = $this->pelanggan('081200000003', 'Pemilik');
        $lain = $this->pelanggan('081200000099', 'Orang Lain');

        $this->actingAs($pemilik)
            ->post(route('pelanggan.meja.checkin', ['meja' => $meja->qr_token]));

        $this->actingAs($lain)
            ->get($this->urlScan($meja))
            ->assertOk()
            ->assertSee('sedang dipakai')
            ->assertSee('bukan milik akun Anda')
            ->assertDontSee('Ya, pakai meja ini')
            ->assertDontSee('Pemilik');

        $this->actingAs($lain)
            ->post(route('pelanggan.meja.checkin', ['meja' => $meja->qr_token]))
            ->assertSessionHasErrors('meja');

        $this->assertSame(1, ReservasiMeja::count());
    }

    public function test_check_in_ulang_oleh_pemilik_tidak_membuat_reservasi_ganda(): void
    {
        $meja = $this->meja('M08');
        $pengguna = $this->pelanggan('081200000008');

        $this->actingAs($pengguna)->post(route('pelanggan.meja.checkin', ['meja' => $meja->qr_token]));
        $this->actingAs($pengguna)->post(route('pelanggan.meja.checkin', ['meja' => $meja->qr_token]));

        $this->assertSame(1, ReservasiMeja::count());
    }

    public function test_pemilik_bisa_melepas_meja_dan_meja_kembali_kosong(): void
    {
        $meja = $this->meja('M09');
        $pengguna = $this->pelanggan('081200000009');

        $this->actingAs($pengguna)->post(route('pelanggan.meja.checkin', ['meja' => $meja->qr_token]));

        $this->actingAs($pengguna)
            ->post(route('pelanggan.meja.lepas', ['meja' => $meja->qr_token]))
            ->assertSessionHas('success');

        $this->assertSame('selesai', ReservasiMeja::firstOrFail()->status);
        $this->assertSame('kosong', $meja->fresh()->status);
    }

    public function test_pelanggan_lain_tidak_bisa_melepas_meja(): void
    {
        $meja = $this->meja('M10');
        $pemilik = $this->pelanggan('081200000010');
        $lain = $this->pelanggan('081200000011');

        $this->actingAs($pemilik)->post(route('pelanggan.meja.checkin', ['meja' => $meja->qr_token]));

        $this->actingAs($lain)
            ->post(route('pelanggan.meja.lepas', ['meja' => $meja->qr_token]))
            ->assertSessionHasErrors('meja');

        $this->assertSame('aktif', ReservasiMeja::firstOrFail()->status);
        $this->assertSame('terisi', $meja->fresh()->status);
    }

    public function test_reservasi_kedaluwarsa_otomatis_membebaskan_meja(): void
    {
        $meja = $this->meja('M11');
        $pelanggan = Pelanggan::create(['nama_pelanggan' => 'Lama', 'nomor_telepon' => '081200000012']);

        ReservasiMeja::create([
            'id_meja' => $meja->id_meja,
            'id_pelanggan' => $pelanggan->id_pelanggan,
            'kode_booking' => 'ABC123',
            'status' => 'aktif',
            'dicek_in_pada' => now()->subHours(3),
            'berakhir_pada' => now()->subHour(),
        ]);
        $meja->update(['status' => 'terisi']);

        $this->actingAs($this->pelanggan('081200000013'))
            ->get($this->urlScan($meja))
            ->assertOk()
            ->assertSee('Meja ini kosong');

        $this->assertSame('kedaluwarsa', ReservasiMeja::firstOrFail()->status);
        $this->assertSame('kosong', $meja->fresh()->status);
    }

    public function test_sinkron_meja_tetap_terisi_selama_reservasi_aktif(): void
    {
        $meja = $this->meja('M12');
        $pengguna = $this->pelanggan('081200000014');

        $this->actingAs($pengguna)->post(route('pelanggan.meja.checkin', ['meja' => $meja->qr_token]));

        Pesanan::sinkronMeja($meja->id_meja);

        $this->assertSame('terisi', $meja->fresh()->status);
    }

    public function test_meja_kosong_setelah_pesanan_terakhir_selesai_dan_tenggang_habis(): void
    {
        $meja = $this->meja('M14');
        $reservasi = $this->reservasiAktif($meja);
        $meja->update(['status' => 'terisi']);

        $pesanan = Pesanan::factory()->create([
            'id_meja' => $meja->id_meja,
            'tipe_pesanan' => 'dine-in',
            'status_pesanan' => 'diproses',
        ]);

        // Selama pesanan aktif, sesi tetap panjang dan meja terisi.
        Pesanan::sinkronMeja($meja->id_meja);
        $reservasi->refresh();
        $this->assertSame('terisi', $meja->fresh()->status);
        $this->assertTrue($reservasi->berakhir_pada->greaterThan(now()->addMinutes(60)));

        // Pesanan terakhir selesai: hanya diberi masa tenggang, bukan sisa sesi.
        $pesanan->update(['status_pesanan' => 'selesai']);
        Pesanan::sinkronMeja($meja->id_meja);

        $reservasi->refresh();
        $this->assertTrue(
            $reservasi->berakhir_pada->lessThanOrEqualTo(now()->addMinutes(ReservasiMeja::TENGGANG_MENIT)),
        );
        $this->assertSame('terisi', $meja->fresh()->status, 'Meja masih terisi selama masa tenggang.');

        // Lewat tenggang: scheduler menutup sesi dan meja kembali kosong.
        $reservasi->update(['berakhir_pada' => now()->subMinute()]);
        $this->artisan('meja:bersihkan')->assertSuccessful();

        $this->assertSame('kosong', $meja->fresh()->status);
        $this->assertSame('kedaluwarsa', $reservasi->fresh()->status);
    }

    public function test_admin_bisa_mencetak_lembar_qr_semua_meja(): void
    {
        $meja = $this->meja('M13');
        $admin = Pengguna::factory()->create(['peran' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.master.meja.qr'))
            ->assertOk()
            ->assertSee('Meja M13')
            ->assertSee('<svg', false)
            ->assertSee($meja->qr_token);
    }

    public function test_selain_admin_tidak_bisa_membuka_lembar_qr(): void
    {
        $this->actingAs($this->pelanggan())
            ->get(route('admin.master.meja.qr'))
            ->assertForbidden();
    }
}
