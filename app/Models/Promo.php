<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model tabel `promo` sesuai ERD (PK: id_promo).
 */
class Promo extends Model
{
    public const TIPE_DISKON = ['persen', 'nominal'];

    protected $table = 'promo';

    protected $primaryKey = 'id_promo';

    public $timestamps = false;

    protected $fillable = [
        'kode_promo',
        'tipe_diskon',
        'nilai_diskon',
        'berlaku_mulai',
        'berlaku_sampai',
        'status_aktif',
    ];

    protected function casts(): array
    {
        return [
            'nilai_diskon' => 'decimal:2',
            'berlaku_mulai' => 'datetime',
            'berlaku_sampai' => 'datetime',
            'status_aktif' => 'boolean',
        ];
    }

    public function pesanan(): HasMany
    {
        return $this->hasMany(Pesanan::class, 'id_promo', 'id_promo');
    }
}
