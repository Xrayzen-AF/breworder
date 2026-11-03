<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model tabel `produk` sesuai ERD (PK: id_produk).
 */
class Produk extends Model
{
    protected $table = 'produk';

    protected $primaryKey = 'id_produk';

    public $timestamps = false;

    protected $fillable = [
        'id_kategori',
        'kode_produk',
        'nama_produk',
        'deskripsi',
        'harga',
        'stok',
        'status_tersedia',
        'url_gambar',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'stok' => 'integer',
            'status_tersedia' => 'boolean',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'id_kategori', 'id_kategori');
    }

    public function detailPesanan(): HasMany
    {
        return $this->hasMany(DetailPesanan::class, 'id_produk', 'id_produk');
    }
}
