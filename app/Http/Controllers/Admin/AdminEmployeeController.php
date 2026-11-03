<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD akun pegawai (Kasir & Barista) — STRICT RULE:
 * hanya Admin yang boleh membuat/mengedit/menghapus akun pegawai.
 * Peran di sini SENGAJA dikunci ke 'kasir' & 'barista' — controller ini
 * tidak bisa dipakai untuk membuat akun 'admin' maupun 'pelanggan'.
 */
class AdminEmployeeController extends Controller
{
    public static function allowedRoles(): array
    {
        return ['kasir', 'barista'];
    }

    public function index(): View
    {
        $employees = Pengguna::whereIn('peran', self::allowedRoles())
            ->latest('dibuat_pada')
            ->paginate(10);

        return view('admin.employees.index', [
            'employees' => $employees,
            'roles' => self::allowedRoles(),
        ] + $this->sidebarTotals());
    }

    public function create(): View
    {
        return view('admin.employees.create', [
            'roles' => self::allowedRoles(),
        ] + $this->sidebarTotals());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:pengguna,email'],
            'peran' => ['required', Rule::in(self::allowedRoles())],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'nama_lengkap.required' => 'Nama pegawai wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah digunakan akun lain.',
            'peran.required' => 'Peran wajib dipilih.',
            'peran.in' => 'Peran hanya boleh kasir atau barista.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $employee = Pengguna::create([
            'nama_lengkap' => trim($validated['nama_lengkap']),
            'email' => strtolower(trim($validated['email'])),
            'peran' => $validated['peran'],
            // Plain password — cast `hashed` di model yang meng-hash.
            'kata_sandi' => $validated['password'],
        ]);

        return redirect()
            ->route('admin.employees.index')
            ->with('success', "Akun {$employee->peran} {$employee->nama_lengkap} berhasil dibuat.");
    }

    public function edit(Pengguna $employee): View
    {
        if (! in_array($employee->peran, self::allowedRoles(), true)) {
            abort(404);
        }

        return view('admin.employees.edit', [
            'employee' => $employee,
            'roles' => self::allowedRoles(),
        ] + $this->sidebarTotals());
    }

    public function update(Request $request, Pengguna $employee): RedirectResponse
    {
        if (! in_array($employee->peran, self::allowedRoles(), true)) {
            abort(404);
        }

        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('pengguna', 'email')->ignore($employee->id_pengguna, 'id_pengguna')],
            'peran' => ['required', Rule::in(self::allowedRoles())],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'nama_lengkap.required' => 'Nama pegawai wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah digunakan akun lain.',
            'peran.required' => 'Peran wajib dipilih.',
            'peran.in' => 'Peran hanya boleh kasir atau barista.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $employee->fill([
            'nama_lengkap' => trim($validated['nama_lengkap']),
            'email' => strtolower(trim($validated['email'])),
            'peran' => $validated['peran'],
        ]);

        if (! empty($validated['password'])) {
            // Plain password — cast `hashed` di model yang meng-hash.
            $employee->kata_sandi = $validated['password'];
        }

        $employee->save();

        return redirect()
            ->route('admin.employees.index')
            ->with('success', "Data akun {$employee->peran} {$employee->nama_lengkap} berhasil diperbarui.");
    }

    public function destroy(Pengguna $employee): RedirectResponse
    {
        if (! in_array($employee->peran, self::allowedRoles(), true) || $employee->id_pengguna === auth()->id()) {
            abort(404);
        }

        $nama = $employee->nama_lengkap;
        $peran = $employee->peran;

        $employee->delete();

        return redirect()
            ->route('admin.employees.index')
            ->with('success', "Akun {$peran} {$nama} berhasil dihapus.");
    }

    /**
     * Total untuk badge sidebar layout admin (se-tema dashboard).
     */
    private function sidebarTotals(): array
    {
        return [
            'totalKasir' => Pengguna::where('peran', 'kasir')->count(),
            'totalBarista' => Pengguna::where('peran', 'barista')->count(),
            'totalPelanggan' => Pengguna::where('peran', 'pelanggan')->count(),
        ];
    }
}
