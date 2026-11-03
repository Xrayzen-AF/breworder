<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kategori;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master CRUD tabel `kategori` — induk dari `produk`.
 * Kategori yang masih dipakai produk tidak boleh dihapus (FK restrict).
 */
class MasterKategoriController extends Controller
{
    public function index(): View
    {
        return view('admin.master.kategori', [
            'kategori' => Kategori::withCount('produk')->orderBy('nama_kategori')->paginate(15),
            'activeMenu' => 'kategori',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:255', 'unique:kategori,nama_kategori'],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'status_aktif' => ['nullable', 'boolean'],
        ], [
            'nama_kategori.required' => 'Nama kategori wajib diisi.',
            'nama_kategori.unique' => 'Nama kategori sudah dipakai.',
        ]);

        Kategori::create([
            'nama_kategori' => trim($v['nama_kategori']),
            'deskripsi' => $v['deskripsi'] ?? null,
            'status_aktif' => $request->boolean('status_aktif', true),
        ]);

        return back()->with('success', "Kategori {$v['nama_kategori']} ditambahkan.");
    }

    public function update(Request $request, Kategori $kategori): RedirectResponse
    {
        $v = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:255', 'unique:kategori,nama_kategori,'.$kategori->id_kategori.',id_kategori'],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'status_aktif' => ['nullable', 'boolean'],
        ]);

        $kategori->update([
            'nama_kategori' => trim($v['nama_kategori']),
            'deskripsi' => $v['deskripsi'] ?? null,
            'status_aktif' => $request->boolean('status_aktif', false),
        ]);

        return back()->with('success', "Kategori {$kategori->nama_kategori} diperbarui.");
    }

    public function destroy(Kategori $kategori): RedirectResponse
    {
        if ($kategori->produk()->exists()) {
            return back()->with('error', "Kategori {$kategori->nama_kategori} masih dipakai {$kategori->produk()->count()} produk dan tidak bisa dihapus.");
        }

        $nama = $kategori->nama_kategori;
        $kategori->delete();

        return back()->with('success', "Kategori {$nama} dihapus.");
    }
}
