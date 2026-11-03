<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model tabel `kategori` sesuai ERD (PK: id_kategori).
 */
class Kategori extends Model
{
    protected $table = 'kategori';

    protected $primaryKey = 'id_kategori';

    public $timestamps = false;

    protected $fillable = [
        'nama_kategori',
        'deskripsi',
        'status_aktif',
    ];

    protected function casts(): array
    {
        return [
            'status_aktif' => 'boolean',
        ];
    }

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class, 'id_kategori', 'id_kategori');
    }
}
