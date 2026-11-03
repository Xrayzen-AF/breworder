<?php

namespace App\Http\Controllers;

use App\Models\Meja;
use App\Services\MejaQrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman yang terbuka setelah pelanggan scan QR fisik di meja.
 * QR hanya menunjuk meja; identitas pelanggan tetap dari akun login,
 * atau dari kode booking bila pelanggan tidak bisa login.
 */
class MejaQrController extends Controller
{
    public function __construct(private readonly MejaQrService $mejaQr) {}

    public function show(Request $request, Meja $meja): View
    {
        $idTerverifikasi = $request->session()->get($this->kunciVerifikasi($meja));
        $idTerverifikasi = is_numeric($idTerverifikasi) ? (int) $idTerverifikasi : null;

        $state = $this->mejaQr->status($meja, $request->user(), $idTerverifikasi);

        return view('pelanggan.meja-scan', [
            'meja' => $meja,
            'state' => $state,
            'mejaKosong' => $this->mejaQr->mejaKosong($meja->id_meja),
            // Sesi tamu yang lolos verifikasi kode (bukan lewat akun login).
            'terverifikasi' => $state['kode'] === MejaQrService::KODE_MILIK_SENDIRI
                && ! $request->user(),
        ]);
    }

    /**
     * Verifikasi kode booking untuk tamu yang tidak bisa login.
     * Dibatasi throttle di rute agar kode tidak bisa ditebak berulang.
     */
    public function verifikasi(Request $request, Meja $meja): RedirectResponse
    {
        $validated = $request->validate([
            'kode_booking' => ['required', 'string', 'max:12'],
        ], [
            'kode_booking.required' => 'Kode booking wajib diisi.',
        ]);

        $reservasi = $this->mejaQr->verifikasiKode($meja, $validated['kode_booking']);

        $request->session()->put($this->kunciVerifikasi($meja), $reservasi->id_reservasi);

        return redirect()
            ->route('meja.scan', ['meja' => $meja->qr_token])
            ->with('success', "Kode booking cocok. Meja {$meja->nomor_meja} tercatat atas nama Anda.");
    }

    public function checkin(Request $request, Meja $meja): RedirectResponse
    {
        $reservasi = $this->mejaQr->klaim($meja, $request->user());

        return redirect()
            ->route('meja.scan', ['meja' => $meja->qr_token])
            ->with('success', "Check-in berhasil. Meja {$meja->nomor_meja} sekarang atas nama Anda (kode {$reservasi->kode_booking}).");
    }

    public function lepas(Request $request, Meja $meja): RedirectResponse
    {
        $this->mejaQr->lepas($meja, $request->user());

        return redirect()
            ->route('meja.scan', ['meja' => $meja->qr_token])
            ->with('success', "Terima kasih. Meja {$meja->nomor_meja} sudah dilepas.");
    }

    private function kunciVerifikasi(Meja $meja): string
    {
        return 'meja_verifikasi_'.$meja->id_meja;
    }
}
