<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kategori;
use App\Models\Produk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Master CRUD tabel `produk` — direferensikan `detail_pesanan` (FK restrict).
 * Produk yang sudah pernah masuk pesanan tidak boleh dihapus; cukup dinonaktifkan.
 */
class MasterProdukController extends Controller
{
    public function index(): View
    {
        return view('admin.master.produk', [
            'produk' => Produk::with('kategori')->orderBy('nama_produk')->paginate(15),
            'kategori' => Kategori::orderBy('nama_kategori')->get(),
            'activeMenu' => 'produk',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $this->validated($request);

        if ($request->hasFile('gambar')) {
            $file = $request->file('gambar');
            $filename = time().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
            $destination = public_path('images/products');
            if (! file_exists($destination)) {
                mkdir($destination, 0755, true);
            }
            $file->move($destination, $filename);
            $v['url_gambar'] = '/images/products/'.$filename;
        }

        Produk::create($v);

        return back()->with('success', "Produk {$v['nama_produk']} ditambahkan.");
    }

    public function update(Request $request, Produk $produk): RedirectResponse
    {
        $v = $this->validated($request, $produk);

        if ($request->hasFile('gambar')) {
            $file = $request->file('gambar');
            $filename = time().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
            $destination = public_path('images/products');
            if (! file_exists($destination)) {
                mkdir($destination, 0755, true);
            }
            $file->move($destination, $filename);

            // Hapus file lama jika berada di dalam /images/products/
            if ($produk->url_gambar && str_starts_with($produk->url_gambar, '/images/products/')) {
                $oldPath = public_path(ltrim($produk->url_gambar, '/'));
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $v['url_gambar'] = '/images/products/'.$filename;
        } else {
            // Tetap gunakan gambar yang sudah ada jika tidak mengunggah file baru
            $v['url_gambar'] = $produk->url_gambar;
        }

        $produk->update($v);

        return back()->with('success', "Produk {$produk->nama_produk} diperbarui.");
    }

    public function destroy(Produk $produk): RedirectResponse
    {
        if ($produk->detailPesanan()->exists()) {
            return back()->with('error', "Produk {$produk->nama_produk} sudah pernah dipesan dan tidak bisa dihapus — matikan via Status Tersedia.");
        }

        $nama = $produk->nama_produk;
        if ($produk->url_gambar && str_starts_with($produk->url_gambar, '/images/products/')) {
            $oldPath = public_path(ltrim($produk->url_gambar, '/'));
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }
        $produk->delete();

        return back()->with('success', "Produk {$nama} dihapus.");
    }

    private function validated(Request $request, ?Produk $produk = null): array
    {
        $uniqueKode = $produk
            ? 'unique:produk,kode_produk,'.$produk->id_produk.',id_produk'
            : 'unique:produk,kode_produk';

        return $request->validate([
            'id_kategori' => ['required', 'integer', 'exists:kategori,id_kategori'],
            'kode_produk' => ['required', 'string', 'max:255', $uniqueKode],
            'nama_produk' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'harga' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'stok' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'gambar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif,avif', 'max:5120'],
            'status_tersedia' => ['nullable', 'boolean'],
        ], [
            'id_kategori.required' => 'Kategori wajib dipilih.',
            'id_kategori.exists' => 'Kategori tidak ditemukan.',
            'kode_produk.required' => 'Kode produk wajib diisi.',
            'kode_produk.unique' => 'Kode produk sudah dipakai.',
            'nama_produk.required' => 'Nama produk wajib diisi.',
            'harga.required' => 'Harga wajib diisi.',
            'gambar.image' => 'File harus berupa gambar.',
            'gambar.mimes' => 'Format gambar harus: jpeg, png, jpg, webp, gif, atau avif.',
            'gambar.max' => 'Ukuran gambar maksimal 5MB.',
        ]) + [
            'status_tersedia' => $request->boolean('status_tersedia', false),
        ];
    }
}
