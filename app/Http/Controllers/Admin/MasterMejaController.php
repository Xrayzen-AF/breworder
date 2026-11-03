<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meja;
use App\Services\MejaQrService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master CRUD tabel `meja` — dipakai pesanan dine-in (FK nullOnDelete).
 * Status sinkron otomatis dengan Pesanan::sinkronMeja(); admin hanya
 * mengatur identitas meja (nomor & kapasitas).
 */
class MasterMejaController extends Controller
{
    public function index(): View
    {
        return view('admin.master.meja', [
            'meja' => Meja::withCount(['pesanan' => fn ($q) => $q->whereIn('status_pesanan', ['baru', 'diproses'])])
                ->orderBy('nomor_meja')
                ->get(),
            'statuses' => Meja::STATUS,
            'activeMenu' => 'meja',
        ]);
    }

    /**
     * Lembar QR untuk ditempel di tiap meja. QR hanya berisi URL scan
     * meja tersebut; token acak mencegah meja lain bisa ditebak.
     */
    public function qr(MejaQrService $mejaQr): View
    {
        $renderer = new ImageRenderer(
            new RendererStyle(260, 2),
            new SvgImageBackEnd,
        );
        $writer = new Writer($renderer);

        $meja = Meja::orderBy('nomor_meja')->get();

        return view('admin.master.meja-qr', [
            'meja' => $meja,
            'qr' => $meja->mapWithKeys(fn (Meja $m) => [
                $m->id_meja => $writer->writeString($mejaQr->urlScan($m)),
            ]),
            'activeMenu' => 'meja',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'nomor_meja' => ['required', 'string', 'max:255', 'unique:meja,nomor_meja'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:1000'],
        ], [
            'nomor_meja.required' => 'Nomor meja wajib diisi.',
            'nomor_meja.unique' => 'Nomor meja sudah dipakai.',
            'kapasitas.required' => 'Kapasitas wajib diisi.',
        ]);

        Meja::create($v + ['status' => 'kosong']);

        return back()->with('success', "Meja {$v['nomor_meja']} ditambahkan.");
    }

    public function update(Request $request, Meja $meja): RedirectResponse
    {
        $v = $request->validate([
            'nomor_meja' => ['required', 'string', 'max:255', 'unique:meja,nomor_meja,'.$meja->id_meja.',id_meja'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $meja->update($v);

        return back()->with('success', "Meja {$meja->nomor_meja} diperbarui.");
    }

    public function destroy(Meja $meja): RedirectResponse
    {
        if ($meja->pesanan()->exists()) {
            return back()->with('error', "Meja {$meja->nomor_meja} punya riwayat pesanan dan tidak bisa dihapus.");
        }

        $nomor = $meja->nomor_meja;
        $meja->delete();

        return back()->with('success', "Meja {$nomor} dihapus.");
    }
}
