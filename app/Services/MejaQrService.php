<?php

namespace App\Services;

use App\Models\Meja;
use App\Models\Pelanggan;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\ReservasiMeja;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur scan QR meja:
 * - QR hanya menunjukkan meja (bukan kunci akses).
 * - Kepemilikan dibuktikan oleh akun pelanggan yang matching dengan
 *   `reservasi_meja.id_pelanggan` / `pesanan.id_pelanggan`, atau oleh
 *   kode booking untuk tamu yang tidak bisa login.
 * - Meja kosong diklaim lewat check-in; meja yang sudah dipesan orang
 *   lain ditolak tanpa membocorkan identitas pemesannya.
 */
class MejaQrService
{
    public const STATUS_KOSONG = 'kosong';

    public const STATUS_TERISI = 'terisi';

    public const STATUS_DIPESAN = 'dipesan';

    public const KODE_KOSONG = 'kosong';

    public const KODE_MILIK_SENDIRI = 'milik_sendiri';

    public const KODE_ORANG_LAIN = 'orang_lain';

    public const KODE_TERISI = 'terisi';

    /** Pelanggan (member) milik akun login, tanpa membuat baris baru. */
    public function pelangganAkun(?Pengguna $pengguna): ?Pelanggan
    {
        if (! $pengguna || blank($pengguna->nomor_telepon)) {
            return null;
        }

        return Pelanggan::where('nomor_telepon', $pengguna->nomor_telepon)->first();
    }

    /** Pelanggan (member) milik akun login, dibuat bila belum ada. */
    public function pelangganUntuk(Pengguna $pengguna): ?Pelanggan
    {
        if (blank($pengguna->nomor_telepon)) {
            return null;
        }

        return Pelanggan::firstOrCreate(
            ['nomor_telepon' => $pengguna->nomor_telepon],
            ['nama_pelanggan' => $pengguna->nama_lengkap, 'poin_loyalitas' => 0],
        );
    }

    /**
     * Snapshot meja untuk halaman scan.
     *
     * @param  int|null  $idReservasiTerverifikasi  ID reservasi yang sudah dicocokkan lewat kode booking (sesi tamu)
     * @return array{kode: string, status: string, label: string, reservasi: ?ReservasiMeja, pesanan: ?Pesanan, bisa_klaim: bool, bisa_verifikasi: bool}
     */
    public function status(Meja $meja, ?Pengguna $pengguna, ?int $idReservasiTerverifikasi = null): array
    {
        ReservasiMeja::bersihkanKedaluwarsa();
        $meja->refresh();

        $pelanggan = $this->pelangganAkun($pengguna);

        $pesananAktif = $meja->pesanan()
            ->whereIn('status_pesanan', ['baru', 'diproses'])
            ->latest('waktu_pesan')
            ->first();

        if ($pesananAktif) {
            $milikSendiri = $pengguna
                && $this->pesananMilik($pengguna, $pelanggan, $pesananAktif);

            return [
                'kode' => $milikSendiri ? self::KODE_MILIK_SENDIRI : self::KODE_TERISI,
                'status' => self::STATUS_TERISI,
                'label' => 'Terisi',
                'reservasi' => null,
                'pesanan' => $pesananAktif,
                'bisa_klaim' => false,
                'bisa_verifikasi' => false,
            ];
        }

        $reservasi = $meja->reservasi()->aktif()->latest('dibuat_pada')->first();

        if ($reservasi) {
            $milikSendiri = ($pelanggan
                    && (int) $reservasi->id_pelanggan === (int) $pelanggan->id_pelanggan)
                || ($idReservasiTerverifikasi !== null
                    && (int) $reservasi->id_reservasi === $idReservasiTerverifikasi);

            return [
                'kode' => $milikSendiri ? self::KODE_MILIK_SENDIRI : self::KODE_ORANG_LAIN,
                'status' => $reservasi->sudahCheckIn() ? self::STATUS_TERISI : self::STATUS_DIPESAN,
                'label' => $reservasi->sudahCheckIn() ? 'Terisi' : 'Dipesan',
                // Jangan kirim detail reservasi orang lain ke view.
                'reservasi' => $milikSendiri ? $reservasi : null,
                'pesanan' => null,
                'bisa_klaim' => false,
                // Kode booking jadi jalan masuk cadangan bila pelanggan
                // tidak bisa login di perangkat ini.
                'bisa_verifikasi' => ! $milikSendiri,
            ];
        }

        // Status yang diatur manual oleh barista (tanpa reservasi tercatat).
        if ($meja->status === self::STATUS_TERISI) {
            return [
                'kode' => self::KODE_TERISI,
                'status' => self::STATUS_TERISI,
                'label' => 'Terisi',
                'reservasi' => null,
                'pesanan' => null,
                'bisa_klaim' => false,
                'bisa_verifikasi' => false,
            ];
        }

        if ($meja->status === self::STATUS_DIPESAN) {
            return [
                'kode' => self::KODE_ORANG_LAIN,
                'status' => self::STATUS_DIPESAN,
                'label' => 'Dipesan',
                'reservasi' => null,
                'pesanan' => null,
                'bisa_klaim' => false,
                'bisa_verifikasi' => false,
            ];
        }

        return [
            'kode' => self::KODE_KOSONG,
            'status' => self::STATUS_KOSONG,
            'label' => 'Kosong',
            'reservasi' => null,
            'pesanan' => null,
            'bisa_klaim' => true,
            'bisa_verifikasi' => false,
        ];
    }

