<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $produk = Produk::with('kategori:id_kategori,nama_kategori')
            ->where('status_tersedia', true)
            ->when($request->query('kategori'), function ($query, $namaKategori) {
                $query->whereHas('kategori', fn ($q) => $q->where('nama_kategori', $namaKategori));
            })
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_produk', 'like', "%{$search}%")
                        ->orWhere('deskripsi', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama_produk')
            ->get(['id_produk', 'id_kategori', 'kode_produk', 'nama_produk', 'deskripsi', 'harga', 'stok', 'status_tersedia', 'url_gambar']);

        return response()->json(['data' => $produk]);
    }

    public function show(Produk $produk): JsonResponse
    {
        $produk->load('kategori:id_kategori,nama_kategori');

        return response()->json(['data' => $produk]);
    }
}
