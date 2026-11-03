<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model tabel `transaksi` sesuai ERD (PK: id_transaksi).
 * CASH     : total_bayar = uang diterima, kembalian = total_bayar - subtotal.
 * TRANSFER : total_bayar = nominal transfer, kembalian = 0,
 *            bank_atau_ewallet & nomor_referensi diisi.
 */
class Transaksi extends Model
{
    public const STATUS_PEMBAYARAN = ['menunggu', 'lunas', 'gagal'];

    protected $table = 'transaksi';

    protected $primaryKey = 'id_transaksi';

    public $timestamps = false;

    protected $fillable = [
        'id_pesanan',
        'id_metode',
        'subtotal',
        'total_bayar',
        'kembalian',
        'bank_atau_ewallet',
        'nomor_referensi',
        'midtrans_order_id',
        'midtrans_payment_type',
        'midtrans_payload',
        'midtrans_expires_at',
        'status_pembayaran',
        'waktu_pembayaran',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total_bayar' => 'decimal:2',
            'kembalian' => 'decimal:2',
            'midtrans_payload' => 'array',
            'midtrans_expires_at' => 'datetime',
            'waktu_pembayaran' => 'datetime',
        ];
    }

    public function pesanan(): BelongsTo
    {
        return $this->belongsTo(Pesanan::class, 'id_pesanan', 'id_pesanan');
    }

    public function metodePembayaran(): BelongsTo
    {
        return $this->belongsTo(MetodePembayaran::class, 'id_metode', 'id_metode');
    }
}
