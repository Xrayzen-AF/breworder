<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use App\Models\Pengguna;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /**
     * Halaman registrasi — khusus PELANGGAN (strict rule: kasir & barista
     * tidak boleh mendaftar mandiri; dibuat oleh Admin via dashboard).
     */
    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    /**
     * Proses registrasi: selalu menyimpan akun dengan peran 'pelanggan'.
     * Nomor HP wajib & unik — dipakai sebagai ID member loyalitas,
     * riwayat pesanan, dan login alternatif.
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'lowercase', 'unique:pengguna,email'],
            'nomor_telepon' => ['required', 'string', 'max:20', 'unique:pengguna,nomor_telepon'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'nama_lengkap.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah terdaftar.',
            'nomor_telepon.required' => 'Nomor HP wajib diisi (untuk member & riwayat pesanan).',
            'nomor_telepon.unique' => 'Nomor HP sudah terdaftar. Silakan masuk.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $pengguna = Pengguna::create([
            'nama_lengkap' => trim($validated['nama_lengkap']),
            'email' => strtolower(trim($validated['email'])),
            'nomor_telepon' => trim($validated['nomor_telepon']),
            // Plain password — cast `hashed` di model yang meng-hash.
            'kata_sandi' => $validated['password'],
            'peran' => 'pelanggan',
            'status_aktif' => true,
        ]);

        // Daftarkan langsung sebagai member loyalitas (anti baris NULL berbagi).
        Pelanggan::firstOrCreate(
            ['nomor_telepon' => $pengguna->nomor_telepon],
            ['nama_pelanggan' => $pengguna->nama_lengkap, 'poin_loyalitas' => 0],
        );

        Auth::login($pengguna);

        // Cegah session fixation pada akun yang baru dibuat.
        $request->session()->regenerate();

        return redirect()->route('pelanggan.dashboard')
            ->with('success', 'Pendaftaran berhasil! Selamat datang.');
    }
}
