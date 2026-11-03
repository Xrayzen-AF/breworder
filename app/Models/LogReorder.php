<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model tabel `log_reorder` — catatan dispatch reorder ke roastery.
 */
class LogReorder extends Model
{
    public const STATUS = ['terkirim', 'gagal'];

    protected $table = 'log_reorder';

    protected $primaryKey = 'id_log_reorder';

    public $timestamps = false;

    protected $fillable = [
        'id_stok_biji',
        'destination',
        'order_quantity_kg',
        'status',
        'response_reference',
        'waktu_dispatch',
    ];

    protected function casts(): array
    {
        return [
            'order_quantity_kg' => 'decimal:2',
            'waktu_dispatch' => 'datetime',
        ];
    }

    public function stokBiji(): BelongsTo
    {
        return $this->belongsTo(StokBiji::class, 'id_stok_biji', 'id_stok_biji');
    }
}
