<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Halaman login utama (satu login sentral untuk semua role).
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * Proses login: deteksi role lalu redirect ke dashboard masing-masing.
     * Mendukung login via email ATAU nomor_telepon (label form: Email / No. HP).
     * Verifikasi password dilakukan terhadap kolom `kata_sandi`.
     */
    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email / No. HP wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $loginInput = trim($validated['email']);
        $password = $validated['password'];

        $isEmail = filter_var($loginInput, FILTER_VALIDATE_EMAIL) !== false;
        $field = $isEmail ? 'email' : 'nomor_telepon';
        $value = $isEmail ? strtolower($loginInput) : $loginInput;

        if (! Auth::attempt([$field => $value, 'password' => $password])) {
            throw ValidationException::withMessages([
                'email' => 'Email / No. HP atau password salah.',
            ]);
        }

        /** @var Pengguna $pengguna */
        $pengguna = Auth::user();

        // Tolak akun yang dinonaktifkan — paksa logout agar sesi bersih.
        if (! $pengguna->status_aktif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Akun Anda sudah dinonaktifkan. Hubungi admin.',
            ]);
        }

        $request->session()->regenerate();

        return $this->safeRedirectByRole($request, $pengguna);
    }

    /**
     * Logout: sesi dihancurkan lalu pengguna kembali ke halaman login.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Anda telah berhasil keluar. Silakan login kembali.');
    }

    /**
     * Tentukan tujuan redirect berdasarkan peran pengguna.
     */
    private function redirectPathByRole(Pengguna $pengguna): string
    {
        return match ($pengguna->peran) {
            'admin' => route('admin.dashboard'),
            'kasir' => route('kasir.dashboard'),
            'barista' => route('barista.dashboard'),
            default => route('pelanggan.dashboard'),
        };
    }

    /**
     * Redirect aman: hanya hormati url.intended jika prefix-nya
     * sesuai dengan peran user. Mencegah pelanggan yang login
     * terlempar ke /admin/* (403) hanya karena sempat membuka
     * halaman admin sebagai guest sebelum login.
     */
    private function safeRedirectByRole(Request $request, Pengguna $pengguna): RedirectResponse
    {
        $default = $this->redirectPathByRole($pengguna);
        $intended = $request->session()->get('url.intended');

        if (is_string($intended) && $intended !== '' && $this->isUrlAllowedForRole($intended, $pengguna->peran)) {
            $request->session()->forget('url.intended');

            return redirect()->to($intended);
        }

        $request->session()->forget('url.intended');

        return redirect()->to($default);
    }

    private function isUrlAllowedForRole(string $url, string $peran): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return match ($peran) {
            'admin' => str_starts_with($path, '/admin'),
            'kasir' => str_starts_with($path, '/kasir'),
            'barista' => str_starts_with($path, '/barista'),
            'pelanggan' => str_starts_with($path, '/pelanggan'),
            default => false,
        };
    }
}
