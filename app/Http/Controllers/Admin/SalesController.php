<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetailPesanan;
use App\Models\Pesanan;
use App\Models\Transaksi;
use App\Services\Agents\SalesAgent;
use App\Services\Agents\StaffingAgent;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Sales & Revenue — laporan finansial admin (baca saja).
 * Sumber angka: SalesAgent + tabel transaksi/pesanan.
 */
class SalesController extends Controller
{
    public function index(SalesAgent $sales, StaffingAgent $staffing): View
    {
        $summary = $sales->financialSummary();
        $topBrews = $sales->topSellingBrews(5);
        $telemetry = $staffing->trafficTelemetry();

        // Omzet 7 hari terakhir (lunas saja).
        $driver = Transaksi::query()->getConnection()->getDriverName();
        $tglExpr = $driver === 'sqlite'
            ? 'date(waktu_pembayaran)'
            : 'DATE(waktu_pembayaran)';

        $harian = Transaksi::query()
            ->selectRaw("{$tglExpr} as tanggal, SUM(total_bayar) as omzet, COUNT(*) as transaksi")
            ->where('status_pembayaran', 'lunas')
            ->where('waktu_pembayaran', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get()
            ->keyBy('tanggal');

        $grafik = [];
        for ($i = 6; $i >= 0; $i--) {
            $tgl = now()->subDays($i)->format('Y-m-d');
            $grafik[] = [
                'tanggal' => $tgl,
                'label' => now()->subDays($i)->format('d M'),
                'omzet' => (float) ($harian[$tgl]->omzet ?? 0),
                'transaksi' => (int) ($harian[$tgl]->transaksi ?? 0),
            ];
        }
        $maxOmzet = max(1, collect($grafik)->max('omzet'));

        // Breakdown metode pembayaran hari ini.
        $perMetode = Transaksi::query()
            ->join('metode_pembayaran', 'metode_pembayaran.id_metode', '=', 'transaksi.id_metode')
            ->select('metode_pembayaran.nama_metode')
            ->selectRaw('COUNT(*) as transaksi, SUM(transaksi.total_bayar) as omzet')
            ->where('transaksi.status_pembayaran', 'lunas')
            ->whereDate('transaksi.waktu_pembayaran', today())
            ->groupBy('metode_pembayaran.nama_metode')
            ->orderByDesc('omzet')
            ->get();

        // Status pesanan hari ini.
        $perStatus = Pesanan::query()
            ->select('status_pesanan', DB::raw('COUNT(*) as total'))
            ->whereDate('waktu_pesan', today())
            ->groupBy('status_pesanan')
            ->pluck('total', 'status_pesanan');

        // Margin per produk (HPP = 25% harga jual, konsisten dengan SalesAgent).
        $marginProduk = DetailPesanan::query()
            ->join('produk', 'produk.id_produk', '=', 'detail_pesanan.id_produk')
            ->select('produk.nama_produk')
            ->selectRaw('SUM(detail_pesanan.jumlah) as cups, SUM(detail_pesanan.subtotal) as pendapatan')
            ->whereHas('pesanan', fn ($q) => $q->where('status_pesanan', 'selesai'))
            ->groupBy('produk.id_produk', 'produk.nama_produk')
            ->orderByDesc('pendapatan')
            ->limit(8)
            ->get()
            ->map(fn ($r) => [
                'nama' => $r->nama_produk,
                'cups' => (int) $r->cups,
                'pendapatan' => (float) $r->pendapatan,
                'margin_pct' => 75.0,
                'at_risk' => (int) $r->cups < SalesAgent::VOLUME_AT_RISK_THRESHOLD,
            ]);

        return view('admin.sales.index', [
            'summary' => $summary,
            'topBrews' => $topBrews,
            'telemetry' => $telemetry,
            'grafik' => $grafik,
            'maxOmzet' => $maxOmzet,
            'perMetode' => $perMetode,
            'perStatus' => $perStatus,
            'marginProduk' => $marginProduk,
            'activeMenu' => 'sales',
        ]);
    }
}
