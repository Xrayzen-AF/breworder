<?php

namespace App\Models;

use Database\Factories\PelangganFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model tabel `pelanggan` sesuai ERD
 * (PK: id_pelanggan, waktu: terdaftar_pada).
 */
class Pelanggan extends Model
{
    /** @use HasFactory<PelangganFactory> */
    use HasFactory;

    protected $table = 'pelanggan';

    protected $primaryKey = 'id_pelanggan';

    const CREATED_AT = 'terdaftar_pada';

    const UPDATED_AT = null;

    protected $fillable = [
        'nama_pelanggan',
        'nomor_telepon',
        'poin_loyalitas',
    ];

    protected function casts(): array
    {
        return [
            'poin_loyalitas' => 'integer',
            'terdaftar_pada' => 'datetime',
        ];
    }

    public function pesanan(): HasMany
    {
        return $this->hasMany(Pesanan::class, 'id_pelanggan', 'id_pelanggan');
    }
}
