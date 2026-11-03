<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetailPesanan;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\StokBiji;
use App\Models\Transaksi;
use Illuminate\View\View;

/**
 * Ringkasan admin — semua angka diambil dari database (tanpa data contoh).
 */
class OverviewController extends Controller
{
    public function index(): View
    {
        $omzetHariIni = (float) Transaksi::query()
            ->where('status_pembayaran', 'lunas')
            ->whereDate('waktu_pembayaran', today())
            ->sum('total_bayar');

        $pesananSelesaiHariIni = Pesanan::query()
            ->where('status_pesanan', 'selesai')
            ->whereDate('waktu_pesan', today())
            ->count();

        $bahan = StokBiji::query()->orderBy('nama_biji')->get();
        $stokKritis = $bahan->filter(fn (StokBiji $b) => $b->isKritis())->values();
        $kesehatanStok = $bahan->count() > 0
            ? (int) round(($bahan->count() - $stokKritis->count()) / $bahan->count() * 100)
            : 100;

        // Penjualan per jam hari ini, dihitung di PHP agar kompatibel
        // dengan MySQL maupun SQLite (test).
        $perJam = Transaksi::query()
            ->where('status_pembayaran', 'lunas')
            ->whereDate('waktu_pembayaran', today())
            ->get(['waktu_pembayaran', 'total_bayar'])
            ->groupBy(fn (Transaksi $t) => $t->waktu_pembayaran?->format('H'))
            ->map(fn ($rows) => (float) $rows->sum('total_bayar'));

        $grafik = collect(range(8, 20))->map(function (int $jam) use ($perJam) {
            $kunci = str_pad((string) $jam, 2, '0', STR_PAD_LEFT);

            return [
                'label' => $kunci.':00',
                'omzet' => (float) ($perJam[$kunci] ?? 0),
            ];
        });

        $maxJam = max(1, (float) $grafik->max('omzet'));
        $jamTersibuk = $grafik->sortByDesc('omzet')->first();

        $topProduk = DetailPesanan::query()
            ->join('produk', 'produk.id_produk', '=', 'detail_pesanan.id_produk')
            ->select('produk.nama_produk')
            ->selectRaw('SUM(detail_pesanan.jumlah) as terjual, SUM(detail_pesanan.subtotal) as pendapatan')
            ->whereHas('pesanan', fn ($q) => $q
                ->where('status_pesanan', 'selesai')
                ->whereDate('waktu_pesan', today()))
            ->groupBy('produk.id_produk', 'produk.nama_produk')
            ->orderByDesc('terjual')
            ->limit(3)
            ->get()
            ->map(fn ($row) => [
                'nama' => $row->nama_produk,
                'terjual' => (int) $row->terjual,
                'pendapatan' => (float) $row->pendapatan,
            ]);

        $staff = Pengguna::query()
            ->whereIn('peran', ['kasir', 'barista'])
            ->where('status_aktif', true)
            ->orderBy('nama_lengkap')
            ->limit(4)
            ->get();

        return view('admin.dashboard', [
            'omzetHariIni' => $omzetHariIni,
            'pesananSelesaiHariIni' => $pesananSelesaiHariIni,
            'totalPelanggan' => Pengguna::where('peran', 'pelanggan')->count(),
            'totalKasir' => Pengguna::where('peran', 'kasir')->count(),
            'totalBarista' => Pengguna::where('peran', 'barista')->count(),
            'totalUsers' => Pengguna::count(),
            'totalBahan' => $bahan->count(),
            'stokKritis' => $stokKritis,
            'kesehatanStok' => $kesehatanStok,
            'grafik' => $grafik,
            'maxJam' => $maxJam,
            'jamTersibuk' => $jamTersibuk,
            'topProduk' => $topProduk,
            'staff' => $staff,
            'activeMenu' => 'dashboard',
        ]);
    }
}
