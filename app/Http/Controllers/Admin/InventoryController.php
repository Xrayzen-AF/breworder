<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogReorder;
use App\Models\StokBiji;
use App\Services\Agents\InventoryAgent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Stok Bahan — kelola semua bahan baku (biji kopi, susu, sirup, kemasan, dll)
 * beserta pesan ulang saat stok menipis. Memakai InventoryAgent untuk status
 * kritis, proyeksi habis, dan jumlah pesan ulang.
 */
class InventoryController extends Controller
{
    /** Pilihan kategori bahan di form. */
    public const KATEGORI = [
        'Biji Kopi',
        'Susu & Krimer',
        'Sirup & Gula',
        'Kemasan',
        'Lainnya',
    ];

    /** Pilihan satuan stok. */
    public const SATUAN = ['kg', 'liter', 'pcs', 'pack', 'botol'];

    public function index(InventoryAgent $agent): View
    {
        $semua = StokBiji::withCount('logReorder')
            ->orderBy('kategori')
            ->orderBy('nama_biji')
            ->get();

        $alerts = $semua->filter(fn (StokBiji $b) => $b->isKritis())->map(fn (StokBiji $b) => [
            'biji' => $b,
            'payload' => $agent->alertPayload($b),
            'reorder_qty' => $agent->reorderQuantity($b),
            'deplesi' => $agent->estimatedDepletion($b),
        ])->values();

        $riwayat = LogReorder::with('stokBiji:id_stok_biji,nama_biji')
            ->latest('waktu_dispatch')
            ->limit(10)
            ->get();

        $kesehatan = $semua->count() > 0
            ? round(($semua->count() - $alerts->count()) / $semua->count() * 100)
            : 100;

        return view('admin.inventory.index', [
            'semua' => $semua,
            'alerts' => $alerts,
            'riwayat' => $riwayat,
            'kesehatan' => $kesehatan,
            'kategoriList' => self::KATEGORI,
            'satuanList' => self::SATUAN,
            'activeMenu' => 'inventory',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'nama_biji' => ['required', 'string', 'max:255', 'unique:stok_biji,nama_biji'],
            'kategori' => ['required', 'string', Rule::in(self::KATEGORI)],
            'satuan' => ['required', 'string', Rule::in(self::SATUAN)],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'sisa_kg' => ['required', 'numeric', 'min:0', 'max:10000'],
            'threshold_kg' => ['required', 'numeric', 'min:0', 'max:10000'],
            'safety_cap_kg' => ['required', 'numeric', 'min:0', 'max:10000'],
            'laju_konsumsi_kg_per_jam' => ['required', 'numeric', 'min:0', 'max:1000'],
        ]);

        StokBiji::create($v);

        return back()->with('success', "Bahan {$v['nama_biji']} berhasil ditambahkan.");
    }

    public function update(Request $request, StokBiji $inventory): RedirectResponse
    {
        $v = $request->validate([
            'kategori' => ['required', 'string', Rule::in(self::KATEGORI)],
            'satuan' => ['required', 'string', Rule::in(self::SATUAN)],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'sisa_kg' => ['required', 'numeric', 'min:0', 'max:10000'],
            'threshold_kg' => ['required', 'numeric', 'min:0', 'max:10000'],
            'safety_cap_kg' => ['required', 'numeric', 'min:0', 'max:10000'],
            'laju_konsumsi_kg_per_jam' => ['required', 'numeric', 'min:0', 'max:1000'],
        ]);

        $inventory->update($v);

        return back()->with('success', "Stok {$inventory->nama_biji} diperbarui.");
    }

    public function destroy(StokBiji $inventory): RedirectResponse
    {
        $nama = $inventory->nama_biji;
        $inventory->delete();

        return back()->with('success', "Bahan {$nama} dihapus.");
    }

    /** Reorder web (sinkron dengan POST /api/v1/inventory/reorder). */
    public function reorder(Request $request, StokBiji $inventory, InventoryAgent $agent): RedirectResponse
    {
        $v = $request->validate([
            'destination' => ['required', 'string', 'max:255'],
            'order_quantity_kg' => ['nullable', 'numeric', 'min:0.1', 'max:1000'],
        ]);

        $qty = (float) ($v['order_quantity_kg'] ?? $agent->reorderQuantity($inventory));
        if ($qty <= 0) {
            return back()->with('error', 'Stok masih di atas target — pesan ulang belum diperlukan.');
        }

        DB::transaction(function () use ($inventory, $v, $qty) {
            $inventory->increment('sisa_kg', $qty);
            LogReorder::create([
                'id_stok_biji' => $inventory->id_stok_biji,
                'destination' => $v['destination'],
                'order_quantity_kg' => $qty,
                'status' => 'terkirim',
                'waktu_dispatch' => now(),
            ]);
        });

        return back()->with('success', "Pesanan ulang {$qty} {$inventory->satuan} {$inventory->nama_biji} ke {$v['destination']} terkirim.");
    }
}
