<?php

namespace App\Models;

use Database\Factories\PesananFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * Model tabel `pesanan` sesuai ERD (PK: id_pesanan, waktu: waktu_pesan).
 */
class Pesanan extends Model
{
    /** @use HasFactory<PesananFactory> */
    use HasFactory;

    public const TIPE_PESANAN = ['dine-in', 'takeaway', 'delivery'];

    public const STATUS_PESANAN = ['baru', 'diproses', 'selesai', 'dibatalkan'];

    /**
     * State machine alur dapur:
     * baru -> diproses / dibatalkan
     * diproses -> selesai / dibatalkan
     * selesai & dibatalkan = terminal.
     * Lompat baru -> selesai dan mundur diproses -> baru DILARANG.
     */
    public const TRANSISI_STATUS = [
        'baru' => ['diproses', 'dibatalkan'],
        'diproses' => ['selesai', 'dibatalkan'],
        'selesai' => [],
        'dibatalkan' => [],
    ];

    /** 1 poin tiap Rp 10.000 belanja bersih. */
    public const RUPIAH_PER_POIN = 10000;

    protected $table = 'pesanan';

    protected $primaryKey = 'id_pesanan';

    const CREATED_AT = 'waktu_pesan';

    const UPDATED_AT = null;

    protected $fillable = [
        'nomor_pesanan',
        'id_pengguna',
        'id_pelanggan',
        'id_meja',
        'id_promo',
        'tipe_pesanan',
        'status_pesanan',
        'total_harga',
        'diskon',
        'biaya_layanan',
        'poin_dipakai',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'total_harga' => 'decimal:2',
            'diskon' => 'decimal:2',
            'biaya_layanan' => 'decimal:2',
            'poin_dipakai' => 'integer',
            'waktu_pesan' => 'datetime',
        ];
    }

    /** Pembuat/penangan pesanan: kasir untuk tiket POS, pelanggan ybs untuk order mandiri. */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'id_pengguna', 'id_pengguna');
    }

    /** Boleh NULL untuk non-member. */
    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'id_pelanggan', 'id_pelanggan');
    }

    /** Boleh NULL untuk takeaway/delivery. */
    public function meja(): BelongsTo
    {
        return $this->belongsTo(Meja::class, 'id_meja', 'id_meja');
    }

    /** Boleh NULL jika tanpa promo. */
    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class, 'id_promo', 'id_promo');
    }

    public function detailPesanan(): HasMany
    {
        return $this->hasMany(DetailPesanan::class, 'id_pesanan', 'id_pesanan');
    }

    /** Satu pesanan dibayar melalui satu transaksi. */
    public function transaksi(): HasOne
    {
        return $this->hasOne(Transaksi::class, 'id_pesanan', 'id_pesanan');
    }

    public static function generateNomorPesanan(): string
    {
        do {
            $number = 'PSN-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        } while (self::where('nomor_pesanan', $number)->exists());

        return $number;
    }

    /** Cek apakah pindah status diizinkan oleh state machine. */
    public static function bolehTransisi(string $dari, string $ke): bool
    {
        return in_array($ke, self::TRANSISI_STATUS[$dari] ?? [], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status_pesanan, ['selesai', 'dibatalkan'], true);
    }

    public function sudahLunas(): bool
    {
        return $this->relationLoaded('transaksi')
            ? (bool) ($this->transaksi && $this->transaksi->status_pembayaran === 'lunas')
            : $this->transaksi()->where('status_pembayaran', 'lunas')->exists();
    }

    /**
     * Batalkan pesanan: kembalikan stok + poin, tandai transaksi gagal,
     * bebaskan meja bila tak ada pesanan aktif lain. Aman dipanggil
     * berulang (idempoten untuk stok bila sudah dibatalkan).
     */
    public function batalkan(): void
    {
        if ($this->status_pesanan === 'dibatalkan') {
            return;
        }

        if ($this->status_pesanan === 'selesai') {
            abort(422, 'Pesanan yang sudah selesai tidak dapat dibatalkan.');
        }

        if ($this->sudahLunas()) {
            abort(422, 'Pesanan yang sudah dibayar tidak dapat dibatalkan. Hubungi kasir untuk refund.');
        }

        DB::transaction(function () {
            $pesanan = self::with('detailPesanan')->lockForUpdate()->find($this->id_pesanan);

            foreach ($pesanan->detailPesanan as $detail) {
                Produk::where('id_produk', $detail->id_produk)->increment('stok', $detail->jumlah);
            }

            if ($pesanan->id_pelanggan && (int) $pesanan->poin_dipakai > 0) {
                Pelanggan::where('id_pelanggan', $pesanan->id_pelanggan)
                    ->increment('poin_loyalitas', (int) $pesanan->poin_dipakai);
            }

            $transaksi = $pesanan->transaksi()->lockForUpdate()->first();
            if ($transaksi && $transaksi->status_pembayaran !== 'lunas') {
                $transaksi->update(['status_pembayaran' => 'gagal']);
            }

            $pesanan->update(['status_pesanan' => 'dibatalkan']);

            self::sinkronMeja($pesanan->id_meja);
        });

        $this->refresh();
    }

    /**
     * Tandai meja terisi bila masih ada pesanan aktif atau sesi check-in
     * (reservasi) yang berlaku; kosongkan bila keduanya sudah tidak ada.
     * Mendukung banyak pesanan dalam satu meja (tidak double-booking strict).
     *
     * Sesi meja juga disesuaikan di sini: diperpanjang selama ada pesanan
     * aktif, lalu hanya diberi tenggang TENGGANG_MENIT setelah pesanan
     * terakhir selesai — sehingga meja kembali kosong ketika pelanggan
     * sudah pulang, tanpa harus menunggu timer check-in habis.
     */
    public static function sinkronMeja(?int $idMeja): void
    {
        if (! $idMeja) {
            return;
        }

        $masihAktif = self::where('id_meja', $idMeja)
            ->whereIn('status_pesanan', ['baru', 'diproses'])
            ->exists();

        $sudahAdaPesananUsai = self::where('id_meja', $idMeja)
            ->whereIn('status_pesanan', ['selesai', 'dibatalkan'])
            ->exists();

        $reservasi = ReservasiMeja::where('id_meja', $idMeja)->aktif()->latest('dibuat_pada')->first();

        $adaReservasi = $reservasi
            ? $reservasi->sesuaikanBatasSesi($masihAktif, $sudahAdaPesananUsai)
            : false;

        Meja::where('id_meja', $idMeja)->update([
            'status' => ($masihAktif || $adaReservasi) ? 'terisi' : 'kosong',
        ]);
    }
}
