<?php

namespace App\Http\Controllers;

use App\Models\Pelanggan;
use App\Models\Pesanan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Profil pelanggan: lihat kartu member + statistik + edit data akun.
 */
class ProfilController extends Controller
{
    /** Halaman profil. */
    public function show(Request $request)
    {
        $pengguna = $request->user();

        $member = $pengguna->nomor_telepon
            ? Pelanggan::where('nomor_telepon', $pengguna->nomor_telepon)->first()
            : null;

        $poin = (int) ($member->poin_loyalitas ?? 0);
        $tier = $poin >= 1000 ? 'Gold' : ($poin >= 500 ? 'Silver' : 'Bronze');

        $selesaiCount = 0;
        $totalBelanja = 0;
        if ($member) {
            $selesai = Pesanan::where('id_pelanggan', $member->id_pelanggan)
                ->where('status_pesanan', 'selesai');
            $selesaiCount = (clone $selesai)->count();
            $totalBelanja = (float) (clone $selesai)->sum('total_harga');
        }

        return view('pelanggan.profil', [
            'member' => $member,
            'poin' => $poin,
            'tier' => $tier,
            'selesaiCount' => $selesaiCount,
            'totalBelanja' => $totalBelanja,
        ]);
    }

    /** Simpan perubahan profil + migrasi data member bila no. HP berubah. */
    public function update(Request $request)
    {
        $pengguna = $request->user();

        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'lowercase',
                'unique:pengguna,email,'.$pengguna->id_pengguna.',id_pengguna'],
            'nomor_telepon' => ['nullable', 'string', 'max:20',
                'unique:pengguna,nomor_telepon,'.$pengguna->id_pengguna.',id_pengguna'],
            'password_saat_ini' => ['nullable', 'string', 'required_with:password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'nama_lengkap.required' => 'Nama wajib diisi.',
            'email.unique' => 'Email sudah dipakai akun lain.',
            'nomor_telepon.unique' => 'Nomor HP sudah dipakai akun lain.',
            'password_saat_ini.required_with' => 'Isi password saat ini untuk mengganti password.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        if (! empty($validated['password']) && ! Hash::check(
            $validated['password_saat_ini'] ?? '', $pengguna->getAuthPassword())) {
            return back()->withErrors(['password_saat_ini' => 'Password saat ini salah.']);
        }

        $teleponLama = $pengguna->nomor_telepon;
        $teleponBaru = isset($validated['nomor_telepon']) && trim($validated['nomor_telepon']) !== ''
            ? trim($validated['nomor_telepon'])
            : null;

        // Migrasi member: pindahkan riwayat & poin ke nomor baru.
        if ($teleponBaru !== $teleponLama) {
            if ($teleponBaru && Pelanggan::where('nomor_telepon', $teleponBaru)->exists()) {
                return back()->withErrors(['nomor_telepon' => 'Nomor ini sudah terdaftar sebagai member lain.']);
            }
            if ($teleponLama && $member = Pelanggan::where('nomor_telepon', $teleponLama)->first()) {
                if ($teleponBaru) {
                    $member->update(['nomor_telepon' => $teleponBaru]);
                }
            } elseif ($teleponBaru) {
                Pelanggan::firstOrCreate(
                    ['nomor_telepon' => $teleponBaru],
                    ['nama_pelanggan' => trim($validated['nama_lengkap']), 'poin_loyalitas' => 0],
                );
            }
        }

        $pengguna->update([
            'nama_lengkap' => trim($validated['nama_lengkap']),
            'email' => strtolower(trim($validated['email'])),
            'nomor_telepon' => $teleponBaru,
            ...(empty($validated['password']) ? [] : ['kata_sandi' => $validated['password']]),
        ]);

        return redirect()->route('pelanggan.profil')
            ->with('success', 'Profil berhasil diperbarui.');
    }
}
