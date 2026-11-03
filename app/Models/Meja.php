<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Model tabel `meja` sesuai ERD (PK: id_meja).
 */
class Meja extends Model
{
    public const STATUS = ['kosong', 'terisi', 'dipesan'];

    protected $table = 'meja';

    protected $primaryKey = 'id_meja';

    public $timestamps = false;

    protected $fillable = [
        'nomor_meja',
        'kapasitas',
        'status',
        'qr_token',
    ];

    protected function casts(): array
    {
        return [
            'kapasitas' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Setiap meja baru langsung punya token QR sendiri.
        static::creating(function (Meja $meja) {
            if (blank($meja->qr_token)) {
                $meja->qr_token = self::generateQrToken();
            }
        });
    }

    public static function generateQrToken(): string
    {
        do {
            $token = Str::random(40);
        } while (self::where('qr_token', $token)->exists());

        return $token;
    }

    public function pesanan(): HasMany
    {
        return $this->hasMany(Pesanan::class, 'id_meja', 'id_meja');
    }

    public function reservasi(): HasMany
    {
        return $this->hasMany(ReservasiMeja::class, 'id_meja', 'id_meja');
    }

    /** Sesi check-in yang sedang berlaku (untuk nama pemesan & sisa waktu). */
    public function reservasiAktif(): HasOne
    {
        return $this->hasOne(ReservasiMeja::class, 'id_meja', 'id_meja')
            ->where('status', 'aktif')
            ->where('berakhir_pada', '>', now())
            ->latestOfMany('id_reservasi');
    }
}
