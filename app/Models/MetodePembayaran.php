<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model tabel `metode_pembayaran` sesuai ERD (PK: id_metode).
 */
class MetodePembayaran extends Model
{
    protected $table = 'metode_pembayaran';

    protected $primaryKey = 'id_metode';

    public $timestamps = false;

    protected $fillable = [
        'kode_metode',
        'nama_metode',
        'ada_kembalian',
    ];

    protected function casts(): array
    {
        return [
            'ada_kembalian' => 'boolean',
        ];
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'id_metode', 'id_metode');
    }
}
