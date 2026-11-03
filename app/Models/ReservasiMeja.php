<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sesi/reservasi meja yang dibuat saat pelanggan melakukan check-in
 * lewat QR di meja. Kepemilikan meja ditentukan oleh `id_pelanggan`,
 * bukan password meja — akun yang mencocokkan reservasi otomatis
 * dianggap sebagai pemilik.
 */
class ReservasiMeja extends Model
{
    public const STATUS = ['aktif', 'selesai', 'kedaluwarsa', 'dibatalkan'];

    /** Lama sesi meja yang diperpanjang setiap ada aktivitas pesanan. */
    public const DURASI_MENIT = 90;

    /** Tenggang setelah pesanan terakhir selesai sebelum meja dilepas. */
    public const TENGGANG_MENIT = 15;

    /** Batas keras satu sesi sejak check-in, agar meja tidak terkunci selamanya. */
    public const MAKS_SESI_MENIT = 180;

    protected $table = 'reservasi_meja';

    protected $primaryKey = 'id_reservasi';

    const CREATED_AT = 'dibuat_pada';

    const UPDATED_AT = null;

    protected $fillable = [
        'id_meja',
        'id_pelanggan',
        'kode_booking',
        'status',
        'dicek_in_pada',
        'berakhir_pada',
    ];

    protected function casts(): array
    {
        return [
            'dicek_in_pada' => 'datetime',
            'berakhir_pada' => 'datetime',
            'dibuat_pada' => 'datetime',
        ];
    }

    public function meja(): BelongsTo
    {
        return $this->belongsTo(Meja::class, 'id_meja', 'id_meja');
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'id_pelanggan', 'id_pelanggan');
    }

    /** Reservasi yang masih berlaku (aktif dan belum lewat waktu). */
    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif')->where('berakhir_pada', '>', now());
    }

    public function sudahCheckIn(): bool
    {
        return $this->dicek_in_pada !== null;
    }

    /**
     * Sesuaikan batas sesi dengan aktivitas meja, lalu tutup bila sudah lewat.
     *
     * - Ada pesanan aktif (`baru`/`diproses`)  → sesi diperpanjang DURASI_MENIT
     *   dari sekarang, dibatasi MAKS_SESI_MENIT sejak check-in.
     * - Semua pesanan sudah usai              → sisa TENGGANG_MENIT saja
     *   (tidak pernah diperpanjang, jadi aman dipanggil berulang).
     * - Belum ada pesanan sama sekali         → sesi check-in dibiarkan berjalan.
     *
     * @return bool true bila sesi masih berlaku setelah penyesuaian.
     */
    public function sesuaikanBatasSesi(bool $adaPesananAktif, bool $sudahAdaPesananUsai): bool
    {
        $sekarang = now();

        if (! $adaPesananAktif && ! $sudahAdaPesananUsai) {
            return $this->berakhir_pada->gt($sekarang);
        }

        if ($adaPesananAktif) {
            $batas = $sekarang->copy()->addMinutes(self::DURASI_MENIT);

            // Sesi yang masih panjang tidak dipendekkan oleh pesanan baru.
            if ($this->berakhir_pada->gt($batas)) {
                $batas = $this->berakhir_pada->copy();
            }

            $maksimal = ($this->dicek_in_pada ?? $this->dibuat_pada ?? $sekarang)
                ->copy()->addMinutes(self::MAKS_SESI_MENIT);

            if ($batas->gt($maksimal)) {
                $batas = $maksimal;
            }
        } else {
            $batas = $sekarang->copy()->addMinutes(self::TENGGANG_MENIT);

            // Sesi yang sudah lebih pendek dibiarkan, tidak diperpanjang.
            if ($this->berakhir_pada->lt($batas)) {
                $batas = $this->berakhir_pada->copy();
            }
        }

        if ($batas->lte($sekarang)) {
            $this->update(['status' => 'kedaluwarsa']);

            return false;
        }

        if (! $this->berakhir_pada->equalTo($batas)) {
            $this->update(['berakhir_pada' => $batas]);
        }

        return true;
    }

    /** Kode pendek untuk disebutkan ke staf (fallback bila akun bermasalah). */
    public static function generateKodeBooking(): string
    {
        do {
            $kode = strtoupper(Str::random(6));
        } while (self::where('kode_booking', $kode)->exists());

        return $kode;
    }

    /**
     * Tutup reservasi yang sudah lewat waktu dan bebaskan mejanya bila
     * tidak ada pesanan aktif. Dipanggil malas saat meja dibuka/dipolling,
     * dan tiap menit lewat perintah `meja:bersihkan` agar status meja tidak
     * basi saat tidak ada yang membuka dashboard.
     */
    public static function bersihkanKedaluwarsa(): void
    {
        $kedaluwarsa = self::query()
            ->where('status', 'aktif')
            ->where('berakhir_pada', '<=', now())
            ->get();

        foreach ($kedaluwarsa as $reservasi) {
            DB::transaction(function () use ($reservasi) {
                $reservasi->update(['status' => 'kedaluwarsa']);
                Pesanan::sinkronMeja($reservasi->id_meja);
            });
        }
    }
}
