<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Services\Agents\StaffingAgent;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Staff & Roster — roster shift + rekomendasi StaffingAgent + CRUD akun.
 * Halaman ini ringkasan roster; kelola akun penuh tetap di employees.*.
 */
class StaffController extends Controller
{
    public function index(StaffingAgent $staffing): View
    {
        $telemetry = $staffing->trafficTelemetry();
        $roster = $staffing->activeRoster();

        $staff = Pengguna::whereIn('peran', ['kasir', 'barista'])
            ->latest('dibuat_pada')
            ->paginate(10);

        $totalKasir = Pengguna::where('peran', 'kasir')->count();
        $totalBarista = Pengguna::where('peran', 'barista')->count();
        $aktif = Pengguna::whereIn('peran', ['kasir', 'barista'])->where('status_aktif', true)->count();

        // Beban tiket per peran hari ini (proxy: tiket selesai+diproses dibagi staff aktif).
        $tiketHariIni = Pesanan::whereDate('waktu_pesan', today())
            ->whereIn('status_pesanan', ['baru', 'diproses', 'selesai'])->count();

        return view('admin.staff.index', [
            'telemetry' => $telemetry,
            'roster' => $roster,
            'staff' => $staff,
            'totalKasir' => $totalKasir,
            'totalBarista' => $totalBarista,
            'totalAktif' => $aktif,
            'tiketHariIni' => $tiketHariIni,
            'activeMenu' => 'staff',
        ]);
    }

    /** Toggle status aktif pegawai dari halaman roster. */
    public function toggle(Pengguna $staff): RedirectResponse
    {
        abort_unless(in_array($staff->peran, ['kasir', 'barista'], true), 404);
        $staff->update(['status_aktif' => ! $staff->status_aktif]);

        $label = $staff->status_aktif ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Pegawai {$staff->nama_lengkap} {$label}.");
    }
}
