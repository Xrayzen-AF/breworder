<?php

namespace App\Console\Commands;

use App\Models\ReservasiMeja;
use Illuminate\Console\Command;

/**
 * Menutup sesi meja yang sudah lewat waktu dan membebaskan mejanya.
 * Dijadwalkan tiap menit agar meja kembali "kosong" walau tidak ada
 * staf yang membuka dashboard (pembersihan malas tetap ada di service).
 */
class BersihkanSesiMeja extends Command
{
    protected $signature = 'meja:bersihkan';

    protected $description = 'Tutup sesi meja yang kedaluwarsa dan kosongkan mejanya';

    public function handle(): int
    {
        $jumlah = ReservasiMeja::query()
            ->where('status', 'aktif')
            ->where('berakhir_pada', '<=', now())
            ->count();

        ReservasiMeja::bersihkanKedaluwarsa();

        $this->info($jumlah > 0
            ? "{$jumlah} sesi meja kedaluwarsa dibebaskan."
            : 'Tidak ada sesi meja yang kedaluwarsa.');

        return self::SUCCESS;
    }
}
