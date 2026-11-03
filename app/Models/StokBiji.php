<?php

namespace App\Models;

use Database\Factories\StokBijiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model tabel `stok_biji` — inventori bahan baku (biji kopi, susu, sirup,
 * kemasan, dll). Nama tabel dipertahankan agar kompatibel dengan InventoryAgent.
 * Kolom `sisa_kg` dan turunannya menyimpan jumlah dalam satuan `satuan`.
 */
class StokBiji extends Model
{
    /** @use HasFactory<StokBijiFactory> */
    use HasFactory;

    protected $table = 'stok_biji';

    protected $primaryKey = 'id_stok_biji';

    protected $fillable = [
        'nama_biji',
        'kategori',
        'satuan',
        'deskripsi',
        'sisa_kg',
        'threshold_kg',
        'safety_cap_kg',
        'laju_konsumsi_kg_per_jam',
    ];

    protected function casts(): array
    {
        return [
            'sisa_kg' => 'decimal:2',
            'threshold_kg' => 'decimal:2',
            'safety_cap_kg' => 'decimal:2',
            'laju_konsumsi_kg_per_jam' => 'decimal:4',
        ];
    }

    public function logReorder(): HasMany
    {
        return $this->hasMany(LogReorder::class, 'id_stok_biji', 'id_stok_biji');
    }

    /** Apakah stok di bawah threshold (kritis)? */
    public function isKritis(): bool
    {
        return (float) $this->sisa_kg < (float) $this->threshold_kg;
    }
}