    /**
     * Cocokkan kode booking untuk meja yang sedang dipesan. Dipakai tamu
     * yang tidak bisa login; kode hanya berlaku pada sesi yang masih aktif.
     */
    public function verifikasiKode(Meja $meja, string $kode): ReservasiMeja
    {
        ReservasiMeja::bersihkanKedaluwarsa();

        $kode = strtoupper(trim($kode));

        $reservasi = $meja->reservasi()
            ->aktif()
            ->where('kode_booking', $kode)
            ->first();

        if (! $reservasi) {
            throw ValidationException::withMessages([
                'kode_booking' => 'Kode booking tidak cocok atau sesi meja sudah berakhir.',
            ]);
        }

        return $reservasi;
    }

    /**
     * Check-in ke meja kosong: buat reservasi milik pelanggan lalu
     * tandai meja terisi. Aman dari dua pelanggan yang scan bersamaan
     * karena baris meja dikunci di dalam transaksi.
     */
    public function klaim(Meja $meja, Pengguna $pengguna): ReservasiMeja
    {
        $pelanggan = $this->pelangganUntuk($pengguna);

        if (! $pelanggan) {
            throw ValidationException::withMessages([
                'meja' => 'Lengkapi nomor HP di profil dulu agar meja tercatat atas nama Anda.',
            ]);
        }

        return DB::transaction(function () use ($meja, $pelanggan) {
            $meja = Meja::whereKey($meja->id_meja)->lockForUpdate()->firstOrFail();

            // Reservasi lewat waktu dilepas dulu sebelum menilai status.
            $meja->reservasi()
                ->where('status', 'aktif')
                ->where('berakhir_pada', '<=', now())
                ->update(['status' => 'kedaluwarsa']);

            $reservasiAktif = $meja->reservasi()->aktif()->first();

            if ($reservasiAktif) {
                // Idempoten: pemilik yang scan ulang tetap memakai sesi yang sama.
                if ((int) $reservasiAktif->id_pelanggan === (int) $pelanggan->id_pelanggan) {
                    $meja->update(['status' => self::STATUS_TERISI]);

                    return $reservasiAktif;
                }

                throw ValidationException::withMessages([
                    'meja' => "Meja {$meja->nomor_meja} sudah dipesan pelanggan lain. Silakan pilih meja lain.",
                ]);
            }

            $adaPesanan = $meja->pesanan()
                ->whereIn('status_pesanan', ['baru', 'diproses'])
                ->exists();

            if ($adaPesanan || $meja->status !== self::STATUS_KOSONG) {
                throw ValidationException::withMessages([
                    'meja' => "Meja {$meja->nomor_meja} sedang terpakai. Silakan pilih meja lain.",
                ]);
            }

            $reservasi = ReservasiMeja::create([
                'id_meja' => $meja->id_meja,
                'id_pelanggan' => $pelanggan->id_pelanggan,
                'kode_booking' => ReservasiMeja::generateKodeBooking(),
                'status' => 'aktif',
                'dicek_in_pada' => now(),
                'berakhir_pada' => now()->addMinutes(ReservasiMeja::DURASI_MENIT),
            ]);

            $meja->update(['status' => self::STATUS_TERISI]);

            return $reservasi;
        });
    }

    /**
     * Lepas meja oleh pemiliknya (selesai memakai meja). Ditolak bila
     * masih ada pesanan aktif agar status meja tidak bentrok dengan dapur.
     */
    public function lepas(Meja $meja, Pengguna $pengguna): void
    {
        $pelanggan = $this->pelangganAkun($pengguna);

        DB::transaction(function () use ($meja, $pelanggan) {
            $meja = Meja::whereKey($meja->id_meja)->lockForUpdate()->firstOrFail();
            $reservasi = $meja->reservasi()->aktif()->latest('dibuat_pada')->first();

            if (! $reservasi || ! $pelanggan
                || (int) $reservasi->id_pelanggan !== (int) $pelanggan->id_pelanggan) {
                throw ValidationException::withMessages([
                    'meja' => 'Meja ini bukan milik akun Anda.',
                ]);
            }

            $adaPesanan = $meja->pesanan()
                ->whereIn('status_pesanan', ['baru', 'diproses'])
                ->exists();

            if ($adaPesanan) {
                throw ValidationException::withMessages([
                    'meja' => 'Masih ada pesanan aktif di meja ini. Tunggu pesanan selesai atau hubungi staf.',
                ]);
            }

            $reservasi->update(['status' => 'selesai']);
            Pesanan::sinkronMeja($meja->id_meja);
        });
    }

    /**
     * Meja lain yang benar-benar bisa dipakai (tidak ada pesanan aktif
     * maupun reservasi berjalan), untuk tawarkan pilihan pengganti.
     *
     * @return Collection<int, Meja>
     */
    public function mejaKosong(int $kecualiId): Collection
    {
        ReservasiMeja::bersihkanKedaluwarsa();

        return Meja::query()
            ->whereKeyNot($kecualiId)
            ->where('status', self::STATUS_KOSONG)
            ->whereDoesntHave('pesanan', fn ($q) => $q->whereIn('status_pesanan', ['baru', 'diproses']))
            ->whereDoesntHave('reservasi', fn ($q) => $q->aktif())
            ->orderBy('nomor_meja')
            ->get();
    }

    public function urlScan(Meja $meja): string
    {
        return route('meja.scan', ['meja' => $meja->qr_token]);
    }

    private function pesananMilik(Pengguna $pengguna, ?Pelanggan $pelanggan, Pesanan $pesanan): bool
    {
        if ((int) $pesanan->id_pengguna === (int) $pengguna->id_pengguna) {
            return true;
        }

        return $pelanggan
            && (int) $pesanan->id_pelanggan === (int) $pelanggan->id_pelanggan;
    }
}
