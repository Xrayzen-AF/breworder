<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Kategori::withCount('produk')->orderBy('nama_kategori')->get(),
        ]);
    }
}
