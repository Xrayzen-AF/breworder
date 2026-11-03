<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customer CRM & Loyalty — kelola member, poin, tier.
 * Tier: Bronze (<100), Silver (100-299), Gold (>=300).
 */
class CustomerController extends Controller
{
    public static function tier(int $poin): string
    {
        return $poin >= 300 ? 'Gold' : ($poin >= 100 ? 'Silver' : 'Bronze');
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $members = Pelanggan::withCount('pesanan')
            ->withSum(['pesanan as total_belanja' => fn ($qq) => $qq->where('status_pesanan', 'selesai')], 'total_harga')
            ->when($q !== '', fn ($qq) => $qq->where(fn ($w) => $w
                ->where('nama_pelanggan', 'like', "%{$q}%")
                ->orWhere('nomor_telepon', 'like', "%{$q}%")))
            ->orderByDesc('poin_loyalitas')
            ->paginate(12)
            ->withQueryString();

        $total = Pelanggan::count();
        $baruHariIni = Pelanggan::whereDate('terdaftar_pada', today())->count();
        $avgPoin = (float) (Pelanggan::avg('poin_loyalitas') ?? 0);
        $gold = Pelanggan::where('poin_loyalitas', '>=', 300)->count();

        return view('admin.customers.index', [
            'members' => $members,
            'q' => $q,
            'total' => $total,
            'baruHariIni' => $baruHariIni,
            'avgPoin' => $avgPoin,
            'gold' => $gold,
            'activeMenu' => 'customers',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'nama_pelanggan' => ['required', 'string', 'max:255'],
            'nomor_telepon' => ['nullable', 'string', 'max:20', 'unique:pelanggan,nomor_telepon'],
            'poin_loyalitas' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        Pelanggan::create([
            'nama_pelanggan' => trim($v['nama_pelanggan']),
            'nomor_telepon' => $v['nomor_telepon'] ?? null,
            'poin_loyalitas' => $v['poin_loyalitas'] ?? 0,
        ]);

        return back()->with('success', "Member {$v['nama_pelanggan']} ditambahkan.");
    }

    /** Tambah/kurang poin manual (adjustment kasir). */
    public function adjustPoin(Request $request, Pelanggan $customer): RedirectResponse
    {
        $v = $request->validate([
            'delta' => ['required', 'integer', 'min:-1000', 'max:1000'],
        ]);

        $baru = max(0, $customer->poin_loyalitas + $v['delta']);
        $customer->update(['poin_loyalitas' => $baru]);

        $aksi = $v['delta'] >= 0 ? "+{$v['delta']}" : (string) $v['delta'];

        return back()->with('success', "Poin {$customer->nama_pelanggan} {$aksi} → {$baru} (".self::tier($baru).').');
    }

    public function destroy(Pelanggan $customer): RedirectResponse
    {
        $nama = $customer->nama_pelanggan;
        $customer->delete();

        return back()->with('success', "Member {$nama} dihapus.");
    }
}
