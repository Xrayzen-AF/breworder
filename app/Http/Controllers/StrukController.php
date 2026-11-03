<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\SettingController;
use App\Models\Pesanan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Struk pembayaran 80mm (thermal).
 * Bisa dilihat kasir/admin dan pelanggan pemilik pesanan.
 */
class StrukController extends Controller
{
    public function show(Request $request, Pesanan $pesanan): View
    {
        $pesanan->load([
            'detailPesanan.produk',
            'meja',
            'pelanggan',
            'pengguna',
            'transaksi.metodePembayaran',
            'promo',
        ]);

        $pengguna = $request->user();

        $staf = $pengguna?->isKasir() || $pengguna?->isAdmin();
        $pemilik = $pengguna && (
            (int) $pesanan->id_pengguna === (int) $pengguna->id_pengguna
            || ($pengguna->nomor_telepon
                && $pesanan->pelanggan
                && $pesanan->pelanggan->nomor_telepon === $pengguna->nomor_telepon)
        );

        abort_unless($staf || $pemilik, 403, 'Anda tidak berhak melihat struk ini.');

        // Nomor antrian harian: urutan pesanan pada tanggal yang sama.
        $nomorAntrian = Pesanan::whereDate('waktu_pesan', $pesanan->waktu_pesan?->toDateString() ?? today())
            ->where('id_pesanan', '<=', $pesanan->id_pesanan)
            ->count();

        return view('struk.show', [
            'pesanan' => $pesanan,
            'toko' => SettingController::load(),
            'nomorAntrian' => max(1, $nomorAntrian),
            'bolehCetak' => $staf,
            'autoPrint' => $request->boolean('print'),
        ]);
    }
}
