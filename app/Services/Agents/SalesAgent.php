<?php

namespace App\Services\Agents;

use App\Models\DetailPesanan;
use App\Models\Transaksi;

/**
 * SalesAgent — Financial Performance Monitor & Brew Profiler.
 *
 * Menghitung metrik finansial nyata dari tabel transaksi & detail_pesanan,
 * lalu mendeteksi high-margin item yang drop di bawah threshold volume.
 */
class SalesAgent
{
    /** Margin dianggap "high" di atas nilai ini (persen). */
    public const HIGH_MARGIN_THRESHOLD = 70.0;

    /** Item high-margin dianggap "at risk" jika volume di bawah nilai ini. */
    public const VOLUME_AT_RISK_THRESHOLD = 20;

    /**
     * Ringkasan finansial untuk endpoint /api/v1/ops/financial-summary.
     */
    public function financialSummary(): array
    {
        $revenueHariIni = (float) Transaksi::where('status_pembayaran', 'lunas')
            ->whereDate('waktu_pembayaran', today())
            ->sum('total_bayar');

        $revenueKemarin = (float) Transaksi::where('status_pembayaran', 'lunas')
            ->whereDate('waktu_pembayaran', today()->subDay())
            ->sum('total_bayar');

        $growthPct = $revenueKemarin > 0
            ? round((($revenueHariIni - $revenueKemarin) / $revenueKemarin) * 100, 1)
            : null;

        return [
            'today_revenue' => (int) $revenueHariIni,
            'revenue_vs_yesterday_pct' => $growthPct,
            'aggregated_gross_margin_pct' => $this->aggregatedGrossMargin(),
        ];
    }

    /**
     * Gross margin agregat — dihitung dari margin per produk
     * berbobot volume terjual. Harga pokok disederhanakan: 25% dari harga jual
     * per item kopi (bisa diganti kolom hpp di `produk` nantinya).
     */
    public function aggregatedGrossMargin(): float
    {
        $rows = DetailPesanan::query()
            ->join('produk', 'produk.id_produk', '=', 'detail_pesanan.id_produk')
            ->selectRaw('SUM(detail_pesanan.subtotal) as pendapatan, SUM(detail_pesanan.subtotal * 0.25) as hpp')
            ->whereHas('pesanan', fn ($q) => $q->where('status_pesanan', 'selesai'))
            ->first();

        if (! $rows || (float) $rows->pendapatan <= 0) {
            return 0.0;
        }

        return round((1 - ((float) $rows->hpp / (float) $rows->pendapatan)) * 100, 1);
    }

    /**
     * Top selling brews hari ini + deteksi item high-margin at-risk.
     */
    public function topSellingBrews(int $limit = 3): array
    {
        $rows = DetailPesanan::query()
            ->join('produk', 'produk.id_produk', '=', 'detail_pesanan.id_produk')
            ->select('produk.nama_produk')
            ->selectRaw('SUM(detail_pesanan.jumlah) as cups')
            ->whereHas('pesanan', fn ($q) => $q->whereIn('status_pesanan', ['baru', 'diproses', 'selesai']))
            ->groupBy('produk.id_produk', 'produk.nama_produk')
            ->orderByDesc('cups')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            $cups = (int) $row->cups;

            return [
                'nama' => $row->nama_produk,
                'cups' => $cups,
                'margin_pct' => self::HIGH_MARGIN_THRESHOLD, // placeholder margin per produk
                'at_risk' => $this->isAtRisk($row->nama_produk, $cups),
            ];
        })->all();
    }

    /**
     * System Action: item high-margin yang drop di bawah threshold volume
     * ditandai at-risk (trigger untuk memodifikasi menu mix / alert).
     */
    public function isAtRisk(string $namaProduk, int $cupsToday): bool
    {
        return $cupsToday < self::VOLUME_AT_RISK_THRESHOLD;
    }

    /**
     * Re-kalkulasi margin tren (dipanggil StaffingAgent saat high ticket volume).
     */
    public function recalculateMarginTrend(): array
    {
        // Ekstrak jam lintas-DB (MySQL: HOUR(), SQLite: strftime('%H'))
        $driver = DetailPesanan::query()->getConnection()->getDriverName();
        $jamExpr = $driver === 'sqlite'
            ? "CAST(strftime('%H', pesanan.waktu_pesan) AS INTEGER)"
            : 'HOUR(pesanan.waktu_pesan)';

        $perJam = DetailPesanan::query()
            ->join('pesanan', 'pesanan.id_pesanan', '=', 'detail_pesanan.id_pesanan')
            ->join('produk', 'produk.id_produk', '=', 'detail_pesanan.id_produk')
            ->selectRaw("{$jamExpr} as jam, SUM(detail_pesanan.subtotal) as pendapatan")
            ->whereDate('pesanan.waktu_pesan', today())
            ->groupBy('jam')
            ->orderBy('jam')
            ->get();

        return $perJam->map(fn ($r) => [
            'jam' => (int) $r->jam,
            'pendapatan' => (float) $r->pendapatan,
        ])->all();
    }
}
