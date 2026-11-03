<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master CRUD tabel `promo` — voucher diskon yang dipakai di checkout
 * (FK nullOnDelete dari pesanan). Promo yang sudah dipakai pesanan
 * tetap boleh dihapus (pesanan tidak ikut hilang), tapi kita beri tahu.
 */
class MasterPromoController extends Controller
{
    public function index(): View
    {
        return view('admin.master.promo', [
            'promo' => Promo::withCount('pesanan')->orderBy('kode_promo')->get(),
            'tipeDiskon' => Promo::TIPE_DISKON,
            'activeMenu' => 'promo',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $this->validated($request);

        Promo::create($v);

        return back()->with('success', "Promo {$v['kode_promo']} ditambahkan.");
    }

    public function update(Request $request, Promo $promo): RedirectResponse
    {
        $v = $this->validated($request, $promo);

        $promo->update($v);

        return back()->with('success', "Promo {$promo->kode_promo} diperbarui.");
    }

    public function destroy(Promo $promo): RedirectResponse
    {
        $kode = $promo->kode_promo;
        $dipakai = $promo->pesanan()->count();

        $promo->delete();

        $pesan = "Promo {$kode} dihapus.".($dipakai > 0 ? " Riwayat {$dipakai} pesanan yang memakainya tetap aman." : '');

        return back()->with('success', $pesan);
    }

    private function validated(Request $request, ?Promo $promo = null): array
    {
        $unique = $promo
            ? 'unique:promo,kode_promo,'.$promo->id_promo.',id_promo'
            : 'unique:promo,kode_promo';

        return $request->validate([
            'kode_promo' => ['required', 'string', 'max:255', $unique],
            'tipe_diskon' => ['required', 'in:persen,nominal'],
            'nilai_diskon' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'berlaku_mulai' => ['nullable', 'date', 'before_or_equal:berlaku_sampai'],
            'berlaku_sampai' => ['nullable', 'date', 'after_or_equal:berlaku_mulai'],
            'status_aktif' => ['nullable', 'boolean'],
        ], [
            'kode_promo.required' => 'Kode promo wajib diisi.',
            'kode_promo.unique' => 'Kode promo sudah dipakai.',
            'tipe_diskon.required' => 'Tipe diskon wajib dipilih.',
            'nilai_diskon.required' => 'Nilai diskon wajib diisi.',
            'berlaku_sampai.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum mulai.',
        ]) + [
            'status_aktif' => $request->boolean('status_aktif', false),
        ];
    }
}
