<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman admin untuk tabel `pesanan` — tabel TRANSAKSI, jadi di sini
 * sengaja TIDAK ada create/edit isi. Yang boleh: lihat daftar + detail,
 * dorong status lewat state machine (baru → diproses → selesai),
 * dan batalkan (rollback stok & poin via Pesanan::batalkan()).
 */
class OrderManagementController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $pesanan = Pesanan::with(['detailPesanan.produk:id_produk,nama_produk', 'meja:id_meja,nomor_meja', 'transaksi'])
            ->when(in_array($status, Pesanan::STATUS_PESANAN, true), fn ($q) => $q->where('status_pesanan', $status))
            ->orderByDesc('waktu_pesan')
            ->paginate(10)
            ->withQueryString();

        $hitung = [];
        foreach (Pesanan::STATUS_PESANAN as $s) {
            $hitung[$s] = $s === $status
                ? $pesanan->total()
                : Pesanan::where('status_pesanan', $s)->count();
        }

        return view('admin.orders.index', [
            'pesanan' => $pesanan,
            'hitung' => $hitung,
            'statusAktif' => $status,
            'statuses' => Pesanan::STATUS_PESANAN,
            'transisi' => Pesanan::TRANSISI_STATUS,
            'activeMenu' => 'orders',
        ]);
    }

    public function updateStatus(Request $request, Pesanan $pesanan): RedirectResponse
    {
        $v = $request->validate([
            'status_pesanan' => ['required', 'in:'.implode(',', Pesanan::STATUS_PESANAN)],
        ]);

        $tujuan = $v['status_pesanan'];

        if ($tujuan === 'selesai' && $pesanan->status_pesanan === 'baru') {
            if (! $pesanan->sudahLunas()) {
                return back()->with('error', 'Pesanan belum dibayar. Lunasi terlebih dahulu.');
            }
            $asal = $pesanan->status_pesanan;
            $pesanan->update(['status_pesanan' => 'selesai']);
            Pesanan::sinkronMeja($pesanan->id_meja);

            return back()->with('success', "Pesanan {$pesanan->nomor_pesanan}: {$asal} → selesai.");
        }

        if (! Pesanan::bolehTransisi($pesanan->status_pesanan, $tujuan)) {
            return back()->with('error', "Status {$pesanan->status_pesanan} tidak bisa langsung jadi {$tujuan}.");
        }

        $asal = $pesanan->status_pesanan;
        $pesanan->update(['status_pesanan' => $tujuan]);
        Pesanan::sinkronMeja($pesanan->id_meja);

        return back()->with('success', "Pesanan {$pesanan->nomor_pesanan}: {$asal} → {$tujuan}.");
    }

    public function destroy(Pesanan $pesanan): RedirectResponse
    {
        $nomor = $pesanan->nomor_pesanan;
        $pesanan->batalkan();

        return back()->with('success', "Pesanan {$nomor} dibatalkan — stok & poin dikembalikan.");
    }
}
